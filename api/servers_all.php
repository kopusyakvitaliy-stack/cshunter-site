<?php
/**
 * GET /api/servers_all.php
 * Повертає статуси всіх серверів одним запитом.
 * Читає з JSON-кешу — без SQL, миттєво.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30'); // браузер не запитуватиме частіше ніж раз на 30с
session_write_close();

$cacheFile = __DIR__ . '/../cache/servers.json';

if (!file_exists($cacheFile)) {
    echo json_encode(['ok' => false, 'error' => 'cache_missing', 'servers' => [], 'total_online' => 0, 'updated_at' => 0]);
    exit;
}

$mtime = filemtime($cacheFile);

// Якщо кеш старіший 10 хвилин — крон не запускався, попереджаємо клієнт
$stale = (time() - $mtime) > 600;

$raw = @file_get_contents($cacheFile);
if (!$raw) {
    echo json_encode(['ok' => false, 'error' => 'cache_unreadable', 'servers' => [], 'total_online' => 0, 'updated_at' => 0]);
    exit;
}

$data = json_decode($raw, true);
if (!$data) {
    echo json_encode(['ok' => false, 'error' => 'cache_invalid', 'servers' => [], 'total_online' => 0, 'updated_at' => 0]);
    exit;
}

// Якщо кеш застарів — всі сервери вважаємо офлайн
if ($stale) {
    foreach ($data['servers'] as $key => &$srv) {
        $srv['online']  = false;
        $srv['players'] = 0;
    }
    unset($srv);
    $data['total_online'] = 0;
    $data['stale']        = true;
}

$data['ok']    = true;
$data['stale'] = $stale;

echo json_encode($data);
