<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

$configPath = __DIR__ . '/../servers_config.php';
$msg   = '';
$error = '';

function loadServersFromDB(PDO $pdo): array {
    $rows = $pdo->query("
        SELECT s.id, s.mode_id, s.name, s.ip, s.port, s.tags, s.sort_order, s.active,
               m.slug
        FROM servers s
        JOIN modes m ON m.id = s.mode_id
        ORDER BY m.slug, s.sort_order, s.id
    ")->fetchAll();
    $grouped = [];
    foreach ($rows as $r) $grouped[$r['slug']][] = $r;
    return $grouped;
}

function regenerateServersConfig(PDO $pdo, string $configPath): bool {
    $grouped = loadServersFromDB($pdo);
    $php  = "<?php\n/**\n * CSHunter — Конфігурація серверів\n";
    $php .= " * Авто-згенеровано адмін-панеллю " . date('d.m.Y H:i:s') . "\n";
    $php .= " * НЕ РЕДАГУЙ ВРУЧНУ\n */\n\nreturn [\n\n";
    foreach ($grouped as $slug => $servers) {
        $php .= "    '" . addslashes($slug) . "' => [\n";
        foreach ($servers as $s) {
            if (!$s['active']) continue;
            $php .= "        ['name'=>'" . addslashes($s['name']) . "','ip'=>'" . addslashes($s['ip']) . "','port'=>" . (int)$s['port'] . ",'tags'=>'" . addslashes($s['tags']??'') . "'],\n";
        }
        $php .= "    ],\n\n";
    }
    $php .= "];\n";
    $tmp = $configPath . '.tmp';
    if (file_put_contents($tmp, $php, LOCK_EX) === false) return false;
    return rename($tmp, $configPath);
}

function sanitizeServerRow(array $s): ?array {
    $name = mb_substr(trim($s['name'] ?? ''), 0, 150);
    $ip   = trim($s['ip'] ?? '');
    $port = (int)($s['port'] ?? 0);
    $tags = mb_substr(trim($s['tags'] ?? ''), 0, 200);
    if (!$name || !$ip || $port < 1 || $port > 65535) return null;
    if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^[\w.\-]{1,253}$/', $ip)) return null;
    return compact('name', 'ip', 'port', 'tags');
}

function triggerCronAsync(): void {
    $cronPath = __DIR__ . '/../cron/update_servers.php';
    if (!file_exists($cronPath)) return;
    exec('php ' . escapeshellarg($cronPath) . ' >> /tmp/cshunter_cron.log 2>&1 &');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';

    // AJAX toggle
    if ($action === 'toggle_active') {
        $id     = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['active'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE servers SET active=? WHERE id=?")->execute([$active, $id]);
            adminLog('toggle_server', (string)$id, ['active' => $active]);
            regenerateServersConfig($pdo, $configPath);
            if ($active) triggerCronAsync();
        }
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'active' => $active]);
        exit;
    }

    if ($action === 'save_all') {
        $submittedModes = $_POST['modes'] ?? [];
        $allModes       = getDemoModes();
        $modeBySlug     = array_column($allModes, null, 'slug');

        try {
            $pdo->beginTransaction();

            $existingIds  = array_column($pdo->query("SELECT id FROM servers")->fetchAll(), 'id');
            $submittedIds = [];
            $order = 0;

            foreach ($submittedModes as $slug => $serverList) {
                $slug = preg_replace('/[^a-z0-9_\-]/', '', $slug);
                if (!isset($modeBySlug[$slug])) continue;
                $modeId = (int)$modeBySlug[$slug]['id'];

                $pdo->prepare("INSERT IGNORE INTO modes (id,slug,name,tag,description,sort_order,active) VALUES (?,?,?,?,?,?,1)")
                    ->execute([$modeId,$slug,$modeBySlug[$slug]['name'],$modeBySlug[$slug]['tag'],$modeBySlug[$slug]['description'],$modeBySlug[$slug]['id']]);

                foreach ($serverList as $s) {
                    $clean = sanitizeServerRow($s);
                    if (!$clean) continue;
                    $existingId = (int)($s['id'] ?? 0);
                    $active     = isset($s['active']) ? (int)$s['active'] : 1;

                    if ($existingId && in_array($existingId, $existingIds)) {
                        $pdo->prepare("UPDATE servers SET name=?,ip=?,port=?,tags=?,sort_order=?,active=? WHERE id=?")
                            ->execute([$clean['name'],$clean['ip'],$clean['port'],$clean['tags'],$order++,$active,$existingId]);
                        $submittedIds[] = $existingId;
                    } else {
                        $pdo->prepare("INSERT INTO servers (mode_id,name,ip,port,tags,sort_order,active) VALUES (?,?,?,?,?,?,1)")
                            ->execute([$modeId,$clean['name'],$clean['ip'],$clean['port'],$clean['tags'],$order++]);
                        $submittedIds[] = (int)$pdo->lastInsertId();
                    }
                }
            }

            $toDelete = array_diff($existingIds, $submittedIds);
            if ($toDelete) {
                $ph = implode(',', array_fill(0, count($toDelete), '?'));
                $pdo->prepare("DELETE FROM servers WHERE id IN ($ph)")->execute(array_values($toDelete));
            }

            $pdo->commit();

            if (!regenerateServersConfig($pdo, $configPath)) {
                $error = 'БД збережено, але не вдалось оновити servers_config.php';
            } else {
                $msg = 'Збережено · запускаємо перевірку серверів…';
            }

            adminLog('save_servers', '', ['modes' => array_keys($submittedModes)]);
            triggerCronAsync();

        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Помилка БД: ' . $e->getMessage();
        }
    }
}

