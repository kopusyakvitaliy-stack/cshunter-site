<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

// ── Stats ─────────────────────────────────────────────────────────────────────
$stats = ['users'=>0,'new_today'=>0,'banned'=>0,'servers'=>0,'current_online'=>0,'peak_online'=>0];
if ($pdo) {
    try {
        $stats['users']          = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE has_logged_in=1")->fetchColumn();
        $stats['new_today']      = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE has_logged_in=1 AND DATE(last_login)=CURDATE()")->fetchColumn();
        $stats['banned']         = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_banned=1")->fetchColumn();
        $stats['current_online'] = (int)$pdo->query("SELECT COALESCE(SUM(players),0) FROM server_status_cache WHERE online=1 AND updated_at>=NOW()-INTERVAL 10 MINUTE")->fetchColumn();
        $stats['peak_online']    = (int)$pdo->query("SELECT COALESCE(MAX(peak_players),0) FROM server_status_cache WHERE DATE(updated_at)=CURDATE()")->fetchColumn();
    } catch (Throwable $e) {}
    try {
        $stats['servers'] = (int)$pdo->query("SELECT COUNT(*) FROM servers WHERE active=1")->fetchColumn();
        $dbSyncWarning = false;
    } catch (Throwable $e) {
        // Таблиця servers відсутня або недоступна — fallback на конфіг
        $cfg = require __DIR__ . '/../servers_config.php';
        $stats['servers'] = array_sum(array_map('count', $cfg));
        $dbSyncWarning = true;
    }
}

// ── Down servers alert ────────────────────────────────────────────────────────
$downServers = [];
if ($pdo) {
    try {
        $downServers = $pdo->query("
            SELECT s.name, s.ip, s.port
            FROM servers s
            LEFT JOIN server_status_cache c ON c.ip=s.ip AND c.port=s.port
            WHERE s.active=1 AND (c.online=0 OR c.updated_at < NOW()-INTERVAL 5 MINUTE OR c.updated_at IS NULL)
        ")->fetchAll();
    } catch (Throwable $e) {}
}

// ── Registrations chart (last 14 days) ───────────────────────────────────────
$chartDays   = [];
$chartCounts = [];
if ($pdo) {
    try {
        $rows = $pdo->query("
            SELECT DATE(last_login) AS day, COUNT(*) AS cnt
            FROM users
            WHERE has_logged_in=1 AND last_login >= NOW()-INTERVAL 14 DAY
            GROUP BY DATE(last_login)
            ORDER BY day ASC
        ")->fetchAll();
        $rowsByDay = array_column($rows, 'cnt', 'day');
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $chartDays[]   = date('d.m', strtotime($d));
            $chartCounts[] = (int)($rowsByDay[$d] ?? 0);
        }
    } catch (Throwable $e) {}
}

// ── Recent logins ─────────────────────────────────────────────────────────────
$recent_users = [];
if ($pdo) {
    try {
        $recent_users = $pdo->query("SELECT steam_id,steam_name,avatar_url,role,last_login FROM users WHERE has_logged_in=1 ORDER BY last_login DESC LIMIT 8")->fetchAll();
    } catch (Throwable $e) {}
}

// ── Recent logs ───────────────────────────────────────────────────────────────
$recent_logs = [];
$actionLabels = ['ban_user'=>'Бан','unban_user'=>'Розбан','set_role'=>'Роль','save_servers'=>'Сервери','toggle_server'=>'Вкл/Викл сервер','clear_skins'=>'Очистка скінів'];
if ($pdo) {
    try {
        $recent_logs = $pdo->query("SELECT * FROM admin_log ORDER BY created_at DESC LIMIT 8")->fetchAll();
    } catch (Throwable $e) {}
}

$page_title = 'Dashboard';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'dashboard');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Dashboard</h1>
        <p class="adm-page-sub">Загальний огляд CSHunter</p>
    </div>
    <div class="adm-page-actions">
        <?php if ($downServers): ?>
            <a href="servers_live.php" class="adm-badge adm-badge-red" style="text-decoration:none">
                ⚠ <?= count($downServers) ?> сервер не відповідає
            </a>
        <?php else: ?>
            <span class="adm-badge adm-badge-green">● Всі сервери онлайн</span>
        <?php endif; ?>
    </div>
</div>

<?php if ($downServers): ?>
<div class="adm-alert adm-alert-error">
    <strong>⚠ Не відповідають:</strong>
    <?= implode(', ', array_map(fn($s) => htmlspecialchars($s['name']), $downServers)) ?>
    · <a href="servers_live.php" style="color:var(--red)">Детальніше →</a>
</div>
<?php endif; ?>

