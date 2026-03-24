<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
session_write_close();

$ip   = $_GET['ip']   ?? '';
$port = (int)($_GET['port'] ?? 0);

if (!preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $ip) || $port < 1 || $port > 65535) {
    echo json_encode(['error' => 'invalid']); exit;
}

if (!$pdo) {
    echo json_encode(['online' => false, 'players' => 0, 'max_players' => 0, 'map' => '', 'ping' => null]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT online, players, max_players, map, ping, updated_at
    FROM server_status_cache
    WHERE ip=? AND port=?
    LIMIT 1
");
$stmt->execute([$ip, $port]);
$row = $stmt->fetch();

if ($row) {
    // Якщо дані старіші 10 хвилин — крон не відпрацював, вважаємо офлайн
    $stale = strtotime($row['updated_at']) < (time() - 600);
    $online = !$stale && (bool)$row['online'];

    echo json_encode([
        'online'      => $online,
        'players'     => $online ? (int)$row['players'] : 0,
        'max_players' => (int)$row['max_players'],
        'map'         => $row['map'],
        'ping'        => $row['ping'] !== null ? (int)$row['ping'] : null,
        'stale'       => $stale,
    ]);
} else {
    echo json_encode(['online' => false, 'players' => 0, 'max_players' => 0, 'map' => '', 'ping' => null, 'stale' => false]);
}
