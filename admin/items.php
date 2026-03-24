<?php
require_once __DIR__ . '/_middleware.php';
require_once __DIR__ . '/_layout.php';
require_once __DIR__ . '/../includes/items.php';
$admin = requireAdmin();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$msg   = '';
$error = '';
$activeAdminTab = $_GET['view'] ?? 'catalog';

// Список доступних зображень з assets/items/
$availableImages = ItemService::getAvailableImages(__DIR__ . '/..');

// ── Handle POST actions ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── Grant item to user ──────────────────────────────────────────────────────
    if ($action === 'grant_item') {
        $steamId = trim($_POST['steam_id'] ?? '');
        $itemId  = (int)($_POST['item_id'] ?? 0);
        if (!preg_match('/^\d{17}$/', $steamId)) {
            $error = 'Невірний Steam ID (має бути 17 цифр)';
        } elseif ($itemId <= 0) {
            $error = 'Оберіть предмет';
        } else {
            $stmt = $pdo->prepare("SELECT id, steam_name FROM users WHERE steam_id = ? AND has_logged_in = 1");
            $stmt->execute([$steamId]);
            $targetUser = $stmt->fetch();
            if (!$targetUser) {
                $error = 'Гравець не знайдений або ніколи не заходив на сайт';
            } else {
                $granted = ItemService::grantItem($pdo, (int)$targetUser['id'], $itemId, 'admin');
                if ($granted) {
                    $itemStmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
                    $itemStmt->execute([$itemId]);
                    $itemName = $itemStmt->fetchColumn();
                    adminLog('grant_item', $steamId, ['item_id' => $itemId, 'item_name' => $itemName, 'to_user' => $targetUser['steam_name']]);
                    $msg = 'Предмет "' . h($itemName) . '" видано гравцю ' . h($targetUser['steam_name']);
                } else {
                    $error = 'Предмет вже є в інвентарі цього гравця';
                }
            }
        }
        $activeAdminTab = 'grant';
    }

    // ── Revoke item ─────────────────────────────────────────────────────────────
    elseif ($action === 'revoke_item') {
        $steamId = trim($_POST['steam_id'] ?? '');
        $itemId  = (int)($_POST['item_id'] ?? 0);
        if (preg_match('/^\d{17}$/', $steamId)) {
            $stmt = $pdo->prepare("SELECT id, steam_name FROM users WHERE steam_id = ?");
            $stmt->execute([$steamId]);
            $targetUser = $stmt->fetch();
            if ($targetUser) {
                ItemService::revokeItem($pdo, (int)$targetUser['id'], $itemId);
                adminLog('revoke_item', $steamId, ['item_id' => $itemId]);
                $msg = 'Предмет забрано у гравця ' . h($targetUser['steam_name']);
            }
        }
        $activeAdminTab = 'inventories';
    }

    // ── Add item ────────────────────────────────────────────────────────────────
    elseif ($action === 'add_item') {
        $slug        = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $name        = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
        $type        = $_POST['type'] ?? 'frame';
        $rarity      = $_POST['rarity'] ?? 'consumer';
        $imageLg     = trim($_POST['image_lg'] ?? '');
        $description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
        $avatarShape = in_array($_POST['avatar_shape'] ?? '', ['rounded','square']) ? $_POST['avatar_shape'] : 'rounded';
        $animated    = (int)isset($_POST['animated']);
        $hidden      = (int)isset($_POST['hidden']);
        $sortOrder   = (int)($_POST['sort_order'] ?? 0);
        $condType    = $_POST['condition_type'] ?? 'manual';
        $condValue   = (int)($_POST['condition_value'] ?? 0);

        $validTypes    = ['frame','background','badge','card_style'];
        $validRarities = ['consumer','industrial','milspec','restricted','classified','covert'];
        $validConds    = ['registration_days','playtime_hours','kills','faceit_level','manual'];

        if (empty($slug) || empty($name)) {
            $error = 'Slug та назва — обовʼязкові поля';
        } elseif (!in_array($type, $validTypes)) {
            $error = 'Невірний тип предмету';
        } elseif (!in_array($rarity, $validRarities)) {
            $error = 'Невірна рідкість';
        } else {
            try {
                $pdo->prepare("
                    INSERT INTO items (type, slug, name, rarity, image_lg, description, avatar_shape, animated, hidden, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$type, $slug, $name, $rarity, $imageLg, $description, $avatarShape, $animated, $hidden, $sortOrder]);
                $newItemId = (int)$pdo->lastInsertId();
                if ($condType !== 'manual' || $condValue > 0) {
                    $pdo->prepare("INSERT INTO item_conditions (item_id, condition_type, condition_value) VALUES (?,?,?)")
                        ->execute([$newItemId, $condType, $condValue]);
                }
                adminLog('add_item', $slug, ['name' => $name, 'rarity' => $rarity, 'type' => $type]);
                if ($type === 'frame' && !empty($imageLg)) {
                    ItemService::generateFrameSm(__DIR__ . '/..', $imageLg);
                }
                $msg = 'Предмет "' . h($name) . '" додано до каталогу';
                $availableImages = ItemService::getAvailableImages(__DIR__ . '/..');
            } catch (Throwable $e) {
                $error = 'Помилка: ' . $e->getMessage();
            }
        }
        $activeAdminTab = 'catalog';
    }

    // ── Edit item ───────────────────────────────────────────────────────────────
    elseif ($action === 'edit_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $ok = ItemService::updateItem($pdo, $itemId, [
                'name'            => mb_substr(trim($_POST['name'] ?? ''), 0, 100),
                'rarity'          => $_POST['rarity'] ?? 'consumer',
                'image_lg'        => trim($_POST['image_lg'] ?? ''),
                'description'     => mb_substr(trim($_POST['description'] ?? ''), 0, 500),
                'avatar_shape'    => in_array($_POST['avatar_shape'] ?? '', ['rounded','square']) ? $_POST['avatar_shape'] : 'rounded',
                'animated'        => (int)isset($_POST['animated']),
                'hidden'          => (int)isset($_POST['hidden']),
                'sort_order'      => (int)($_POST['sort_order'] ?? 0),
                'condition_type'  => $_POST['condition_type'] ?? 'manual',
                'condition_value' => (int)($_POST['condition_value'] ?? 0),
            ]);
            if ($ok) {
                $editedItem = ItemService::getAllItems($pdo)[0] ?? null; // just to get type
                $editType = $_POST['type'] ?? '';
                $editImg  = trim($_POST['image_lg'] ?? '');
                if ($editType === 'frame' && !empty($editImg)) {
                    ItemService::generateFrameSm(__DIR__ . '/..', $editImg);
                }
                adminLog('edit_item', "id:{$itemId}");
                $msg = 'Предмет оновлено';
            } else {
                $error = 'Помилка при збереженні';
            }
        }
        $activeAdminTab = 'catalog';
    }

    // ── Delete item ─────────────────────────────────────────────────────────────
    elseif ($action === 'delete_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $pdo->prepare("SELECT name FROM items WHERE id = ?");
            $stmt->execute([$itemId]);
            $itemName = $stmt->fetchColumn();
            $pdo->prepare("DELETE FROM items WHERE id = ?")->execute([$itemId]);
            adminLog('delete_item', $itemName ?: "id:$itemId");
            $msg = 'Предмет видалено';
        }
        $activeAdminTab = 'catalog';
    }
}

