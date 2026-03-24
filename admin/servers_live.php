<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

// ── Load servers with live status ─────────────────────────────────────────────
$servers = [];
if ($pdo) {
    try {
        $servers = $pdo->query("
            SELECT
                s.id, s.name, s.ip, s.port, s.tags, s.active,
                m.slug, m.name AS mode_name, m.tag AS mode_tag,
                c.online, c.players, c.max_players, c.map, c.ping, c.updated_at,
                TIMESTAMPDIFF(SECOND, c.updated_at, NOW()) AS seconds_ago
            FROM servers s
            JOIN modes m ON m.id = s.mode_id
            LEFT JOIN server_status_cache c ON c.ip = s.ip AND c.port = s.port
            WHERE s.active = 1
            ORDER BY m.slug, s.sort_order, s.id
        ")->fetchAll();
    } catch (Throwable $e) {}
}

// Count alerts (servers down > 5 min)
$downServers = array_filter($servers, fn($s) =>
    ($s['online'] == 0 || $s['seconds_ago'] > 300) && $s['active']
);

$page_title = 'Сервери Live';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'servers_live');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Сервери Live</h1>
        <p class="adm-page-sub">Оновлюється автоматично · крон кожні ~30 сек</p>
    </div>
    <div class="adm-page-actions">
        <?php if ($downServers): ?>
            <span class="adm-badge adm-badge-red">
                ⚠ <?= count($downServers) ?> сервер<?= count($downServers) > 1 ? 'и' : '' ?> не відповідає
            </span>
        <?php else: ?>
            <span class="adm-badge adm-badge-green">● Всі онлайн</span>
        <?php endif; ?>
        <a href="servers.php" class="adm-btn adm-btn-ghost adm-btn-sm">⚙ Налаштування</a>
    </div>
</div>

<?php if ($downServers): ?>
<div class="adm-alert adm-alert-error" style="margin-bottom:20px">
    <strong>⚠ Не відповідають:</strong>
    <?= implode(', ', array_map(fn($s) => htmlspecialchars($s['name']), $downServers)) ?>
</div>
<?php endif; ?>

<!-- Group by mode -->
<?php
$byMode = [];
foreach ($servers as $s) {
    $byMode[$s['slug']][] = $s;
}
?>

<?php foreach ($byMode as $slug => $modeServers):
    $first = $modeServers[0];
    $totalPlayers = array_sum(array_column($modeServers, 'players'));
?>
<div class="adm-card" style="margin-bottom:16px">
    <div class="adm-card-header">
        <div class="adm-mode-header-left">
            <span class="adm-badge adm-badge-yellow"><?= htmlspecialchars($first['mode_tag']) ?></span>
            <span class="adm-card-title"><?= htmlspecialchars($first['mode_name']) ?></span>
            <span class="adm-server-count"><?= count($modeServers) ?> серв · <?= $totalPlayers ?> онлайн</span>
        </div>
    </div>

    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Сервер</th>
                    <th>Статус</th>
                    <th>Карта</th>
                    <th>Гравці</th>
                    <th>Пінг</th>
                    <th>Оновлено</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($modeServers as $s):
                $isStale   = $s['seconds_ago'] === null || $s['seconds_ago'] > 300;
                $isOnline  = $s['online'] && !$isStale;
                $fillPct   = $s['max_players'] > 0 ? round(($s['players'] / $s['max_players']) * 100) : 0;
                $fillColor = $fillPct >= 90 ? 'var(--red)' : ($fillPct >= 60 ? 'var(--yellow)' : 'var(--green)');
            ?>
            <tr class="<?= !$isOnline ? 'adm-row-offline' : '' ?>">
                <td>
                    <div class="adm-user-name"><?= htmlspecialchars($s['name']) ?></div>
                    <div class="adm-user-sub" style="font-family:var(--mono)">
                        <?= htmlspecialchars($s['ip']) ?>:<?= $s['port'] ?>
                    </div>
                </td>
                <td>
                    <?php if ($isOnline): ?>
                        <span class="adm-status-dot adm-status-dot--green"></span>
                        <span class="adm-text-sm" style="color:var(--green)">Online</span>
                    <?php elseif ($isStale && $s['online']): ?>
                        <span class="adm-status-dot adm-status-dot--yellow"></span>
                        <span class="adm-text-sm" style="color:var(--yellow)">Немає даних</span>
                    <?php else: ?>
                        <span class="adm-status-dot adm-status-dot--red"></span>
                        <span class="adm-text-sm" style="color:var(--red)">Offline</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-sm"><?= htmlspecialchars($s['map'] ?: '—') ?></td>
                <td>
                    <?php if ($isOnline && $s['max_players'] > 0): ?>
                    <div class="adm-players-bar">
                        <div class="adm-players-bar-fill" style="width:<?= $fillPct ?>%;background:<?= $fillColor ?>"></div>
                    </div>
                    <div class="adm-text-sm adm-text-muted" style="margin-top:3px">
                        <?= $s['players'] ?> / <?= $s['max_players'] ?>
                    </div>
                    <?php else: ?>
                        <span class="adm-text-muted adm-text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-sm">
                    <?php if ($s['ping'] !== null && $isOnline): ?>
                        <span style="color:<?= $s['ping'] < 50 ? 'var(--green)' : ($s['ping'] < 100 ? 'var(--yellow)' : 'var(--red)') ?>">
                            <?= (int)$s['ping'] ?>ms
                        </span>
                    <?php else: ?>
                        <span class="adm-text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-muted adm-text-sm">
                    <?php if ($s['updated_at']): ?>
                        <?php $ago = (int)$s['seconds_ago']; ?>
                        <?= $ago < 60 ? $ago . 'с тому' : floor($ago/60) . 'хв тому' ?>
                    <?php else: ?>
                        Ніколи
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($servers)): ?>
<div class="adm-card">
    <div class="adm-empty">Немає активних серверів</div>
</div>
<?php endif; ?>

<style>
.adm-row-offline td { opacity: .5; }

.adm-status-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    margin-right: 5px;
    vertical-align: middle;
}
.adm-status-dot--green  { background: var(--green);  box-shadow: 0 0 6px var(--green); }
.adm-status-dot--yellow { background: var(--yellow); box-shadow: 0 0 6px var(--yellow); }
.adm-status-dot--red    { background: var(--red);    box-shadow: 0 0 6px var(--red); }

.adm-players-bar {
    width: 80px; height: 4px;
    background: rgba(255,255,255,.08);
    border-radius: 2px;
    overflow: hidden;
}
.adm-players-bar-fill {
    height: 100%;
    border-radius: 2px;
    transition: width .3s ease;
}
</style>

<script>
// Auto-refresh every 35 seconds (slightly longer than cron interval)
setTimeout(() => location.reload(), 35000);

// Countdown timer
let countdown = 35;
const timer = setInterval(() => {
    countdown--;
    const el = document.querySelector('.adm-page-sub');
    if (el) el.textContent = `Оновлюється автоматично · наступне оновлення через ${countdown}с`;
    if (countdown <= 0) clearInterval(timer);
}, 1000);
</script>

<?php adminLayoutEnd(); ?>
