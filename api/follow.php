<?php
/**
 * POST /api/follow.php
 * body: action=follow|unfollow & steam_id=STEAMID64
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
if (!verifyCsrfToken()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
    exit;
}

session_write_close();

$action    = $_POST['action']   ?? '';
$targetSid = $_POST['steam_id'] ?? '';

if (!in_array($action, ['follow', 'unfollow'], true) ||
    !preg_match('/^\d{17}$/', $targetSid)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

$me = getUser();
if ($me['steam_id'] === $targetSid) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'self_follow']);
    exit;
}

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'no_db']);
    exit;
}

try {
    // Один запит замість двох окремих SELECT
    $stmt = $pdo->prepare('SELECT id, steam_id FROM users WHERE steam_id IN (?, ?)');
    $stmt->execute([$me['steam_id'], $targetSid]);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[$row['steam_id']] = (int)$row['id'];
    }

    $myId     = $rows[$me['steam_id']] ?? 0;
    $theirId  = $rows[$targetSid]      ?? 0;

    if (!$myId || !$theirId) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'user_not_found']);
        exit;
    }

    if ($action === 'follow') {
        $pdo->prepare('INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)')
            ->execute([$myId, $theirId]);
    } else {
        $pdo->prepare('DELETE FROM follows WHERE follower_id = ? AND followed_id = ?')
            ->execute([$myId, $theirId]);
    }

    $fc = $pdo->prepare('SELECT COUNT(*) FROM follows WHERE followed_id = ?');
    $fc->execute([$theirId]);

    echo json_encode([
        'ok'        => true,
        'following' => $action === 'follow',
        'followers' => (int)$fc->fetchColumn(),
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