// ── Load data ──────────────────────────────────────────────────────────────────
$allItems = ItemService::getAllItems($pdo);

$invSearch = trim($_GET['inv_search'] ?? '');
$invUsers  = [];
$invItems  = [];
if ($activeAdminTab === 'inventories' && $invSearch !== '') {
    if (preg_match('/^\d{17}$/', $invSearch)) {
        $stmt = $pdo->prepare("SELECT id, steam_id, steam_name, avatar_url FROM users WHERE steam_id = ?");
        $stmt->execute([$invSearch]);
    } else {
        $stmt = $pdo->prepare("SELECT id, steam_id, steam_name, avatar_url FROM users WHERE steam_name LIKE ? LIMIT 10");
        $stmt->execute(['%' . $invSearch . '%']);
    }
    $invUsers = $stmt->fetchAll();
    if (count($invUsers) === 1) {
        $invItems = ItemService::getUserInventory($pdo, (int)$invUsers[0]['id']);
    }
}

$totalItems     = count($allItems);
$totalUserItems = 0;
try { $totalUserItems = (int)$pdo->query("SELECT COUNT(*) FROM user_items")->fetchColumn(); } catch (Throwable) {}

$recentGrants = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_log WHERE action IN ('grant_item','revoke_item','add_item','edit_item','delete_item') ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $recentGrants = $stmt->fetchAll();
} catch (Throwable) {}

// Групуємо предмети по типу для зручності
$itemsByType = [];
foreach ($allItems as $item) { $itemsByType[$item['type']][] = $item; }

adminLayoutStart('Предмети', 'items');
?>