$serversFromDB = $pdo ? loadServersFromDB($pdo) : [];
$allModes      = getDemoModes();

$page_title = 'Сервери';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'servers');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Управління серверами</h1>
        <p class="adm-page-sub">БД → servers_config.php · приховані сервери не потрапляють на сайт</p>
    </div>
</div>

<?php if ($msg): ?><div class="adm-alert adm-alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="adm-alert adm-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" id="serversForm">
    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
    <input type="hidden" name="action" value="save_all">

    <?php foreach ($allModes as $mode):
        $slug        = $mode['slug'];
        $servers     = $serversFromDB[$slug] ?? [];
        $activeCount = count(array_filter($servers, fn($s) => $s['active']));
    ?>
    <div class="adm-card adm-card-mode" data-slug="<?= htmlspecialchars($slug) ?>">
        <div class="adm-card-header">
            <div class="adm-mode-header-left">
                <span class="adm-badge adm-badge-yellow"><?= htmlspecialchars($mode['tag']) ?></span>
                <span class="adm-card-title"><?= htmlspecialchars($mode['name']) ?></span>
                <span class="adm-server-count" id="count-<?= $slug ?>">
                    <?= $activeCount ?>/<?= count($servers) ?> активних
                </span>
            </div>
            <button type="button" class="adm-btn adm-btn-secondary adm-btn-sm"
                    onclick="addServer('<?= $slug ?>')">+ Додати сервер</button>
        </div>

        <div class="adm-servers-list" id="list-<?= $slug ?>">
            <?php if (empty($servers)): ?>
                <div class="adm-empty adm-servers-empty" id="empty-<?= $slug ?>">
                    Немає серверів — режим прихований на сайті
                </div>
            <?php endif; ?>

            <?php foreach ($servers as $i => $s): ?>
            <div class="adm-server-row <?= !$s['active'] ? 'adm-server-row--hidden' : '' ?>"
                 data-index="<?= $i ?>" data-id="<?= (int)$s['id'] ?>">
                <div class="adm-server-row-handle" title="Сортування">⠿</div>
                <input type="hidden" name="modes[<?= $slug ?>][<?= $i ?>][id]"     value="<?= (int)$s['id'] ?>">
                <input type="hidden" name="modes[<?= $slug ?>][<?= $i ?>][active]" value="<?= (int)$s['active'] ?>" class="srv-active-input">
                <div class="adm-server-fields">
                    <input type="text"   name="modes[<?= $slug ?>][<?= $i ?>][name]" value="<?= htmlspecialchars($s['name']) ?>"     placeholder="Назва сервера"    class="adm-input" required>
                    <input type="text"   name="modes[<?= $slug ?>][<?= $i ?>][ip]"   value="<?= htmlspecialchars($s['ip']) ?>"       placeholder="IP / hostname"     class="adm-input adm-input-ip" required>
                    <input type="number" name="modes[<?= $slug ?>][<?= $i ?>][port]" value="<?= (int)$s['port'] ?>"                  placeholder="Port"              class="adm-input adm-input-port" min="1" max="65535" required>
                    <input type="text"   name="modes[<?= $slug ?>][<?= $i ?>][tags]" value="<?= htmlspecialchars($s['tags'] ?? '') ?>" placeholder="Теги"            class="adm-input adm-input-tags">
                </div>
                <div class="adm-server-actions">
                    <button type="button"
                            class="adm-btn adm-btn-xs srv-toggle-btn <?= $s['active'] ? 'adm-btn-secondary' : 'adm-btn-ghost' ?>"
                            data-id="<?= (int)$s['id'] ?>" data-active="<?= (int)$s['active'] ?>"
                            title="<?= $s['active'] ? 'Приховати з сайту' : 'Показати на сайті' ?>">
                        <?= $s['active'] ? '👁 Видимий' : '🙈 Прихований' ?>
                    </button>
                    <button type="button" class="adm-btn adm-btn-xs adm-btn-danger-ghost"
                            onclick="removeServer(this,'<?= $slug ?>')">✕</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="adm-form-actions">
        <button type="submit" class="adm-btn adm-btn-primary adm-btn-lg">💾 Зберегти всі сервери</button>
        <span class="adm-text-muted adm-text-sm">Після збереження автоматично запускається перевірка онлайну</span>
    </div>