<?php if (!empty($dbSyncWarning)): ?>
<div class="adm-alert adm-alert-warn">
    <strong>⚠ БД не синхронізована:</strong>
    Таблиця <code>servers</code> недоступна — кількість серверів береться з <code>servers_config.php</code>.
    Запустіть міграцію або перевірте підключення до БД.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="adm-stats-grid">
    <div class="adm-stat-card">
        <div class="adm-stat-icon adm-stat-icon--blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= number_format($stats['users']) ?></div>
            <div class="adm-stat-lbl">Зареєстрованих</div>
        </div>
        <div class="adm-stat-trend adm-stat-trend--up">+<?= $stats['new_today'] ?> сьогодні</div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon adm-stat-icon--green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $stats['servers'] ?></div>
            <div class="adm-stat-lbl">Активних серверів</div>
        </div>
        <div class="adm-stat-trend <?= $downServers ? 'adm-stat-trend--down' : '' ?>">
            <?= $downServers ? count($downServers) . ' не відповідає' : 'Всі режими' ?>
        </div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon adm-stat-icon--yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $stats['current_online'] ?></div>
            <div class="adm-stat-lbl">Онлайн зараз</div>
        </div>
        <div class="adm-stat-trend adm-stat-trend--up">Пік сьогодні: <?= $stats['peak_online'] ?></div>
    </div>
    <div class="adm-stat-card">
        <div class="adm-stat-icon adm-stat-icon--red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $stats['banned'] ?></div>
            <div class="adm-stat-lbl">Забанено</div>
        </div>
        <div class="adm-stat-trend"><a href="bans.php" style="color:var(--text-3)">Керувати →</a></div>
    </div>
</div>

<!-- Chart -->
<?php if (!empty($chartDays)): ?>
<div class="adm-card" style="margin-bottom:20px">
    <div class="adm-card-header">
        <span class="adm-card-title">Реєстрації за 14 днів</span>
        <a href="users.php" class="adm-link">Всі гравці →</a>
    </div>
    <div style="padding:20px 20px 12px">
        <canvas id="regChart" height="80"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- Two columns -->
<div class="adm-grid-2">
    <div class="adm-card">
        <div class="adm-card-header">
            <span class="adm-card-title">Останні входи</span>
            <a href="users.php" class="adm-link">Всі →</a>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead><tr><th>Гравець</th><th>Роль</th><th>Вхід</th></tr></thead>
                <tbody>
                <?php foreach ($recent_users as $u): ?>
                <tr>
                    <td>
                        <div class="adm-user-cell">
                            <img src="<?= htmlspecialchars($u['avatar_url']??'') ?>" class="adm-avatar" alt="">
                            <div>
                                <div class="adm-user-name"><?= htmlspecialchars(mb_substr($u['steam_name'],0,20)) ?></div>
                                <div class="adm-user-sub"><?= htmlspecialchars($u['steam_id']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['role']==='admin'): ?><span class="adm-badge adm-badge-red">Admin</span>
                        <?php elseif ($u['role']==='moderator'): ?><span class="adm-badge adm-badge-blue">Mod</span>
                        <?php else: ?><span class="adm-badge adm-badge-gray">Player</span><?php endif; ?>
                    </td>
                    <td class="adm-text-muted adm-text-sm"><?= $u['last_login'] ? date('d.m H:i',strtotime($u['last_login'])) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_users)): ?><tr><td colspan="3" class="adm-empty">Немає даних</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="adm-card">
        <div class="adm-card-header">
            <span class="adm-card-title">Останні дії</span>
            <a href="logs.php" class="adm-link">Всі →</a>
        </div>
        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead><tr><th>Дія</th><th>Адмін</th><th>Час</th></tr></thead>
                <tbody>
                <?php foreach ($recent_logs as $log): ?>
                <?php $lbl = $actionLabels[$log['action']] ?? $log['action']; ?>
                <tr>
                    <td>
                        <div class="adm-text-sm" style="font-weight:500"><?= htmlspecialchars($lbl) ?></div>
                        <?php if ($log['target']): ?>
                        <div class="adm-user-sub"><?= htmlspecialchars(mb_substr($log['target'],0,25)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="adm-text-sm"><?= htmlspecialchars(mb_substr($log['admin_name'],0,16)) ?></td>
                    <td class="adm-text-muted adm-text-sm"><?= date('d.m H:i',strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_logs)): ?><tr><td colspan="3" class="adm-empty">Порожньо</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (!empty($chartDays)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('regChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($chartDays) ?>,
        datasets: [{
            label: 'Входів',
            data: <?= json_encode($chartCounts) ?>,
            backgroundColor: 'rgba(79,142,247,.35)',
            borderColor: 'rgba(79,142,247,.8)',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#5c677d', font: { size: 11 } } },
            y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: '#5c677d', font: { size: 11 }, stepSize: 1 }, beginAtZero: true }
        }
    }
});
</script>
<?php endif; ?>

<style>
.adm-stat-trend--down { color: var(--red) !important; }
</style>

<?php adminLayoutEnd(); ?>
