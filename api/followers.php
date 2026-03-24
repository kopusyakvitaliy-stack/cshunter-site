<?php
/**
 * GET  /api/followers.php?type=followers|following&steam_id=STEAMID&offset=0
 * POST /api/followers.php  body: action=unfollow&steam_id=STEAMID
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'no_db']);
    exit;
}

// ── POST: unfollow ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
        exit;
    }

    // ── CSRF ──────────────────────────────────────────────────────────────────
    if (!verifyCsrfToken()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
        exit;
    }

    session_write_close();

    $action    = $_POST['action']   ?? '';
    $targetSid = $_POST['steam_id'] ?? '';

    if ($action !== 'unfollow' || !preg_match('/^\d{17}$/', $targetSid)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid']);
        exit;
    }

    $me = getUser();
    try {
        $s = $pdo->prepare('SELECT id FROM users WHERE steam_id = ?');
        $s->execute([$me['steam_id']]);
        $myId = (int)$s->fetchColumn();

        $s->execute([$targetSid]);
        $theirId = (int)$s->fetchColumn();

        if (!$myId || !$theirId) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'user_not_found']);
            exit;
        }

        $pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?')
            ->execute([$myId, $theirId]);

        // Return updated counts
        $r = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
        $r->execute([$myId]);
        $followingCount = (int)$r->fetchColumn();

        echo json_encode(['ok' => true, 'following_count' => $followingCount]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'db_error']);
    }
    exit;
}

// ── GET: list followers or following ─────────────────────────────────────────
session_write_close();

$type    = $_GET['type']     ?? '';
$steamId = $_GET['steam_id'] ?? '';
$offset  = max(0, (int)($_GET['offset'] ?? 0));
$limit   = 20;

if (!in_array($type, ['followers', 'following'], true) || !preg_match('/^\d{17}$/', $steamId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

try {
    // Get the user's DB id
    $s = $pdo->prepare('SELECT id FROM users WHERE steam_id = ?');
    $s->execute([$steamId]);
    $userId = (int)$s->fetchColumn();

    if (!$userId) {
        echo json_encode(['ok' => true, 'users' => [], 'has_more' => false]);
        exit;
    }

    if ($type === 'followers') {
        // People who follow this user
        $stmt = $pdo->prepare(
            'SELECT u.steam_id, u.steam_name, u.avatar_url, f.created_at
             FROM follows f
             JOIN users u ON u.id = f.follower_id
             WHERE f.followed_id = ?
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?'
        );
    } else {
        // People this user follows
        $stmt = $pdo->prepare(
            'SELECT u.steam_id, u.steam_name, u.avatar_url, f.created_at
             FROM follows f
             JOIN users u ON u.id = f.followed_id
             WHERE f.follower_id = ?
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?'
        );
    }

    $stmt->execute([$userId, $limit + 1, $offset]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);

    $users = array_map(fn($r) => [
        'steam_id'   => $r['steam_id'],
        'steam_name' => $r['steam_name'],
        'avatar_url' => $r['avatar_url'] ?? '',
        'profile_url'=> SITE_URL . '/profile/' . $r['steam_id'],
    ], $rows);

    echo json_encode(['ok' => true, 'users' => $users, 'has_more' => $hasMore]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}