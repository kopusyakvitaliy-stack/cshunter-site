<?php
/**
 * POST /api/items_equip.php
 * Екіпірувати або зняти предмет.
 *
 * Body (JSON):
 *   { "item_id": 5, "action": "equip", "csrf": "..." }
 *   { "item_type": "frame", "action": "unequip", "csrf": "..." }
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/items.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'not_logged_in']);
    exit;
}

$rawBody  = file_get_contents('php://input');
$body     = json_decode($rawBody, true) ?? [];

// CSRF: приймаємо і з body, і з header
$csrfBody = $body['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$csrfSes  = $_SESSION['csrf_token'] ?? '';

if (empty($csrfSes) || !hash_equals($csrfSes, $csrfBody)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid_csrf']);
    exit;
}

$me = getUser();
if (!$me || !$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM users WHERE steam_id = ?");
$stmt->execute([$me['steam_id']]);
$userId = (int)$stmt->fetchColumn();

if (!$userId) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'user_not_found']);
    exit;
}

$action = $body['action'] ?? '';

if ($action === 'equip') {
    $itemId = (int)($body['item_id'] ?? 0);
    if ($itemId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'invalid_item_id']);
        exit;
    }

    $result = ItemService::equipItem($pdo, $userId, $itemId);
    if ($result === true) {
        $equipped = ItemService::getUserEquipped($pdo, $userId);
        $frame    = $equipped['frame'] ?? null;
        echo json_encode([
            'ok'      => true,
            'message' => 'Екіпіровано',
            'frame'   => $frame ? [
                'image_lg'     => $frame['image_lg'],
                'rarity'       => $frame['rarity'],
                'rarity_color' => ItemService::getRarityColor($frame['rarity']),
                'animated'     => (bool)$frame['animated'],
                'avatar_shape' => $frame['avatar_shape'] ?? 'rounded',
            ] : null,
        ]);
    } else {
        $msgs = ['not_owned' => 'Предмет не знайдено в інвентарі', 'db_error' => 'Помилка бази даних'];
        echo json_encode(['ok' => false, 'error' => $result, 'message' => $msgs[$result] ?? 'Помилка']);
    }

} elseif ($action === 'unequip') {
    $itemType = $body['item_type'] ?? '';
    $allowed  = ['frame', 'background', 'badge', 'card_style'];
    if (!in_array($itemType, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'invalid_type']);
        exit;
    }
    ItemService::unequipItem($pdo, $userId, $itemType);
    echo json_encode(['ok' => true, 'message' => 'Знято']);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_action']);
}
