<?php
/**
 * Крон: опитує всі ігрові сервери, пише в БД і генерує /cache/servers.json
 * Запускати: * * * * * php /path/to/cron/update_servers.php >> /logs/cron.log 2>&1
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sourcequery.php';

if (!$pdo) { echo "[ERROR] No DB connection\n"; exit(1); }

// ── Збираємо унікальні сервери з конфігу ─────────────────────────────────────
$serversConfig = require __DIR__ . '/../servers_config.php';

$unique = [];
foreach ($serversConfig as $slug => $servers) {
    foreach ($servers as $srv) {
        $key = $srv['ip'] . ':' . $srv['port'];
        if (!isset($unique[$key])) {
            $unique[$key] = ['ip' => $srv['ip'], 'port' => (int)$srv['port']];
        }
    }
}

// ── Опитуємо кожен сервер ────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO server_status_cache (ip, port, online, players, max_players, map, ping)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        online      = VALUES(online),
        players     = VALUES(players),
        max_players = VALUES(max_players),
        map         = VALUES(map),
        ping        = VALUES(ping),
        updated_at  = NOW()
");

$cacheData   = [];
$totalOnline = 0;

foreach ($unique as $key => $srv) {
    $r = queryServer($srv['ip'], $srv['port']);

    $stmt->execute([
        $srv['ip'],
        $srv['port'],
        $r['online'] ? 1 : 0,
        $r['players']     ?? 0,
        $r['max_players'] ?? 0,
        $r['map']         ?? '',
        $r['ping']        ?? null,
    ]);

    if ($r['online']) $totalOnline += (int)($r['players'] ?? 0);

    $cacheData[$key] = [
        'online'      => $r['online'],
        'players'     => (int)($r['players'] ?? 0),
        'max_players' => (int)($r['max_players'] ?? 0),
        'map'         => $r['map'] ?? '',
        'ping'        => isset($r['ping']) ? (int)$r['ping'] : null,
    ];

    $status  = $r['online'] ? "OK {$r['players']}/{$r['max_players']} map:{$r['map']}" : 'offline';
    $pingStr = isset($r['ping']) ? $r['ping'] . 'ms' : '—';
    echo "[{$key}] {$status} ping:{$pingStr}\n";
}

// ── Записуємо JSON-кеш атомарно ──────────────────────────────────────────────
// tmp + rename() гарантує що читачі не отримають половину файлу
$cacheFile = __DIR__ . '/../cache/servers.json';
$tmpFile   = $cacheFile . '.tmp';

$json = json_encode([
    'updated_at'   => time(),
    'total_online' => $totalOnline,
    'servers'      => $cacheData,
], JSON_UNESCAPED_UNICODE);

file_put_contents($tmpFile, $json, LOCK_EX);
rename($tmpFile, $cacheFile);

echo "Done " . date('H:i:s') . " | total online: {$totalOnline}\n";

// ── Функції опитування ────────────────────────────────────────────────────────

function queryServer(string $ip, int $port): array {
    // UDP першим — точніший лічильник (Steam API рахує GOTV/слоти)
    $udp = queryDirectUDP($ip, $port);
    if ($udp['online']) return $udp;
    // Steam API як fallback якщо UDP недоступний
    return queryViaSteamAPI($ip, $port);
}

function queryViaSteamAPI(string $ip, int $port): array {
    $key = defined('STEAM_API_KEY') ? STEAM_API_KEY : '';
    if (!$key) return ['online' => false];
    $url = "https://api.steampowered.com/IGameServersService/GetServerList/v1/?key={$key}&filter=addr\\{$ip}:{$port}&limit=1";
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $t0  = microtime(true);
    $json = @file_get_contents($url, false, $ctx);
    $ping = (int)round((microtime(true) - $t0) * 1000);
    if (!$json) return ['online' => false];
    $data = json_decode($json, true);
    $s    = $data['response']['servers'][0] ?? null;
    if (!$s) return ['online' => false];
    return [
        'online'      => true,
        'players'     => (int)$s['players'],
        'max_players' => (int)$s['max_players'],
        'map'         => $s['map'] ?? '',
        'ping'        => $ping,
    ];
}

function queryDirectUDP(string $ip, int $port): array {
    try {
        $t0  = microtime(true);
        $sq  = new SourceQuery(1.5);
        $r   = $sq->query($ip, $port);
        $ping = (int)round((microtime(true) - $t0) * 1000);
        if (isset($r['online']) && $r['online']) $r['ping'] = $ping;
        return $r;
    } catch (Throwable $e) {
        return ['online' => false, 'players' => 0, 'max_players' => 0, 'map' => ''];
    }
}
