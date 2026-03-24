<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/players_cs.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'search') {
    $q      = trim($_GET['q'] ?? '');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit  = 30;

    if (!$pdo) { echo json_encode(['error'=>'no_db','players'=>[],'has_more'=>false]); exit; }

    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM cs_players")->fetchColumn();
    } catch (Throwable $e) {
        echo json_encode(['error'=>$e->getMessage(),'players'=>[],'has_more'=>false]); exit;
    }

    if ($count < 100) {
        $syncOk = syncPlayersFromPandaScore($pdo);
        if (!$syncOk) {
            // Debug: test API directly
            $testUrl = "https://api.pandascore.co/players?filter[videogame]=cs-go&per_page=1&page=1";
            $ctx = stream_context_create(['http' => [
                'header' => "Authorization: Bearer " . PANDASCORE_KEY . "\r\nAccept: application/json\r\n",
                'timeout' => 8, 'ignore_errors' => true,
            ]]);
            $raw = @file_get_contents($testUrl, false, $ctx);
            $decoded = $raw ? json_decode($raw, true) : null;
            echo json_encode([
                'debug' => true,
                'sync_ok' => $syncOk,
                'api_raw_length' => strlen($raw ?? ''),
                'api_first_item' => $decoded[0] ?? $decoded ?? 'null',
                'players' => [], 'has_more' => false, 'offset' => 0
            ]);
            exit;
        }
    }

    $players = strlen($q) > 0
        ? searchCsPlayers($pdo, $q, $limit, $offset)
        : getTopCsPlayers($pdo, $limit, $offset);

    $result = array_map(function($p) {
        return [
            'id'          => (int)$p['id'],
            'name'        => $p['name'],
            'real_name'   => $p['real_name'] ?? null,
            'nationality' => $p['nationality'] ?? null,
            'team'        => $p['team_name'] ?? null,
            'photo'       => buildPlayerLogoUrl($p),
        ];
    }, $players);

    echo json_encode(['players'=>$result,'has_more'=>count($players)===$limit,'offset'=>$offset+count($players)]);
    exit;
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) { echo json_encode(['error'=>'auth']); exit; }
    if (!verifyCsrfToken()) { http_response_code(403); echo json_encode(['error'=>'csrf']); exit; }

    $playerId = (int)($_POST['player_id'] ?? 0);
    $steamId  = getUser()['steam_id'] ?? '';

    if ($playerId === 0) {
        removeFavPlayer($pdo, $steamId);
        $_SESSION['user']['fav_player'] = null;
        echo json_encode(['ok'=>true,'removed'=>true]);
        exit;
    }

    $player = getCsPlayerById($pdo, $playerId);
    if (!$player) { echo json_encode(['error'=>'not_found']); exit; }

    $ok = setFavPlayer($pdo, $steamId, $playerId);
    if ($ok) {
        $_SESSION['user']['fav_player'] = [
            'id'    => (int)$player['id'],
            'name'  => $player['name'],
            'team'  => $player['team_name'] ?? null,
            'photo' => buildPlayerLogoUrl($player),
        ];
    }

    echo json_encode(['ok'=>$ok,'player'=>$_SESSION['user']['fav_player']??null]);
    exit;
}

echo json_encode(['error'=>'unknown_action']);
