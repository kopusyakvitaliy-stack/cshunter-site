<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

// ── Filters ───────────────────────────────────────────────────────────────────
$search      = trim($_GET['q']       ?? '');
$actionF     = trim($_GET['action_f']?? '');
$adminSteamF = trim($_GET['admin_id']?? '');
$dateFrom    = trim($_GET['from']    ?? '');
$dateTo      = trim($_GET['to']      ?? '');
$page        = max(1, (int)($_GET['p'] ?? 1));
$perPage     = 30;
$offset      = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($search) {
    $where[]  = '(action LIKE ? OR target LIKE ? OR admin_name LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($actionF)     { $where[] = 'action = ?';           $params[] = $actionF; }
if ($adminSteamF) { $where[] = 'admin_steam_id = ?';   $params[] = $adminSteamF; }
if ($dateFrom)    { $where[] = 'DATE(created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)      { $where[] = 'DATE(created_at) <= ?'; $params[] = $dateTo; }

$whereSQL = implode(' AND ', $where);

// Get distinct actions and admins for filter dropdowns
$availableActions = [];
$availableAdmins  = [];
if ($pdo) {
    try {
        $availableActions = $pdo->query("SELECT DISTINCT action FROM admin_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
        $availableAdmins  = $pdo->query("SELECT DISTINCT admin_steam_id, admin_name FROM admin_log ORDER BY admin_name")->fetchAll();
    } catch (Throwable $e) {}
}

$total = 0;
$logs  = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_log WHERE $whereSQL");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $stmt2 = $pdo->prepare("SELECT * FROM admin_log WHERE $whereSQL ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
        $stmt2->execute($params);
        $logs = $stmt2->fetchAll();
    } catch (Throwable $e) {}
}

$totalPages = max(1, ceil($total / $perPage));
$hasFilters = $search || $actionF || $adminSteamF || $dateFrom || $dateTo;

// Human-readable action labels
$actionLabels = [
    'ban_user'       => ['label' => 'Бан',           'color' => 'red'],
    'unban_user'     => ['label' => 'Розбан',         'color' => 'green'],
    'set_role'       => ['label' => 'Зміна ролі',     'color' => 'blue'],
    'clear_skins'    => ['label' => 'Очистка скінів', 'color' => 'yellow'],
    'save_servers'   => ['label' => 'Збереження серверів', 'color' => 'blue'],
    'toggle_server'  => ['label' => 'Вкл/Викл сервер','color' => 'yellow'],
];

// Format details into human-readable string
function formatDetails(string $action, array $details): string {
    if (empty($details)) return '';
    switch ($action) {
        case 'ban_user':
            $s = 'Причина: ' . ($details['reason'] ?: 'не вказана');
            if (!empty($details['until'])) $s .= ' · До: ' . date('d.m.Y H:i', strtotime($details['until']));
            else $s .= ' · Перманентний';
            return $s;
        case 'set_role':
            return 'Нова роль: ' . ($details['role'] ?? '—');
        case 'save_servers':
            $modes = $details['modes'] ?? [];
            return 'Режими: ' . (is_array($modes) ? implode(', ', $modes) : $modes);
        case 'toggle_server':
            return isset($details['active']) ? ($details['active'] ? '✅ Увімкнено' : '🙈 Приховано') : '';
        default:
            $parts = [];
            foreach ($details as $k => $v) {
                $parts[] = $k . ': ' . (is_array($v) ? implode(', ', $v) : $v);
            }
            return implode(' · ', $parts);
    }
}

$page_title = 'Журнал дій';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'logs');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Журнал дій</h1>
        <p class="adm-page-sub">Всього записів: <?= number_format($total) ?></p>
    </div>
</div>