<style>
/* ── Items Admin v2 ─────────────────────────────────────────────────────── */
.itm-tabs { display:flex; gap:4px; margin-bottom:24px; flex-wrap:wrap; }
.itm-tab {
    display:inline-flex; align-items:center; gap:7px;
    padding:8px 16px; border-radius:8px; font-size:13px; font-weight:600;
    cursor:pointer; border:1px solid var(--border); color:var(--text-2);
    background:var(--surface-2); text-decoration:none; transition:all .15s;
}
.itm-tab:hover { border-color:var(--border-2); color:var(--text-1); }
.itm-tab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* Каталог — картки */
.itm-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:14px; }
.itm-card {
    background:var(--surface-2); border:1px solid var(--border); border-radius:12px;
    overflow:hidden; transition:border-color .15s;
}
.itm-card:hover { border-color:var(--border-2); }
.itm-card-head {
    display:flex; align-items:center; gap:12px; padding:12px 14px;
    border-bottom:1px solid var(--border); position:relative;
}
.itm-card-img {
    position:relative; width:54px; height:54px; flex-shrink:0;
    background:var(--bg); border-radius:8px; overflow:hidden;
    display:flex; align-items:center; justify-content:center;
}
.itm-card-img img { width:100%; height:100%; object-fit:contain; }
.itm-card-img-ph { font-size:22px; opacity:.2; }
.itm-card-meta { flex:1; min-width:0; }
.itm-card-name { font-weight:700; font-size:14px; color:var(--text-1); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.itm-card-slug { font-size:11px; color:var(--text-3); font-family:var(--mono); margin-top:2px; }
.itm-card-badges { display:flex; gap:5px; margin-top:5px; flex-wrap:wrap; }

.itm-rarity-dot {
    width:8px; height:8px; border-radius:50%; flex-shrink:0; display:inline-block;
}
.itm-badge {
    display:inline-flex; align-items:center; gap:4px; padding:2px 7px;
    border-radius:4px; font-size:10px; font-weight:700; border:1px solid;
    font-family:var(--mono);
}

/* Edit panel (hidden/shown via JS) */
.itm-edit-panel {
    background:var(--bg); border-top:1px solid var(--border);
    padding:14px; display:none;
    animation:fadeInDown .18s ease;
}
.itm-edit-panel.open { display:block; }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
.itm-edit-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.itm-edit-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }

/* Image preview in select */
.itm-img-preview {
    width:48px; height:48px; object-fit:contain; border-radius:6px;
    background:rgba(0,0,0,.3); border:1px solid var(--border);
    display:none;
}
.itm-img-preview.visible { display:block; }

/* Action buttons in card */
.itm-card-actions {
    display:flex; align-items:center; gap:6px; padding:10px 14px;
    border-top:1px solid var(--border); background:rgba(0,0,0,.1);
}

/* Grant form */
.itm-grant-card { background:var(--surface-2); border:1px solid var(--border); border-radius:12px; padding:20px; margin-bottom:20px; }
.itm-grant-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:700px){ .itm-grant-grid { grid-template-columns:1fr; } }

/* Inventory table */
.itm-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.itm-tbl th { text-align:left; padding:9px 12px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-3); border-bottom:1px solid var(--border); white-space:nowrap; }
.itm-tbl td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
.itm-tbl tr:last-child td { border-bottom:none; }
.itm-tbl tr:hover td { background:rgba(255,255,255,.02); }

/* Add item form */
.itm-add-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
@media(max-width:900px){ .itm-add-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:600px){ .itm-add-grid { grid-template-columns:1fr; } }

/* Image select row */
.itm-img-row { display:flex; align-items:center; gap:10px; }
.itm-img-row select { flex:1; }

/* Tooltip chip */
.itm-chip {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600;
    background:rgba(255,255,255,.06); color:var(--text-2); white-space:nowrap;
}
</style>

<div class="adm-page-header">
    <div>
        <div class="adm-page-title">Система предметів</div>
        <div class="adm-page-sub">
            <?= $totalItems ?> предметів у каталозі · <?= $totalUserItems ?> видано гравцям
        </div>
    </div>
</div>

<?php if ($msg):  ?><div class="adm-alert adm-alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="adm-alert adm-alert-error"><?= h($error) ?></div><?php endif; ?>

<div class="itm-tabs">
    <a href="?view=catalog"     class="itm-tab <?= $activeAdminTab==='catalog'     ? 'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        Каталог
    </a>
    <a href="?view=add"         class="itm-tab <?= $activeAdminTab==='add'         ? 'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Додати предмет
    </a>
    <a href="?view=grant"       class="itm-tab <?= $activeAdminTab==='grant'       ? 'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7m0 0a3 3 0 0 0-3-3m3 3a3 3 0 0 1 3-3"/></svg>
        Видати гравцю
    </a>
    <a href="?view=inventories" class="itm-tab <?= $activeAdminTab==='inventories' ? 'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Інвентарі гравців
    </a>
    <a href="?view=log"         class="itm-tab <?= $activeAdminTab==='log'         ? 'active':'' ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Лог дій
    </a>
</div>

