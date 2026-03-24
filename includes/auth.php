<?php
require_once __DIR__ . '/../config.php';

function getSteamLoginUrl() {
    $return_url = SITE_URL . '/auth/callback.php';
    $params = [
        'openid.ns'         => 'http://specs.openid.net/auth/2.0',
        'openid.mode'       => 'checkid_setup',
        'openid.return_to'  => $return_url,
        'openid.realm'      => SITE_URL . '/',
        'openid.identity'   => 'http://specs.openid.net/auth/2.0/identifier_select',
        'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
    ];
    return 'https://steamcommunity.com/openid/login?' . http_build_query($params);
}

function validateSteamLogin(array $params): bool {
    // Normalize: make sure openid.mode is set
    $send = [];
    foreach ($params as $k => $v) {
        // Convert underscores back to dots for openid params
        if (strpos($k, 'openid_') === 0 && strpos($k, 'openid.') === false) {
            $k = str_replace('openid_', 'openid.', $k);
        }
        $send[$k] = $v;
    }
    $send['openid.mode'] = 'check_authentication';

    $postData = http_build_query($send);
    $context  = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-type: application/x-www-form-urlencoded\r\n"
                             . "Content-length: " . strlen($postData) . "\r\n",
            'content'       => $postData,
            'timeout'       => 8,
            'ignore_errors' => true,
        ]
    ]);

    $result = @file_get_contents('https://steamcommunity.com/openid/login', false, $context);
    return $result && strpos($result, 'is_valid:true') !== false;
}

function getSteamId64FromOpenId(string $claimed_id): ?string {
    if (preg_match('#steamcommunity\.com/openid/id/(\d+)#', $claimed_id, $m)) {
        return $m[1];
    }
    // fallback: just grab any 17-digit number
    if (preg_match('/\b(\d{17})\b/', $claimed_id, $m)) {
        return $m[1];
    }
    return null;
}

function fetchSteamUser(string $steamId64): ?array {
    $url  = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/'
          . '?key=' . STEAM_API_KEY . '&steamids=' . $steamId64;
    $ctx  = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
    $json = @file_get_contents($url, false, $ctx);
    if (!$json) return null;
    $data = json_decode($json, true);
    return $data['response']['players'][0] ?? null;
}

function saveOrUpdateUser($pdo, array $steam_data): ?array {
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (steam_id, steam_name, avatar_url, profile_url, country, last_login)
            VALUES (?,?,?,?,?,NOW())
            ON DUPLICATE KEY UPDATE
              steam_name   = VALUES(steam_name),
              avatar_url   = VALUES(avatar_url),
              profile_url  = VALUES(profile_url),
              country      = VALUES(country),
              last_login   = NOW()
        ");
        $stmt->execute([
            $steam_data['steamid'],
            $steam_data['personaname'],
            $steam_data['avatarfull'] ?? $steam_data['avatarmedium'] ?? $steam_data['avatar'],
            $steam_data['profileurl'],
            $steam_data['loccountrycode'] ?? '',
        ]);
        $s = $pdo->prepare("SELECT * FROM users WHERE steam_id=?");
        $s->execute([$steam_data['steamid']]);
        return $s->fetch();
    } catch (Throwable $e) {
        return null;
    }
}


// ── Ban check ─────────────────────────────────────────────────────────────────
function checkBan(PDO $pdo, string $steamId): ?array {
    try {
        $stmt = $pdo->prepare("
            SELECT b.reason, b.banned_until, b.created_at
            FROM user_bans b
            WHERE b.steam_id = ?
              AND b.is_active = 1
              AND (b.banned_until IS NULL OR b.banned_until > NOW())
            ORDER BY b.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$steamId]);
        $ban = $stmt->fetch();
        return $ban ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

function showBanPage(array $ban): never {
    $until  = $ban['banned_until']
        ? 'до ' . date('d.m.Y H:i', strtotime($ban['banned_until']))
        : 'назавжди';
    $reason = htmlspecialchars($ban['reason'] ?: 'Порушення правил сервера');
    http_response_code(403);
    echo <<<HTML
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Акаунт заблоковано</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Unbounded:wght@700;900&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{min-height:100vh;background:#0a0d14;color:#e8ecf4;font-family:'Manrope',sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}
  .ban-card{background:#161b27;border:1px solid rgba(248,113,113,.25);border-radius:16px;padding:48px 40px;max-width:480px;width:100%;text-align:center;box-shadow:0 0 60px rgba(248,113,113,.08)}
  .ban-icon{font-size:52px;margin-bottom:20px;display:block}
  .ban-title{font-family:'Unbounded',sans-serif;font-size:22px;font-weight:900;color:#f87171;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
  .ban-sub{font-size:14px;color:#9aa3b8;line-height:1.6;margin-bottom:28px}
  .ban-detail{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.15);border-radius:10px;padding:16px 20px;margin-bottom:28px;text-align:left}
  .ban-detail-row{display:flex;gap:10px;font-size:13px;margin-bottom:8px;align-items:flex-start}
  .ban-detail-row:last-child{margin-bottom:0}
  .ban-detail-label{color:#5c677d;font-weight:700;text-transform:uppercase;font-size:10px;letter-spacing:1px;padding-top:2px;flex-shrink:0;width:64px}
  .ban-detail-value{color:#e8ecf4;font-weight:500}
  .ban-appeal{font-size:12px;color:#5c677d}
</style>
</head>
<body>
  <div class="ban-card">
    <span class="ban-icon">🚫</span>
    <div class="ban-title">Акаунт заблоковано</div>
    <p class="ban-sub">Твій акаунт було заблоковано адміністрацією CSHunter.</p>
    <div class="ban-detail">
      <div class="ban-detail-row">
        <span class="ban-detail-label">Причина</span>
        <span class="ban-detail-value">{$reason}</span>
      </div>
      <div class="ban-detail-row">
        <span class="ban-detail-label">Термін</span>
        <span class="ban-detail-value">{$until}</span>
      </div>
    </div>
    <p class="ban-appeal">Якщо вважаєш бан помилковим — зв\'яжись з адміністрацією.</p>
  </div>
</body>
</html>
HTML;
    exit;
}

function isLoggedIn(): bool {
    return !empty($_SESSION['user']['steam_id']);
}

function getUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// ── URL helpers ───────────────────────────────────────────────────────────────
function profileUrl(string $steamId, string $tab = ''): string {
    $base = SITE_URL . '/profile/' . $steamId;
    return $tab ? $base . '/' . $tab : $base;
}

function modeUrl(string $slug): string {
    return SITE_URL . '/servers/' . $slug;
}

function skinchangerUrl(string $steamId = ''): string {
    return $steamId
        ? SITE_URL . '/profile/' . $steamId . '/skinchanger'
        : SITE_URL . '/skinchanger';
}

// ── CSRF ───────────────────────────────────────────────────────────────────────
function getCsrfToken(): string {
    // config.php вже генерує токен при старті — просто повертаємо
    // Якщо сесія закрита — $_SESSION досі читається, тільки не пишеться
    if (!empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }
    // Fallback: якщо сесія ще відкрита — генеруємо
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    return '';
}

function verifyCsrfToken(): bool {
    // Accept from header (JS fetch) or POST body (forms)
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['csrf_token']
          ?? '';
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}