<!-- Filters -->
<div class="adm-filters-block" style="margin-bottom:16px">
    <form method="GET" id="logsForm">
        <div class="adm-filters-row" style="gap:8px;flex-wrap:wrap;display:flex;align-items:center">
            <!-- Search -->
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Пошук…" class="adm-input" style="width:200px"
                   oninput="debounceSubmit()">

            <!-- Action type -->
            <select name="action_f" class="adm-select" onchange="this.form.submit()">
                <option value="">Всі дії</option>
                <?php foreach ($availableActions as $a): ?>
                <?php $lbl = $actionLabels[$a]['label'] ?? $a; ?>
                <option value="<?= htmlspecialchars($a) ?>" <?= $actionF===$a ? 'selected':'' ?>>
                    <?= htmlspecialchars($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Admin filter with avatar -->
            <select name="admin_id" class="adm-select" onchange="this.form.submit()" style="min-width:160px">
                <option value="">Всі адміни</option>
                <?php foreach ($availableAdmins as $a): ?>
                <option value="<?= htmlspecialchars($a['admin_steam_id']) ?>"
                        <?= $adminSteamF===$a['admin_steam_id'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($a['admin_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <!-- Date range -->
            <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>"
                   class="adm-input" style="width:140px" onchange="this.form.submit()"
                   title="Від дати">
            <span class="adm-text-muted adm-text-sm">—</span>
            <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>"
                   class="adm-input" style="width:140px" onchange="this.form.submit()"
                   title="До дати">

            <?php if ($hasFilters): ?>
                <a href="logs.php" class="adm-btn adm-btn-ghost">✕ Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Дія</th>
                    <th>Ціль</th>
                    <th>Деталі</th>
                    <th>Адмін</th>
                    <th>IP</th>
                    <th>Час</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <?php
                $meta    = $actionLabels[$log['action']] ?? ['label' => $log['action'], 'color' => 'gray'];
                $details = json_decode($log['details'] ?? '{}', true) ?: [];
                $detailsStr = formatDetails($log['action'], $details);
            ?>
            <tr class="adm-log-row" onclick="toggleLogDetail(this)" style="cursor:pointer">
                <td>
                    <span class="adm-badge adm-badge-<?= $meta['color'] ?>">
                        <?= htmlspecialchars($meta['label']) ?>
                    </span>
                </td>
                <td class="adm-text-sm">
                    <?php if ($log['target'] && preg_match('/^\d{17}$/', $log['target'])): ?>
                        <a href="<?= SITE_URL ?>/profile/<?= $log['target'] ?>" target="_blank"
                           class="adm-text-sm" style="color:var(--accent)"
                           onclick="event.stopPropagation()">
                            <?= htmlspecialchars($log['target']) ?>
                        </a>
                    <?php elseif ($log['target']): ?>
                        <span><?= htmlspecialchars($log['target']) ?></span>
                    <?php else: ?>
                        <span class="adm-text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-sm adm-text-muted">
                    <?= htmlspecialchars($detailsStr ?: '—') ?>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <?php
                        // Show admin avatar if available
                        $adminRow = null;
                        foreach ($availableAdmins as $a) {
                            if ($a['admin_steam_id'] === $log['admin_steam_id']) { $adminRow = $a; break; }
                        }
                        ?>
                        <div>
                            <div class="adm-user-name adm-text-sm"><?= htmlspecialchars($log['admin_name']) ?></div>
                            <a href="logs.php?admin_id=<?= urlencode($log['admin_steam_id']) ?>"
                               class="adm-user-sub" onclick="event.stopPropagation()"
                               title="Показати тільки цього адміна">
                               всі дії →
                            </a>
                        </div>
                    </div>
                </td>
                <td class="adm-text-muted adm-text-sm"><?= htmlspecialchars($log['ip'] ?? '—') ?></td>
                <td class="adm-text-muted adm-text-sm" title="<?= $log['created_at'] ?>">
                    <?= date('d.m.y H:i', strtotime($log['created_at'])) ?>
                </td>
            </tr>
            <!-- Detail row (hidden by default) -->
            <tr class="adm-log-detail" style="display:none">
                <td colspan="6" style="padding:0">
                    <div class="adm-log-detail-body">
                        <div class="adm-log-detail-grid">
                            <div><span class="adm-log-detail-label">Дія</span><?= htmlspecialchars($log['action']) ?></div>
                            <div><span class="adm-log-detail-label">Адмін Steam ID</span><?= htmlspecialchars($log['admin_steam_id']) ?></div>
                            <div><span class="adm-log-detail-label">IP</span><?= htmlspecialchars($log['ip'] ?? '—') ?></div>
                            <div><span class="adm-log-detail-label">Час</span><?= $log['created_at'] ?></div>
                        </div>
                        <?php if ($details): ?>
                        <div style="margin-top:10px">
                            <span class="adm-log-detail-label">Raw деталі</span>
                            <pre class="adm-log-pre"><?= htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
            <tr><td colspan="6" class="adm-empty">Журнал порожній</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="adm-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $qs = http_build_query(array_filter(['q'=>$search,'action_f'=>$actionF,'admin_id'=>$adminSteamF,'from'=>$dateFrom,'to'=>$dateTo,'p'=>$i]));
        ?>
            <a href="?<?= $qs ?>" class="adm-page-btn <?= $i===$page ? 'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.adm-log-row:hover td { background: rgba(255,255,255,.025); }
.adm-log-row.expanded td { background: rgba(79,142,247,.05); }

.adm-log-detail-body {
    padding: 14px 20px 16px;
    background: rgba(0,0,0,.2);
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}
.adm-log-detail-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    font-size: 12px;
    color: var(--text-2);
}
.adm-log-detail-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--text-3);
    margin-bottom: 3px;
}
.adm-log-pre {
    font-family: var(--mono);
    font-size: 11px;
    color: var(--text-2);
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 10px 14px;
    margin-top: 6px;
    white-space: pre-wrap;
    word-break: break-all;
}
</style>

<script>
let debounceTimer;
function debounceSubmit() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => document.getElementById('logsForm').submit(), 500);
}

function toggleLogDetail(row) {
    const detailRow = row.nextElementSibling;
    const isOpen    = detailRow.style.display !== 'none';
    detailRow.style.display = isOpen ? 'none' : '';
    row.classList.toggle('expanded', !isOpen);
}
</script>

<?php adminLayoutEnd(); ?>
