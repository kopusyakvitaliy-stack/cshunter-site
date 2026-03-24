<?php
/**
 * Returns player list for a server.
 * Steam Web API only gives count, not names.
 * For now returns server info + placeholder players.
 * When CS2 plugin (SimpleAdmin/GameCMS) is installed — extend this.
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');


$ip   = $_GET['ip']   ?? '';
$port = (int)($_GET['port'] ?? 0);

if (!preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip) || $port < 1 || $port > 65535) {
    echo json_encode(['error' => 'invalid']); exit;
}

// Cache 20s
$ckey = 'pl_' . md5($ip.$port);
if (isset($_SESSION[$ckey], $_SESSION[$ckey.'_t']) && time() - $_SESSION[$ckey.'_t'] < 20) {
    echo json_encode($_SESSION[$ckey]); exit;
}

// Fetch server info via Steam API
$filter  = 'addr\\' . $ip . ':' . $port;
$url     = 'https://api.steampowered.com/IGameServersService/GetServerList/v1/'
         . '?key=' . STEAM_API_KEY
         . '&filter=' . urlencode($filter)
         . '&limit=1';

$ctx     = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$json    = @file_get_contents($url, false, $ctx);
$servers = [];
if ($json) {
    $d = json_decode($json, true);
    $servers = $d['response']['servers'] ?? [];
}

if (empty($servers)) {
    $result = ['online' => false, 'players' => 0, 'max_players' => 32, 'map' => 'Офлайн', 'name' => '', 'player_list' => []];
} else {
    $srv = $servers[0];
    $result = [
        'online'      => true,
        'players'     => (int)($srv['players'] ?? 0),
        'max_players' => (int)($srv['max_players'] ?? 32),
        'map'         => $srv['map'] ?? '',
        'name'        => $srv['name'] ?? '',
        'bots'        => (int)($srv['bots'] ?? 0),
        'player_list' => [], // Will be populated by plugin later
        'plugin_required' => true, // flag for frontend
    ];
}

$_SESSION[$ckey]        = $result;
$_SESSION[$ckey . '_t'] = time();

echo json_encode($result);
