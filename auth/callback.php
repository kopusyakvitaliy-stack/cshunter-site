<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/teams.php';

// Normalize params: PHP replaces dots with underscores in GET keys
// So openid.mode becomes openid_mode — we need to reverse that
$raw = [];
foreach ($_GET as $k => $v) {
    // restore dots
    $key = str_replace('_', '.', $k);
    // but "openid.ns" should stay, "openid.return.to" etc — only first segment matters
    // Actually just replace all underscores in key with dots for openid params
    $raw[$key] = $v;
}

// Also try original keys in case server passes them correctly
foreach ($_GET as $k => $v) {
    if (!isset($raw[$k])) $raw[$k] = $v;
}

$claimedId = $raw['openid.claimed_id'] ?? $raw['openid_claimed_id'] ?? '';

if (empty($claimedId)) {
    header('Location: ' . SITE_URL . '/?error=no_claimed_id');
    exit;
}

// Validate with Steam
if (!validateSteamLogin($raw)) {
    // Try with underscores as-is in case of misconfiguration
    $raw2 = $_GET;
    $raw2['openid.mode'] = $raw2['openid_mode'] ?? 'check_authentication';
    if (!validateSteamLogin($raw2)) {
        header('Location: ' . SITE_URL . '/?error=validation_failed');
        exit;
    }
}

$steamId64 = getSteamId64FromOpenId($claimedId);
if (!$steamId64) {
    // Try parsing directly from URL
    if (preg_match('/(\d{17,})/', $claimedId, $m)) {
        $steamId64 = $m[1];
    } else {
        header('Location: ' . SITE_URL . '/?error=no_steamid');
        exit;
    }
}

// Fetch Steam user info
$steamUser = fetchSteamUser($steamId64);

if (!$steamUser) {
    // Steam API failed — try to get existing user from DB
    $existingUser = null;
    if ($pdo) {
        $s = $pdo->prepare('SELECT * FROM users WHERE steam_id = ?');
        $s->execute([$steamId64]);
        $existingUser = $s->fetch() ?: null;
    }
    $_SESSION['user'] = [
        'steam_id'    => $steamId64,
        'steam_name'  => $existingUser['steam_name'] ?? ('Player_' . substr($steamId64, -5)),
        'avatar_url'  => $existingUser['avatar_url']  ?? 'https://avatars.steamstatic.com/fef49e7fa7e1997310d705b2a6158ff8dc1cdfeb_medium.jpg',
        'profile_url' => $existingUser['profile_url'] ?? 'https://steamcommunity.com/profiles/' . $steamId64,
        'country'     => $existingUser['country']     ?? '',
        'db_id'       => $existingUser['id']          ?? null,
    ];
} else {
    $dbUser = saveOrUpdateUser($pdo, $steamUser);

    // ── Перевірка бану перед входом ───────────────────────────────────────────
    if ($pdo && $steamId64) {
        $ban = checkBan($pdo, $steamId64);
        if ($ban) {
            showBanPage($ban);
        }
    }

    // ── Позначаємо що юзер справді залогінився ────────────────────────────────
    if ($pdo && $steamId64) {
        try {
            $pdo->prepare("UPDATE users SET has_logged_in = 1 WHERE steam_id = ?")
                ->execute([$steamId64]);
        } catch (Throwable $e) {}
    }
    // Load fav team from DB
    $favTeamData = null;
    if (!empty($dbUser['fav_team_id']) && $pdo) {
        $ft = getTeamById($pdo, (int)$dbUser['fav_team_id']);
        if ($ft) {
            $ftLogo = null;
            if (!empty($ft['logo_local']) && file_exists(__DIR__ . '/../' . $ft['logo_local'])) {
                $ftLogo = SITE_URL . '/' . $ft['logo_local'];
            } elseif (!empty($ft['logo_url'])) {
                $ftLogo = $ft['logo_url'];
            }
            $favTeamData = ['id' => (int)$ft['id'], 'name' => $ft['name'], 'logo' => $ftLogo];
        }
    }

    $_SESSION['user'] = [
        'steam_id'    => $steamUser['steamid'],
        'steam_name'  => $steamUser['personaname'],
        'avatar_url'  => $steamUser['avatarfull'] ?? $steamUser['avatarmedium'] ?? $steamUser['avatar'],
        'profile_url' => $steamUser['profileurl'],
        'country'     => $steamUser['loccountrycode'] ?? '',
        'db_id'       => $dbUser['id'] ?? null,
        'fav_team'    => $favTeamData,
    ];
}

header('Location: ' . SITE_URL . '/');
exit;