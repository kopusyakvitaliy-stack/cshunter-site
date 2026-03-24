<?php
/**
 * Reaction score API
 * POST action=save       — save result (auth required)
 * GET  action=leaderboard — top 10 best avg
 * GET  action=my_history  — last 10 + personal best (auth required)
 * GET  action=my_status   — attempts today + next available time (auth required)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Helper: get user db row ───────────────────────────────────────────────────
function getDbUser($pdo, string $steamId): ?array {
    $stmt = $pdo->prepare("SELECT id, reaction_attempts_today, reaction_last_attempt FROM users WHERE steam_id=?");
    $stmt->execute([$steamId]);
    return $stmt->fetch() ?: null;
}

// ── Save score ────────────────────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) { echo json_encode(['error' => 'auth']); exit; }
    if (!$pdo)         { echo json_encode(['error' => 'db']);   exit; }
    if (!verifyCsrfToken()) { http_response_code(403); echo json_encode(['error' => 'csrf']); exit; }

    $avg         = (int)($_POST['avg_ms']      ?? 0);
    $best        = (int)($_POST['best_ms']     ?? 0);
    $splitsRaw   = $_POST['splits']            ?? '[]';
    $earlyClicks = (int)($_POST['early_clicks'] ?? 0);

    // ── Validate range ────────────────────────────────────────────────────────
    if ($avg < 80 || $avg > 2000 || $best < 80 || $best > 2000) {
        echo json_encode(['error' => 'invalid_range']); exit;
    }

    // ── Validate splits integrity ─────────────────────────────────────────────
    $splits = json_decode($splitsRaw, true);
    if (!is_array($splits) || count($splits) !== 5) {
        echo json_encode(['error' => 'invalid_splits']); exit;
    }
    foreach ($splits as $s) {
        if (!is_int($s) && !is_float($s)) { echo json_encode(['error' => 'invalid_split_type']); exit; }
        if ($s < 80 || $s > 2000)         { echo json_encode(['error' => 'invalid_split_value']); exit; }
    }
    $computedAvg = (int)round(array_sum($splits) / count($splits));
    if (abs($computedAvg - $avg) > 5) { // allow 5ms rounding diff
        echo json_encode(['error' => 'avg_mismatch']); exit;
    }

    $user   = getUser();
    $dbUser = getDbUser($pdo, $user['steam_id']);
    if (!$dbUser) { echo json_encode(['error' => 'user_not_found']); exit; }

    // ── Rate limit: 5 attempts per 24h ───────────────────────────────────────
    $lastAttempt = $dbUser['reaction_last_attempt'];
    $attempts    = (int)$dbUser['reaction_attempts_today'];
    $resetTime   = null;

    if ($lastAttempt) {
        $hoursSince = (time() - strtotime($lastAttempt)) / 3600;
        if ($hoursSince >= 24) {
            // Reset counter
            $attempts = 0;
            $pdo->prepare("UPDATE users SET reaction_attempts_today=0 WHERE id=?")->execute([$dbUser['id']]);
        }
    }

    if ($attempts >= 5) {
        $nextTime = strtotime($lastAttempt) + 86400;
        echo json_encode([
            'error'      => 'limit_reached',
            'next_at'    => $nextTime,
            'attempts'   => $attempts,
        ]);
        exit;
    }

    // ── Get previous best BEFORE saving ──────────────────────────────────────
    $prevPb = $pdo->prepare("SELECT MIN(avg_ms) FROM reaction_scores WHERE user_id=?");
    $prevPb->execute([$dbUser['id']]);
    $prevBest = $prevPb->fetchColumn();
    $prevBest = $prevBest !== false ? (int)$prevBest : null;

    // ── Save ──────────────────────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO reaction_scores (user_id, avg_ms, best_ms, splits, early_clicks)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$dbUser['id'], $avg, $best, $splitsRaw, $earlyClicks]);

    // ── Update attempts counter ───────────────────────────────────────────────
    $newAttempts = $attempts + 1;
    $pdo->prepare("
        UPDATE users SET
            reaction_attempts_today = ?,
            reaction_last_attempt   = NOW()
        WHERE id = ?
    ")->execute([$newAttempts, $dbUser['id']]);

    $isPB = ($prevBest === null) ? false : ($avg < $prevBest);

    echo json_encode([
        'ok'             => true,
        'is_pb'          => $isPB,
        'personal_best'  => min($avg, $prevBest ?? $avg),
        'attempts_left'  => max(0, 5 - $newAttempts),
    ]);
    exit;
}

// ── My status (attempts + timer) ─────────────────────────────────────────────
if ($action === 'my_status') {
    if (!isLoggedIn() || !$pdo) { echo json_encode(['attempts_left' => 5, 'next_at' => null]); exit; }

    $user   = getUser();
    $dbUser = getDbUser($pdo, $user['steam_id']);
    if (!$dbUser) { echo json_encode(['attempts_left' => 5, 'next_at' => null]); exit; }

    $attempts    = (int)$dbUser['reaction_attempts_today'];
    $lastAttempt = $dbUser['reaction_last_attempt'];
    $nextAt      = null;

    if ($lastAttempt) {
        $hoursSince = (time() - strtotime($lastAttempt)) / 3600;
        if ($hoursSince >= 24) {
            $attempts = 0;
            $pdo->prepare("UPDATE users SET reaction_attempts_today=0 WHERE id=?")->execute([$dbUser['id']]);
        } elseif ($attempts >= 5) {
            $nextAt = strtotime($lastAttempt) + 86400;
        }
    }

    echo json_encode([
        'attempts_left' => max(0, 5 - $attempts),
        'attempts_used' => $attempts,
        'next_at'       => $nextAt,
    ]);
    exit;
}

// ── Leaderboard ───────────────────────────────────────────────────────────────
if ($action === 'leaderboard') {
    if (!$pdo) { echo json_encode(['leaderboard' => []]); exit; }

    $rows = $pdo->query("
        SELECT
            rs.user_id,
            MIN(rs.avg_ms)  AS best_avg,
            MIN(rs.best_ms) AS best_single,
            u.steam_name,
            u.avatar_url,
            u.steam_id,
            u.faceit_level,
            (SELECT rs2.created_at FROM reaction_scores rs2
             WHERE rs2.user_id = rs.user_id
             ORDER BY rs2.avg_ms ASC LIMIT 1) AS achieved_at
        FROM reaction_scores rs
        JOIN users u ON u.id = rs.user_id
        GROUP BY rs.user_id, u.steam_name, u.avatar_url, u.steam_id, u.faceit_level
        ORDER BY best_avg ASC
        LIMIT 10
    ")->fetchAll();

    $result = [];
    foreach ($rows as $i => $r) {
        $lvl = (int)($r['faceit_level'] ?? 0);
        $result[] = [
            'rank'         => $i + 1,
            'steam_name'   => $r['steam_name'],
            'avatar_url'   => $r['avatar_url'],
            'steam_id'     => $r['steam_id'],
            'best_avg'     => (int)$r['best_avg'],
            'best_single'  => (int)$r['best_single'],
            'achieved_at'  => $r['achieved_at'],
            'faceit_level' => $lvl > 0 ? $lvl : null,
        ];
    }

    echo json_encode(['leaderboard' => $result]);
    exit;
}

// ── My history ────────────────────────────────────────────────────────────────
if ($action === 'my_history') {
    if (!isLoggedIn() || !$pdo) { echo json_encode(['history' => [], 'personal_best' => null]); exit; }

    $user   = getUser();
    $dbUser = getDbUser($pdo, $user['steam_id']);
    if (!$dbUser) { echo json_encode(['history' => [], 'personal_best' => null]); exit; }

    // Personal best (separate query)
    $pbStmt = $pdo->prepare("
        SELECT avg_ms, best_ms, created_at FROM reaction_scores
        WHERE user_id = ? ORDER BY avg_ms ASC LIMIT 1
    ");
    $pbStmt->execute([$dbUser['id']]);
    $pbRow = $pbStmt->fetch();

    // Last 10
    $stmt = $pdo->prepare("
        SELECT avg_ms, best_ms, splits, created_at
        FROM reaction_scores
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$dbUser['id']]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'history'       => $rows,
        'personal_best' => $pbRow ? [
            'avg_ms'     => (int)$pbRow['avg_ms'],
            'best_ms'    => (int)$pbRow['best_ms'],
            'created_at' => $pbRow['created_at'],
        ] : null,
    ]);
    exit;
}

echo json_encode(['error' => 'unknown_action']);
