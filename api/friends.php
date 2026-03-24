<?php
/**
 * GET /api/friends.php?steam_id=STEAMID
 * Returns friends list with online status and registration info.
 * Cached in session for 5 minutes per steam_id.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/steam_friends.php';
require_once __DIR__ . '/../includes/items.php';

header('Content-Type: application/json');

$steamId = $_GET['steam_id'] ?? '';
if (!preg_match('/^\d{17}$/', $steamId)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}

// ── Session cache per steam_id ────────────────────────────────────────────────
$cacheKey  = 'friends_d_' . $steamId;
$cacheTime = 'friends_t_' . $steamId;

// Own profile uses shared cache key
$me = getUser();
if ($me && $me['steam_id'] === $steamId) {
    $cacheKey  = 'friends_d';
    $cacheTime = 'friends_t';
}

$friendDetails = [];
try {
    if (empty($_SESSION[$cacheTime]) || time() - $_SESSION[$cacheTime] > 300) {
        $rawFriends = getSteamFriends($steamId);
        if (!empty($rawFriends)) {
            $ids = array_column($rawFriends, 'steamid');
            $friendDetails = getSteamUserSummaries($ids);
            uasort($friendDetails, fn($a, $b) =>
                (isPersonaOnline($a['personastate'] ?? 0) ? 0 : 1) <=>
                (isPersonaOnline($b['personastate'] ?? 0) ? 0 : 1)
            );
        }
        $_SESSION[$cacheKey]  = $friendDetails;
        $_SESSION[$cacheTime] = time();
    } else {
        $friendDetails = $_SESSION[$cacheKey] ?? [];
    }
} catch (Throwable $e) {
    session_write_close();
    echo json_encode(['ok' => false, 'error' => 'steam_api_error']);
    exit;
}

session_write_close();

if (empty($friendDetails)) {
    echo json_encode(['ok' => true, 'friends' => [], 'registered' => []]);
    exit;
}

// ── Check which friends are registered on our site + load their frames ────────
$registeredSteamIds = [];
$friendUserIds      = [];  // steam_id => db user id
$friendFrames       = [];  // db user id => frame item row
if ($pdo) {
    try {
        $ids = array_column(array_values($friendDetails), 'steamid');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rs = $pdo->prepare("SELECT id, steam_id FROM users WHERE steam_id IN ($placeholders) AND has_logged_in = 1");
        $rs->execute($ids);
        foreach ($rs->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $registeredSteamIds[$row['steam_id']] = true;
            $friendUserIds[$row['steam_id']]      = (int)$row['id'];
        }
        // Batch-load equipped frames for all registered friends
        if (!empty($friendUserIds)) {
            $friendFrames = ItemService::getEquippedFramesBatch($pdo, array_values($friendUserIds));
        }
    } catch (Throwable $e) {}
}

// ── Build response ────────────────────────────────────────────────────────────
$friends = [];
$offlineCount = 0;
foreach ($friendDetails as $f) {
    $online  = isPersonaOnline($f['personastate'] ?? 0);
    $inGame  = !empty($f['gameid']);
    $isReg   = isset($registeredSteamIds[$f['steamid']]);

    if (!$online) {
        $offlineCount++;
        // Always include registered users, only limit unregistered offline friends
        if ($offlineCount > 40 && !$isReg) continue;
    }

    $status = $inGame ? 'ingame' : ($online ? 'online' : 'offline');
    $statusLabel = $inGame
        ? ('🎮 ' . ($f['gameextrainfo'] ?? 'В грі'))
        : getPersonaStateName($f['personastate'] ?? 0);

    $isReg    = isset($registeredSteamIds[$f['steamid']]);
    $dbUserId = $friendUserIds[$f['steamid']] ?? 0;
    $frame    = ($dbUserId && isset($friendFrames[$dbUserId])) ? $friendFrames[$dbUserId] : null;

    $friends[] = [
        'steamid'      => $f['steamid'],
        'name'         => $f['personaname'],
        'avatar'       => $f['avatarmedium'] ?? $f['avatar'] ?? '',
        'profile_url'  => SITE_URL . '/profile/' . $f['steamid'],
        'status'       => $status,
        'status_label' => $statusLabel,
        'registered'   => $isReg,
        'frame_img'    => ($frame && !empty($frame['image_lg'])) ? SITE_URL . '/' . $frame['image_lg'] : null,
        'frame_img_sm' => ($frame && !empty($frame['image_lg'])) ? (function() use ($frame) {
            $lg = $frame['image_lg'];
            $sm = preg_replace('/_lg(\.\w+)$/', '_sm$1', $lg);
            if ($sm === $lg) $sm = preg_replace('/(\.[^.]+)$/', '_sm$1', $lg);
            $path = __DIR__ . '/../' . $sm;
            return file_exists($path) ? SITE_URL . '/' . $sm : SITE_URL . '/' . $lg;
        })() : null,
        'frame_rarity' => $frame['rarity'] ?? null,
        'frame_shape'  => $frame['avatar_shape'] ?? 'rounded',
    ];
}

$totalOnline     = count(array_filter($friendDetails, fn($f) => isPersonaOnline($f['personastate'] ?? 0)));
$totalOffline    = count(array_filter($friendDetails, fn($f) => !isPersonaOnline($f['personastate'] ?? 0)));
$totalAll        = count($friendDetails);
// Count only registered friends that are actually in the response (not cut off by offline limit)
$totalRegistered = count(array_filter($friends, fn($f) => $f['registered'] === true));

echo json_encode([
    'ok'      => true,
    'friends' => $friends,
    'counts'  => [
        'all'        => $totalAll,
        'online'     => $totalOnline,
        'offline'    => $totalOffline,
        'registered' => $totalRegistered,
        'offline_hidden' => max(0, $totalOffline - 40),
    ],
]);