</form>

<style>
.adm-server-row--hidden { opacity:.45; }
.adm-server-row--hidden .adm-server-fields input { color:var(--text-3); }
.adm-server-actions { display:flex; align-items:center; gap:4px; flex-shrink:0; }
</style>

<script>
const counters = {};
const csrf = document.querySelector('meta[name="csrf-token"]').content;
<?php foreach ($allModes as $mode): $slug = $mode['slug']; ?>
counters['<?= $slug ?>'] = <?= count($serversFromDB[$slug] ?? []) ?>;
<?php endforeach; ?>

document.addEventListener('click', async (e) => {
    const btn = e.target.closest('.srv-toggle-btn');
    if (!btn) return;
    const id = parseInt(btn.dataset.id);
    if (!id) return; // новий незбережений сервер — просто міняємо локально
    const newActive = btn.dataset.active === '1' ? 0 : 1;
    btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('action','toggle_active'); fd.append('id',id);
        fd.append('active',newActive); fd.append('csrf_token',csrf);
        const data = await fetch('servers.php',{method:'POST',body:fd}).then(r=>r.json());
        if (data.ok) applyToggle(btn, newActive);
    } catch(err){ alert('Помилка: '+err.message); }
    finally { btn.disabled = false; }
});

function applyToggle(btn, newActive) {
    btn.dataset.active   = newActive;
    btn.textContent      = newActive ? '👁 Видимий' : '🙈 Прихований';
    btn.className        = 'adm-btn adm-btn-xs srv-toggle-btn ' + (newActive ? 'adm-btn-secondary' : 'adm-btn-ghost');
    btn.title            = newActive ? 'Приховати з сайту' : 'Показати на сайті';
    const row            = btn.closest('.adm-server-row');
    row.querySelector('.srv-active-input').value = newActive;
    row.classList.toggle('adm-server-row--hidden', !newActive);
    updateActiveCount(row.closest('[data-slug]').dataset.slug);
}

function updateActiveCount(slug) {
    const rows   = document.querySelectorAll('[data-slug="'+slug+'"] .adm-server-row');
    const active = [...rows].filter(r=>!r.classList.contains('adm-server-row--hidden')).length;
    const el     = document.getElementById('count-'+slug);
    if (el) el.textContent = active+'/'+rows.length+' активних';
}

function addServer(slug) {
    const idx  = counters[slug]++;
    const list = document.getElementById('list-'+slug);
    const empty = document.getElementById('empty-'+slug);
    if (empty) empty.remove();
    const row = document.createElement('div');
    row.className = 'adm-server-row';
    row.innerHTML = `
        <div class="adm-server-row-handle">⠿</div>
        <input type="hidden" name="modes[${slug}][${idx}][id]"     value="0">
        <input type="hidden" name="modes[${slug}][${idx}][active]" value="1" class="srv-active-input">
        <div class="adm-server-fields">
            <input type="text"   name="modes[${slug}][${idx}][name]" placeholder="Назва сервера"    class="adm-input" required>
            <input type="text"   name="modes[${slug}][${idx}][ip]"   placeholder="IP / hostname"     class="adm-input adm-input-ip" required>
            <input type="number" name="modes[${slug}][${idx}][port]" placeholder="Port"              class="adm-input adm-input-port" min="1" max="65535" required>
            <input type="text"   name="modes[${slug}][${idx}][tags]" placeholder="Теги"              class="adm-input adm-input-tags">
        </div>
        <div class="adm-server-actions">
            <button type="button" class="adm-btn adm-btn-xs adm-btn-secondary srv-toggle-btn"
                    data-id="0" data-active="1">👁 Видимий</button>
            <button type="button" class="adm-btn adm-btn-xs adm-btn-danger-ghost"
                    onclick="removeServer(this,'${slug}')">✕</button>
        </div>`;
    list.appendChild(row);
    row.querySelector('input[type=text]').focus();
    updateActiveCount(slug);
}

function removeServer(btn, slug) {
    if (!confirm('Видалити цей сервер?')) return;
    btn.closest('.adm-server-row').remove();
    updateActiveCount(slug);
    const list = document.getElementById('list-'+slug);
    if (!list.querySelector('.adm-server-row')) {
        const d = document.createElement('div');
        d.className='adm-empty adm-servers-empty'; d.id='empty-'+slug;
        d.textContent='Немає серверів — режим прихований на сайті';
        list.appendChild(d);
    }
}
</script>

<?php adminLayoutEnd(); ?>
