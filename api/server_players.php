<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

$ip   = $_GET['ip']   ?? '';
$port = (int)($_GET['port'] ?? 0);

if (!preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip) || $port < 1 || $port > 65535) {
    echo json_encode(['error' => 'invalid']); exit;
}

// Cache 15s per server
$ckey = 'pl2_' . md5($ip . $port);
if (isset($_SESSION[$ckey], $_SESSION[$ckey.'_t']) && time() - $_SESSION[$ckey.'_t'] < 15) {
    echo json_encode($_SESSION[$ckey]); exit;
}

// Step 1: Get server status from Steam API
$filter = 'addr\\' . $ip . ':' . $port;
$url    = 'https://api.steampowered.com/IGameServersService/GetServerList/v1/'
        . '?key=' . STEAM_API_KEY . '&filter=' . urlencode($filter) . '&limit=1';
$ctx    = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
$json   = @file_get_contents($url, false, $ctx);
$srv    = json_decode($json ?? '', true)['response']['servers'][0] ?? null;

$count = (int)($srv['players']     ?? 0);
$max   = (int)($srv['max_players'] ?? 32);
$bots  = (int)($srv['bots']        ?? 0);
$map   = $srv['map']  ?? 'unknown';
$name  = $srv['name'] ?? '';

// Step 2: Get real players from our DB (written by ServerOnline CSSharp plugin)
$players = [];
try {
    // Connect to shared DB where plugin writes data
    $pdo = new PDO(
        "mysql:host=".SHARED_DB_HOST.";dbname=".SHARED_DB_NAME.";charset=utf8mb4",
        SHARED_DB_USER, SHARED_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Check table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'server_online'")->fetchAll();
    if (!empty($tables)) {
        $stmt = $pdo->prepare("
            SELECT so.steam_id, so.steam_name, so.joined_at,
                   u.avatar_url, u.profile_url
            FROM server_online so
            LEFT JOIN cshunter.users u ON u.steam_id = so.steam_id
            WHERE so.server_ip = ? AND so.server_port = ?
            ORDER BY so.joined_at ASC
        ");
        $stmt->execute([$ip, $port]);
        $rows = $stmt->fetchAll();

        // Collect SteamIDs without avatars for batch Steam API lookup
        $needAvatar = [];
        foreach ($rows as $r) {
            if (empty($r['avatar_url'])) $needAvatar[] = $r['steam_id'];
        }

        // Batch fetch avatars from Steam API (max 100 per request)
        $steamProfiles = [];
        if (!empty($needAvatar)) {
            $chunks = array_chunk($needAvatar, 100);
            foreach ($chunks as $chunk) {
                $steamUrl = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/'
                          . '?key=' . STEAM_API_KEY . '&steamids=' . implode(',', $chunk);
                $sCtx  = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
                $sJson = @file_get_contents($steamUrl, false, $sCtx);
                $sData = json_decode($sJson ?? '', true);
                foreach ($sData['response']['players'] ?? [] as $p) {
                    $steamProfiles[$p['steamid']] = [
                        'avatar'      => $p['avatarmedium'] ?? $p['avatar'] ?? null,
                        'profile_url' => $p['profileurl'] ?? null,
                        'steam_name'  => $p['personaname'] ?? null,
                    ];
                }
            }
        }

        foreach ($rows as $r) {
            $sid = $r['steam_id'];
            $players[] = [
                'steam_id'   => $sid,
                'name'       => $steamProfiles[$sid]['steam_name'] ?? $r['steam_name'],
                'avatar'     => $r['avatar_url'] ?? $steamProfiles[$sid]['avatar'] ?? null,
                'profile_url'=> $r['profile_url'] ?? $steamProfiles[$sid]['profile_url'] ?? null,
                'joined_at'  => $r['joined_at'],
            ];
        }
    }
} catch (Throwable $e) {
    // Plugin not installed — fallback to A2S_PLAYER UDP query
    $players = [];
}

// If plugin gave nothing — try A2S_PLAYER directly
if (empty($players)) {
    require_once __DIR__ . '/../includes/sourcequery.php';
    $sq = new SourceQuery(2.5);
    $a2sPlayers = $sq->queryPlayers($ip, $port);
    if (is_array($a2sPlayers)) {
        foreach ($a2sPlayers as $p) {
            if ($p['name'] === '' || $p['name'] === 'GOTV') continue; // skip bots/GOTV
            $players[] = [
                'name'     => $p['name'],
                'avatar'   => null,
                'score'    => $p['score'],
                'duration' => $p['duration'],
                'source'   => 'a2s', // flag so frontend knows no avatar
            ];
        }
    }
}

$result = [
    'count'   => $count,
    'max'     => $max,
    'map'     => $map,
    'name'    => $name,
    'bots'    => $bots,
    'players' => $players,
    'plugin_active' => !empty($players) || isset($tables),
];

$_SESSION[$ckey]        = $result;
$_SESSION[$ckey . '_t'] = time();

echo json_encode($result);
