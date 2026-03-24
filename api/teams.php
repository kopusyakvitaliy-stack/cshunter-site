<?php
/**
 * Teams API — search, sync, save favourite
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/teams.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Sync (called once/day, can be triggered manually) ───────────────────────
if ($action === 'sync') {
    if (!isLoggedIn()) { echo json_encode(['error' => 'auth']); exit; }
    $ok = syncTeamsFromPandaScore($pdo);
    echo json_encode(['ok' => $ok]);
    exit;
}

// ── Search ───────────────────────────────────────────────────────────────────
if ($action === 'search') {
    $q      = trim($_GET['q'] ?? '');
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $limit  = 30;

    if (!$pdo) { echo json_encode(['error' => 'no_db', 'teams' => [], 'has_more' => false]); exit; }

    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
    } catch (Throwable $e) {
        echo json_encode(['error' => 'db_error: ' . $e->getMessage(), 'teams' => [], 'has_more' => false]); exit;
    }

    if ($count === 0) {
        $syncResult = syncTeamsFromPandaScore($pdo);
        if (!$syncResult) {
            echo json_encode(['error' => 'sync_failed', 'teams' => [], 'has_more' => false]); exit;
        }
    }

    if (strlen($q) < 1) {
        $teams = getTopTeams($pdo, $limit, $offset);
    } else {
        $teams = searchTeams($pdo, $q, $limit, $offset);
    }

    // Build response
    $result = array_map(function($t) {
        $logo = null;
        if (!empty($t['logo_local']) && file_exists(__DIR__ . '/../' . $t['logo_local'])) {
            $logo = SITE_URL . '/' . $t['logo_local'];
        } elseif (!empty($t['logo_url'])) {
            $logo = $t['logo_url'];
        }
        return [
            'id'   => (int)$t['id'],
            'name' => $t['name'],
            'slug' => $t['slug'] ?? '',
            'logo' => $logo,
        ];
    }, $teams);

    echo json_encode([
        'teams'    => $result,
        'has_more' => count($teams) === $limit,
        'offset'   => $offset + count($teams),
    ]);
    exit;
}

// ── Save favourite ───────────────────────────────────────────────────────────
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) { echo json_encode(['error' => 'auth']); exit; }
    if (!verifyCsrfToken()) { http_response_code(403); echo json_encode(['error' => 'csrf']); exit; }

    $teamId  = (int)($_POST['team_id'] ?? 0);
    $steamId = getUser()['steam_id'] ?? '';

    if ($teamId === 0) {
        // Remove
        $ok = removeFavTeam($pdo, $steamId);
        echo json_encode(['ok' => $ok, 'removed' => true]);
        exit;
    }

    $team = getTeamById($pdo, $teamId);
    if (!$team) { echo json_encode(['error' => 'team_not_found']); exit; }

    $ok = setFavTeam($pdo, $steamId, $teamId);

    // Update session
    if ($ok) {
        $logo = null;
        if (!empty($team['logo_local']) && file_exists(__DIR__ . '/../' . $team['logo_local'])) {
            $logo = SITE_URL . '/' . $team['logo_local'];
        } elseif (!empty($team['logo_url'])) {
            $logo = $team['logo_url'];
        }
        // Always keep logo_url as fallback
        $_SESSION['user']['fav_team'] = [
            'id'   => (int)$team['id'],
            'name' => $team['name'],
            'logo' => $logo,
        ];
    }

    echo json_encode(['ok' => $ok, 'team' => $_SESSION['user']['fav_team'] ?? null]);
    exit;
}

echo json_encode(['error' => 'unknown_action']);