<?php
// ════════════════════════════════════════════════════════════════════════════
// TAB: CATALOG
// ════════════════════════════════════════════════════════════════════════════
if ($activeAdminTab === 'catalog'): ?>

<?php if (empty($allItems)): ?>
<div class="adm-card" style="padding:40px;text-align:center;color:var(--text-3)">
    Каталог порожній. <a href="?view=add" class="adm-btn adm-btn-primary" style="margin-left:12px">Додати перший предмет</a>
</div>
<?php else: ?>

<?php
// Групуємо предмети по типу
$typeLabels = ['frame'=>'Рамки аватара','background'=>'Фони профілю','badge'=>'Значки','card_style'=>'Стилі картки'];
foreach ($itemsByType as $typeKey => $typeItems):
?>
<div class="adm-card" style="margin-bottom:20px">
    <div class="adm-card-header">
        <div class="adm-card-title"><?= h($typeLabels[$typeKey] ?? ucfirst($typeKey)) ?> (<?= count($typeItems) ?>)</div>
    </div>
    <div class="itm-grid" style="padding:14px">
    <?php foreach ($typeItems as $item):
        $rColor = ItemService::getRarityColor($item['rarity']);
        $hasImg = !empty($item['image_lg']);
        $condType = $item['condition_type'] ?? 'manual';
        $condValue = (int)($item['condition_value'] ?? 0);
    ?>
    <div class="itm-card" id="itm-<?= $item['id'] ?>">
        <div class="itm-card-head">
            <div class="itm-card-img">
                <?php if ($hasImg): ?>
                <img src="<?= h(SITE_URL.'/'.$item['image_lg']) ?>" alt="">
                <?php else: ?>
                <span class="itm-card-img-ph">🖼</span>
                <?php endif; ?>
            </div>
            <div class="itm-card-meta">
                <div class="itm-card-name"><?= h($item['name']) ?></div>
                <div class="itm-card-slug"><?= h($item['slug']) ?></div>
                <div class="itm-card-badges">
                    <span class="itm-badge" style="color:<?= $rColor ?>;border-color:<?= $rColor ?>33;background:<?= $rColor ?>11">
                        <span class="itm-rarity-dot" style="background:<?= $rColor ?>"></span>
                        <?= ItemService::getRarityLabel($item['rarity']) ?>
                    </span>
                    <?php if ($item['avatar_shape'] === 'square'): ?>
                    <span class="itm-chip">⬛ Квадрат</span>
                    <?php endif; ?>
                    <?php if ($item['animated']): ?>
                    <span class="itm-chip">✨ Анімована</span>
                    <?php endif; ?>
                    <?php if ($item['hidden']): ?>
                    <span class="itm-chip">🔒 Прихована</span>
                    <?php endif; ?>
                    <?php if ($condType !== 'manual'): ?>
                    <span class="itm-chip"><?= h(match($condType){
                        'registration_days' => "📅 {$condValue}д на сайті",
                        'playtime_hours'    => "⏱ {$condValue}год на серв.",
                        'kills'             => "💀 ".number_format($condValue)." кілів",
                        'faceit_level'      => "🎮 FACEIT {$condValue}+",
                        default             => $condType,
                    }) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="font-size:11px;color:var(--text-3);font-family:var(--mono);flex-shrink:0">#<?= $item['id'] ?></div>
        </div>

        <?php if (!empty($item['description'])): ?>
        <div style="padding:8px 14px;font-size:12px;color:var(--text-3);border-bottom:1px solid var(--border);font-style:italic">
            "<?= h(mb_substr($item['description'],0,80)) ?><?= mb_strlen($item['description'])>80?'…':'' ?>"
        </div>
        <?php endif; ?>

        <!-- Edit panel (hidden by default) -->
        <div class="itm-edit-panel" id="edit-panel-<?= $item['id'] ?>">
            <form method="post">
                <input type="hidden" name="action" value="edit_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <?= adminCsrfField() ?>

                <div class="itm-edit-grid" style="margin-bottom:10px">
                    <div>
                        <label class="adm-label">Назва</label>
                        <input type="text" name="name" class="adm-input" value="<?= h($item['name']) ?>" required maxlength="100">
                    </div>
                    <div>
                        <label class="adm-label">Рідкість</label>
                        <select name="rarity" class="adm-input">
                            <?php foreach (ItemService::getAllRarities() as $key => $r): ?>
                            <option value="<?= $key ?>" <?= $item['rarity']===$key?'selected':'' ?> style="color:<?= $r['color'] ?>"><?= $r['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:10px">
                    <label class="adm-label">Зображення</label>
                    <div class="itm-img-row">
                        <img id="ep-preview-<?= $item['id'] ?>" class="itm-img-preview <?= $hasImg?'visible':'' ?>"
                             src="<?= $hasImg ? h(SITE_URL.'/'.$item['image_lg']) : '' ?>" alt="">
                        <select name="image_lg" class="adm-input" onchange="itmPreview(this,'ep-preview-<?= $item['id'] ?>')">
                            <option value="">— без зображення —</option>
                            <?php foreach ($availableImages as $img): ?>
                            <option value="<?= h($img) ?>" <?= $item['image_lg']===$img?'selected':'' ?>><?= h(basename($img)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-bottom:10px">
                    <label class="adm-label">Опис / як отримати (буде показано гравцям)</label>
                    <textarea name="description" class="adm-input" rows="2" maxlength="500" placeholder="Унікальна рамка для..." style="resize:vertical"><?= h($item['description'] ?? '') ?></textarea>
                </div>

                <div class="itm-edit-grid-3" style="margin-bottom:10px">
                    <div>
                        <label class="adm-label">Форма аватара</label>
                        <select name="avatar_shape" class="adm-input">
                            <option value="rounded" <?= ($item['avatar_shape']??'rounded')==='rounded'?'selected':'' ?>>◼ Скруглена</option>
                            <option value="square"  <?= ($item['avatar_shape']??'rounded')==='square' ?'selected':'' ?>>⬛ Квадратна</option>
                        </select>
                    </div>
                    <div>
                        <label class="adm-label">Умова авто-видачі</label>
                        <select name="condition_type" class="adm-input">
                            <option value="manual"             <?= $condType==='manual'            ?'selected':'' ?>>manual — тільки адмін</option>
                            <option value="registration_days"  <?= $condType==='registration_days' ?'selected':'' ?>>Днів на сайті</option>
                            <option value="playtime_hours"     <?= $condType==='playtime_hours'    ?'selected':'' ?>>Годин на серверах</option>
                            <option value="kills"              <?= $condType==='kills'             ?'selected':'' ?>>Вбивств</option>
                            <option value="faceit_level"       <?= $condType==='faceit_level'      ?'selected':'' ?>>FACEIT рівень</option>
                        </select>
                    </div>
                    <div>
                        <label class="adm-label">Значення умови</label>
                        <input type="number" name="condition_value" class="adm-input" value="<?= $condValue ?>" min="0">
                    </div>
                </div>

                <div class="itm-edit-grid-3" style="margin-bottom:12px">
                    <div>
                        <label class="adm-label">Сортування</label>
                        <input type="number" name="sort_order" class="adm-input" value="<?= (int)$item['sort_order'] ?>" min="0">
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:14px;padding-bottom:2px">
                        <label class="adm-toggle-label">
                            <input type="checkbox" name="animated" <?= $item['animated']?'checked':'' ?>>
                            Анімована
                        </label>
                        <label class="adm-toggle-label">
                            <input type="checkbox" name="hidden" <?= $item['hidden']?'checked':'' ?>>
                            Прихована
                        </label>
                    </div>
                </div>

                <div style="display:flex;gap:8px">
                    <button type="submit" class="adm-btn adm-btn-primary adm-btn-sm">💾 Зберегти</button>
                    <button type="button" class="adm-btn adm-btn-ghost adm-btn-sm" onclick="itmToggleEdit(<?= $item['id'] ?>)">Скасувати</button>
                </div>
            </form>
        </div>

        <div class="itm-card-actions">
            <button class="adm-btn adm-btn-ghost adm-btn-sm" onclick="itmToggleEdit(<?= $item['id'] ?>)">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Редагувати
            </button>
            <?php if (!empty($item['image_lg'])): ?>
            <a href="<?= h(SITE_URL.'/'.$item['image_lg']) ?>" target="_blank" class="adm-btn adm-btn-ghost adm-btn-sm">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Зображення
            </a>
            <?php endif; ?>
            <div style="flex:1"></div>
            <form method="post" onsubmit="return confirm('Видалити «<?= h(addslashes($item['name'])) ?>»? Це НАЗАВЖДИ видалить його з інвентарів усіх гравців!')">
                <input type="hidden" name="action" value="delete_item">
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <?= adminCsrfField() ?>
                <button type="submit" class="adm-btn adm-btn-danger adm-btn-sm">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                    Видалити
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════════════════════
// TAB: ADD ITEM
// ════════════════════════════════════════════════════════════════════════════
elseif ($activeAdminTab === 'add'): ?>

<div class="adm-card">
    <div class="adm-card-header">
        <div class="adm-card-title">Додати новий предмет до каталогу</div>
    </div>
    <div style="padding:20px">
    <form method="post" action="?view=catalog">
        <input type="hidden" name="action" value="add_item">
        <?= adminCsrfField() ?>

        <div class="itm-add-grid" style="margin-bottom:14px">
            <div>
                <label class="adm-label">Slug (унікальний, тільки a-z0-9_) *</label>
                <input type="text" name="slug" class="adm-input" placeholder="frame_my_name" required pattern="[a-z0-9_]+" autocomplete="off">
            </div>
            <div>
                <label class="adm-label">Назва (показується гравцям) *</label>
                <input type="text" name="name" class="adm-input" placeholder="Моя рамка" required maxlength="100">
            </div>
            <div>
                <label class="adm-label">Тип</label>
                <select name="type" class="adm-input">
                    <option value="frame">🖼 frame — Рамка аватара</option>
                    <option value="background">🎨 background — Фон профілю</option>
                    <option value="badge">⭐ badge — Значок</option>
                    <option value="card_style">🃏 card_style — Стиль картки</option>
                </select>
            </div>
            <div>
                <label class="adm-label">Рідкість</label>
                <select name="rarity" class="adm-input">
                    <?php foreach (ItemService::getAllRarities() as $key => $r): ?>
                    <option value="<?= $key ?>" style="color:<?= $r['color'] ?>"><?= $r['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="avatarShapeField">
                <label class="adm-label">Форма аватарки під рамку</label>
                <select name="avatar_shape" class="adm-input">
                    <option value="rounded">◼ Скруглена (стандарт)</option>
                    <option value="square">⬛ Квадратна (без скруглення)</option>
                </select>
                <div id="bgSizeHint" style="display:none;margin-top:5px;font-size:11px;color:var(--text-3)">
                    💡 Рекомендований розмір фону: <strong>1200×200px</strong> або ширший (PNG/JPG/WebP)
                </div>
            </div>
            <script>
            document.querySelector('select[name="type"]')?.addEventListener('change', function() {
                const isFrame = this.value === 'frame';
                const isBg = this.value === 'background';
                const shapeField = document.getElementById('avatarShapeField');
                const select = shapeField?.querySelector('select');
                const hint = document.getElementById('bgSizeHint');
                if (select) select.style.display = isFrame ? '' : 'none';
                if (shapeField?.querySelector('.adm-label')) shapeField.querySelector('.adm-label').style.display = isFrame ? '' : 'none';
                if (hint) hint.style.display = isBg ? '' : 'none';
            });
            </script>
            <div>
                <label class="adm-label">Умова авто-видачі</label>
                <select name="condition_type" class="adm-input">
                    <option value="manual">manual — тільки адмін</option>
                    <option value="registration_days">📅 Днів на сайті</option>
                    <option value="playtime_hours">⏱ Годин на серверах</option>
                    <option value="kills">💀 Кількість вбивств</option>
                    <option value="faceit_level">🎮 FACEIT рівень</option>
                </select>
            </div>
            <div>
                <label class="adm-label">Значення умови (0 = одразу)</label>
                <input type="number" name="condition_value" class="adm-input" value="0" min="0">
            </div>
            <div>
                <label class="adm-label">Сортування (менше = вище)</label>
                <input type="number" name="sort_order" class="adm-input" value="0" min="0">
            </div>
        </div>

        <div style="margin-bottom:14px">
            <label class="adm-label">Зображення (з assets/items/)</label>
            <div class="itm-img-row">
                <img id="add-img-preview" class="itm-img-preview" src="" alt="">
                <select name="image_lg" class="adm-input" onchange="itmPreview(this,'add-img-preview')">
                    <option value="">— без зображення —</option>
                    <?php foreach ($availableImages as $img): ?>
                    <option value="<?= h($img) ?>"><?= h(basename($img)) ?> (<?= h(dirname($img)) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-top:6px;font-size:11px;color:var(--text-3)">
                📁 Рамки: <code>assets/items/frames/</code> &nbsp;|&nbsp; 🖼 Фони: <code>assets/items/backgrounds/</code>
            </div>
            <?php if (empty($availableImages)): ?>
            <div style="margin-top:6px;font-size:12px;color:var(--yellow)">⚠ Файли не знайдено в assets/items/. Завантажте файли на сервер спочатку.</div>
            <?php endif; ?>
        </div>

        <div style="margin-bottom:14px">
            <label class="adm-label">Опис / як отримати (буде показано гравцям у профілі)</label>
            <textarea name="description" class="adm-input" rows="2" maxlength="500" style="resize:vertical" placeholder="Наприклад: Ця рамка видається за першу перемогу на нашому сервері..."></textarea>
            <div style="margin-top:4px;font-size:11px;color:var(--text-3)">Якщо залишити порожнім — текст буде генеруватись автоматично з умови вище.</div>
        </div>

        <div style="display:flex;gap:20px;align-items:center;margin-bottom:16px;flex-wrap:wrap">
            <label class="adm-toggle-label">
                <input type="checkbox" name="animated"> Анімована (APNG/WebP)
            </label>
            <label class="adm-toggle-label">
                <input type="checkbox" name="hidden"> Прихована (тільки адмін може видати)
            </label>
        </div>

        <button type="submit" class="adm-btn adm-btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Додати предмет
        </button>
    </form>
    </div>
</div>

<?php
// ════════════════════════════════════════════════════════════════════════════
// TAB: GRANT ITEM
// ════════════════════════════════════════════════════════════════════════════
elseif ($activeAdminTab === 'grant'): ?>

<div class="adm-card">
    <div class="adm-card-header">
        <div class="adm-card-title">Видати предмет гравцю</div>
    </div>
    <div style="padding:20px">
    <form method="post">
        <input type="hidden" name="action" value="grant_item">
        <?= adminCsrfField() ?>
        <div class="itm-grant-grid" style="margin-bottom:14px">
            <div>
                <label class="adm-label">Steam ID гравця (17 цифр) *</label>
                <input type="text" name="steam_id" class="adm-input" placeholder="76561198xxxxxxxxx" required pattern="\d{17}" maxlength="17">
                <div style="margin-top:4px;font-size:11px;color:var(--text-3)">Гравець повинен хоча б раз увійти на сайт</div>
            </div>
            <div>
                <label class="adm-label">Предмет *</label>
                <select name="item_id" class="adm-input" required>
                    <option value="">— Обрати предмет —</option>
                    <?php
                    foreach ($itemsByType as $typeKey => $typeItems):
                        $tLabel = $typeLabels[$typeKey] ?? ucfirst($typeKey);
                    ?>
                    <optgroup label="── <?= h($tLabel) ?> ──">
                        <?php foreach ($typeItems as $item): ?>
                        <option value="<?= $item['id'] ?>">
                            [<?= ItemService::getRarityLabel($item['rarity']) ?>] <?= h($item['name']) ?><?= $item['hidden'] ? ' 🔒' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><path d="M12 22V7"/></svg>
            Видати предмет
        </button>
    </form>
    </div>
</div>

<?php
// ════════════════════════════════════════════════════════════════════════════
// TAB: INVENTORIES
// ════════════════════════════════════════════════════════════════════════════
elseif ($activeAdminTab === 'inventories'): ?>

<div class="adm-card" style="margin-bottom:20px">
    <div style="padding:16px 20px">
    <form method="get" style="display:flex;gap:10px;align-items:flex-end">
        <input type="hidden" name="view" value="inventories">
        <div style="flex:1">
            <label class="adm-label">Steam ID або нік гравця</label>
            <input type="text" name="inv_search" class="adm-input" value="<?= h($invSearch) ?>" placeholder="76561198... або нікнейм">
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">Знайти</button>
    </form>
    </div>
</div>

<?php if ($invSearch !== '' && empty($invUsers)): ?>
<div class="adm-alert adm-alert-error">Гравця не знайдено</div>
<?php endif; ?>

<?php foreach ($invUsers as $u): ?>
<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;background:var(--surface);border:1px solid var(--border);border-radius:12px;margin-bottom:14px">
    <?php if ($u['avatar_url']): ?>
    <img src="<?= h($u['avatar_url']) ?>" style="width:42px;height:42px;border-radius:9px;object-fit:cover" alt="">
    <?php endif; ?>
    <div>
        <div style="font-weight:700"><?= h($u['steam_name']) ?></div>
        <div style="font-size:12px;color:var(--text-3);font-family:var(--mono)"><?= h($u['steam_id']) ?></div>
    </div>
    <div style="margin-left:auto;font-size:13px;color:var(--text-3)"><?= count($invItems) ?> предметів</div>
</div>
<?php endforeach; ?>

<?php if (!empty($invItems)): ?>
<div class="adm-card">
    <div class="adm-card-header">
        <div class="adm-card-title">Інвентар (<?= count($invItems) ?> предметів)</div>
    </div>
    <div class="adm-table-wrap">
    <table class="itm-tbl">
        <thead>
            <tr><th>Предмет</th><th>Тип</th><th>Рідкість</th><th>Отримано</th><th>Як</th><th>Екіп.</th><th>Дія</th></tr>
        </thead>
        <tbody>
        <?php foreach ($invItems as $it):
            $rColor = ItemService::getRarityColor($it['rarity']);
        ?>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:9px">
                    <?php if (!empty($it['image_lg'])): ?>
                    <img src="<?= h(SITE_URL.'/'.$it['image_lg']) ?>" style="width:32px;height:32px;object-fit:contain;border-radius:5px;background:var(--bg)" alt="">
                    <?php endif; ?>
                    <span style="font-weight:700"><?= h($it['name']) ?></span>
                </div>
            </td>
            <td><span style="font-family:var(--mono);font-size:11px;background:rgba(255,255,255,.06);padding:2px 7px;border-radius:4px"><?= h($it['type']) ?></span></td>
            <td>
                <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;color:<?= $rColor ?>;background:<?= $rColor ?>11;border:1px solid <?= $rColor ?>33">
                    <span style="width:7px;height:7px;border-radius:50%;background:<?= $rColor ?>;flex-shrink:0"></span>
                    <?= ItemService::getRarityLabel($it['rarity']) ?>
                </span>
            </td>
            <td style="font-size:11px;color:var(--text-3)"><?= !empty($it['obtained_at']) ? date('d.m.Y', strtotime($it['obtained_at'])) : '—' ?></td>
            <td style="font-size:11px"><?= h($it['obtained_by'] ?? '—') ?></td>
            <td><?= $it['is_equipped'] ? '<span style="color:var(--green);font-weight:700">✓</span>' : '<span style="color:var(--text-3)">—</span>' ?></td>
            <td>
                <form method="post" onsubmit="return confirm('Забрати предмет?')">
                    <input type="hidden" name="action" value="revoke_item">
                    <input type="hidden" name="steam_id" value="<?= h($invUsers[0]['steam_id'] ?? '') ?>">
                    <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                    <?= adminCsrfField() ?>
                    <button type="submit" class="adm-btn adm-btn-danger adm-btn-xs">Забрати</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php elseif (!empty($invUsers)): ?>
<div style="text-align:center;padding:40px;color:var(--text-3);background:var(--surface);border:1px solid var(--border);border-radius:12px">
    У гравця порожній інвентар.
    <a href="?view=grant" class="adm-btn adm-btn-primary" style="margin-left:12px">Видати предмет</a>
</div>
<?php endif; ?>

<?php
// ════════════════════════════════════════════════════════════════════════════
// TAB: LOG
// ════════════════════════════════════════════════════════════════════════════
elseif ($activeAdminTab === 'log'): ?>

<div class="adm-card">
    <div class="adm-card-header">
        <div class="adm-card-title">Останні дії з предметами (30)</div>
    </div>
    <?php if (empty($recentGrants)): ?>
    <div style="padding:40px;text-align:center;color:var(--text-3)">Лог порожній</div>
    <?php else: ?>
    <div class="adm-table-wrap">
    <table class="itm-tbl">
        <thead><tr><th>Час</th><th>Адмін</th><th>Дія</th><th>Ціль</th><th>Деталі</th></tr></thead>
        <tbody>
        <?php foreach ($recentGrants as $log):
            $details = json_decode($log['details'] ?? '{}', true) ?? [];
            $actionLabel = match($log['action']) {
                'grant_item'  => '<span style="color:var(--green);font-weight:700">▲ Видано</span>',
                'revoke_item' => '<span style="color:var(--red);font-weight:700">▼ Забрано</span>',
                'add_item'    => '<span style="color:var(--accent);font-weight:700">+ Додано</span>',
                'edit_item'   => '<span style="color:var(--yellow);font-weight:700">✎ Редаг.</span>',
                'delete_item' => '<span style="color:var(--red);font-weight:700">✕ Видалено</span>',
                default       => h($log['action']),
            };
        ?>
        <tr>
            <td style="font-size:11px;color:var(--text-3);white-space:nowrap"><?= date('d.m H:i', strtotime($log['created_at'])) ?></td>
            <td style="font-size:12px;font-weight:700"><?= h($log['admin_name']) ?></td>
            <td><?= $actionLabel ?></td>
            <td style="font-size:11px;font-family:var(--mono);color:var(--text-3)"><?= h(mb_substr($log['target'],0,30)) ?></td>
            <td style="font-size:11px;color:var(--text-3)"><?= h($details['item_name'] ?? $details['name'] ?? $details['item_id'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<script>
// ── Toggle edit panel ────────────────────────────────────────────────────────
function itmToggleEdit(id) {
    const panel = document.getElementById('edit-panel-' + id);
    if (!panel) return;
    panel.classList.toggle('open');
    // Close others
    document.querySelectorAll('.itm-edit-panel.open').forEach(p => {
        if (p.id !== 'edit-panel-' + id) p.classList.remove('open');
    });
}

// ── Image preview on select change ──────────────────────────────────────────
function itmPreview(selectEl, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    const val = selectEl.value;
    if (val) {
        preview.src = '<?= SITE_URL ?>/' + val;
        preview.classList.add('visible');
    } else {
        preview.src = '';
        preview.classList.remove('visible');
    }
}
</script>

<?php adminLayoutEnd(); ?>