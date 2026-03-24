<?php
require_once __DIR__ . '/../config.php';

$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    $pdo = null;
}

// ── Helpers ───────────────────────────────────────────────────

function getModes($pdo) {
    // Always use local config as source of truth
    return getDemoModes();
}

function getMode($pdo, $slug) {
    // Always check local config first — it's the source of truth
    foreach (getDemoModes() as $m) {
        if ($m['slug'] === $slug) return $m;
    }
    // Fallback to DB only if somehow not found locally
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM modes WHERE slug=? AND active=1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        if ($row) return $row;
    }
    return null;
}

function getServers($pdo, $mode_id) {
    // static — завантажується один раз, не потребує global
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../servers_config.php';
    }

    $slug = null;
    foreach (getDemoModes() as $m) {
        if ($m['id'] == $mode_id) { $slug = $m['slug']; break; }
    }
    if (!$slug) return [];

    $configured = $config[$slug] ?? [];
    if (empty($configured)) return [];

    $result = [];
    foreach ($configured as $i => $s) {
        $result[] = [
            'id'             => $mode_id * 100 + $i,
            'mode_id'        => $mode_id,
            'name'           => $s['name'],
            'ip'             => $s['ip'],
            'port'           => (int)$s['port'],
            'map'            => '...',
            'players_online' => 0,
            'players_max'    => 0,
            'status'         => 1,
            'tags'           => $s['tags'] ?? '',
        ];
    }
    return $result;
}

function getTopPlayers($pdo, $mode_id, $limit = 10) {
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT u.steam_name, u.avatar_url, s.score, s.playtime_hours
            FROM stats s JOIN users u ON s.user_id = u.id
            WHERE s.mode_id = ? ORDER BY s.score DESC LIMIT ?
        ");
        $stmt->execute([$mode_id, $limit]);
        return $stmt->fetchAll();
    }
    return []; // no fake top players — show "no stats yet"
}

// ── Mode definitions ───────────────────────────────────────────
function getDemoModes() {
    return [
        ['id'=>1,'slug'=>'surf',       'name'=>'Surf',       'tag'=>'Popular', 'description'=>'Зліт по хвилях швидкості — відчуй адреналін серфінгу на CS2 серверах з унікальними картами та власним рейтингом.',   'rules'=>['Жодного гріфінгу та образ','Чит-програми суворо заборонені','Поважай інших гравців','Не стрибай з початкової точки','Таймер зупиняється на забороненій зоні'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#0a1628','color_to'=>'#1565c0','accent'=>'#42a5f5'],
        ['id'=>2,'slug'=>'deathmatch', 'name'=>'Deathmatch', 'tag'=>'TOP',     'description'=>'Нескінченний бій де вмирати — не соромно. Тренуй аім, рефлекси та читай позиції противника.',                       'rules'=>['Без образ та токсичності','Заборонено гучний мікрофон','Не блокуй гравців','Камперство заборонено','Забороняється скаржитись на аім :)'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#1a0505','color_to'=>'#b71c1c','accent'=>'#ef5350'],
        ['id'=>3,'slug'=>'1v1',        'name'=>'1v1 Arena',  'tag'=>'Skill',   'description'=>'Доведи що ти найкращий у прямій дуелі. Чесний бій, рівні умови, тільки аім вирішує переможця.',                       'rules'=>['Повага до противника','Один раунд — одне зброя','Pause тільки на тех. проблеми','Заборонено гучні звукові ефекти','Результати автоматично записуються в рейтинг'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#0d0621','color_to'=>'#7b1fa2','accent'=>'#ce93d8'],
        ['id'=>4,'slug'=>'kz',         'name'=>'KZ Climb',   'tag'=>'Hardcore','description'=>'Екстремальний паркур по вертикальних картах. Тільки майстри досягають вершини — чи є ти одним із них?',               'rules'=>['Без телепортів на заборонені місця','Bunnyhop тільки за дозволом карти','Не заважай іншим гравцям','Рекорди зберігаються автоматично','Читерські скрипти = бан'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#0a1a0a','color_to'=>'#2e7d32','accent'=>'#66bb6a'],
        ['id'=>5,'slug'=>'bhop',       'name'=>'Bhop',       'tag'=>'Speed',   'description'=>'Стрибай без зупинки, набирай швидкість до нелюдських значень. Найшвидший гравець отримує безсмертя в таблиці рекордів.','rules'=>['Заборонені автобхоп скрипти','Тільки ручний бхоп','Гучні звуки заборонені','Повага до рекордів інших','Не блокуй старт'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#001a20','color_to'=>'#00695c','accent'=>'#26c6da'],
        ['id'=>6,'slug'=>'rab',         'name'=>'RAB',        'tag'=>'Fun',     'description'=>'Рандомні ефекти на кожному раунді — gravity, speed, invisibility та десятки інших. Жоден раунд не схожий на попередній.','rules'=>['Рандом вирішує всe','Не скаржся на ефекти :)','Командна гра допомагає','Чити суворо заборонені','Отримуй задоволення!'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#1a0e00','color_to'=>'#e65100','accent'=>'#ffa726'],
        ['id'=>7,'slug'=>'duels',      'name'=>'Duels',      'tag'=>'Skill',   'description'=>'Чесна дуель 1 на 1 — рівні умови, одна зброя, чистий аім. Тільки твої рефлекси і точність вирішують переможця.','rules'=>['Повага до суперника','Одна зброя за раунд','Без образ','Пауза тільки на технічні проблеми','Результати йдуть у рейтинг'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#0d0621','color_to'=>'#4a1970','accent'=>'#ce93d8'],
        ['id'=>8,'slug'=>'old_maps',   'name'=>'2x2 Old Maps','tag'=>'Nostalgia','description'=>'Скучив за легендарними картами, яких вже немає в CS2? Rialto, Lake, Canals, Agency — вони живуть тут. Зіграй на класиці з другом у форматі 2 на 2 і відчуй дух старого CS:GO.','rules'=>['Командна гра 2 на 2','Повага до суперника','Без агресії','Чити суворо заборонені','Насолоджуйся класикою!'],'servers_count'=>0,'total_online'=>0,'color_from'=>'#1a1008','color_to'=>'#5d4037','accent'=>'#ffca28'],
    ];
}
