<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

$msg = '';
$error = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'unban') {
        $steamId = $_POST['steam_id'] ?? '';
        if (preg_match('/^\d{17}$/', $steamId)) {
            $pdo->prepare("UPDATE users SET is_banned=0, ban_reason=NULL WHERE steam_id=?")->execute([$steamId]);
            $pdo->prepare("UPDATE user_bans SET is_active=0 WHERE steam_id=?")->execute([$steamId]);
            adminLog('unban_user', $steamId);
            $msg = 'Бан знятий з ' . $steamId;
        }
    } elseif ($action === 'edit_ban') {
        $banId  = (int)($_POST['ban_id'] ?? 0);
        $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 500);
        $until  = !empty($_POST['until']) ? $_POST['until'] : null;
        if ($banId) {
            $pdo->prepare("UPDATE user_bans SET reason=?, banned_until=? WHERE id=?")->execute([$reason, $until, $banId]);
            adminLog('edit_ban', (string)$banId, ['reason' => $reason, 'until' => $until]);
            $msg = 'Бан оновлено';
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search   = trim($_GET['q']      ?? '');
$typeF    = trim($_GET['type']   ?? ''); // 'permanent' | 'temporary' | 'expired'
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 20;
$offset   = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(b.steam_id LIKE ? OR u.steam_name LIKE ? OR b.reason LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
if ($typeF === 'permanent') { $where[] = 'b.banned_until IS NULL AND b.is_active=1'; }
if ($typeF === 'temporary')  { $where[] = 'b.banned_until IS NOT NULL AND b.banned_until > NOW() AND b.is_active=1'; }
if ($typeF === 'expired')    { $where[] = '(b.banned_until IS NOT NULL AND b.banned_until <= NOW()) OR b.is_active=0'; }
if ($typeF === 'active' || $typeF === '')    {
    if ($typeF === 'active') $where[] = 'b.is_active=1 AND (b.banned_until IS NULL OR b.banned_until > NOW())';
}

$whereSQL = implode(' AND ', $where);

$total = 0;
$bans  = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_bans b
            LEFT JOIN users u ON u.steam_id = b.steam_id
            WHERE $whereSQL
        ");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $stmt2 = $pdo->prepare("
            SELECT b.*, u.steam_name, u.avatar_url,
                   ab.steam_name AS banned_by_name
            FROM user_bans b
            LEFT JOIN users u  ON u.steam_id = b.steam_id
            LEFT JOIN users ab ON ab.steam_id = b.banned_by
            WHERE $whereSQL
            ORDER BY b.created_at DESC
            LIMIT $perPage OFFSET $offset
        ");
        $stmt2->execute($params);
        $bans = $stmt2->fetchAll();
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

// Stats
$banStats = ['total' => 0, 'active' => 0, 'permanent' => 0, 'expired' => 0];
if ($pdo) {
    try {
        $banStats['total']     = (int)$pdo->query("SELECT COUNT(*) FROM user_bans")->fetchColumn();
        $banStats['active']    = (int)$pdo->query("SELECT COUNT(*) FROM user_bans WHERE is_active=1 AND (banned_until IS NULL OR banned_until > NOW())")->fetchColumn();
        $banStats['permanent'] = (int)$pdo->query("SELECT COUNT(*) FROM user_bans WHERE is_active=1 AND banned_until IS NULL")->fetchColumn();
        $banStats['expired']   = (int)$pdo->query("SELECT COUNT(*) FROM user_bans WHERE is_active=0 OR (banned_until IS NOT NULL AND banned_until <= NOW())")->fetchColumn();
    } catch (Throwable $e) {}
}

$totalPages = max(1, ceil($total / $perPage));
$hasFilters = $search || $typeF;

$page_title = 'Бани';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'bans');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Управління банами</h1>
        <p class="adm-page-sub">Всього банів: <?= number_format($banStats['total']) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="adm-alert adm-alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="adm-alert adm-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Quick stats -->
<div class="adm-stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <a href="bans.php?type=active" class="adm-stat-card" style="text-decoration:none;cursor:pointer">
        <div class="adm-stat-icon adm-stat-icon--red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $banStats['active'] ?></div>
            <div class="adm-stat-lbl">Активних</div>
        </div>
    </a>
    <a href="bans.php?type=permanent" class="adm-stat-card" style="text-decoration:none;cursor:pointer">
        <div class="adm-stat-icon adm-stat-icon--red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $banStats['permanent'] ?></div>
            <div class="adm-stat-lbl">Перманентних</div>
        </div>
    </a>
    <a href="bans.php?type=temporary" class="adm-stat-card" style="text-decoration:none;cursor:pointer">
        <div class="adm-stat-icon adm-stat-icon--yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $banStats['active'] - $banStats['permanent'] ?></div>
            <div class="adm-stat-lbl">Тимчасових</div>
        </div>
    </a>
    <a href="bans.php?type=expired" class="adm-stat-card" style="text-decoration:none;cursor:pointer">
        <div class="adm-stat-icon adm-stat-icon--green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="adm-stat-body">
            <div class="adm-stat-num"><?= $banStats['expired'] ?></div>
            <div class="adm-stat-lbl">Знятих/Протермінованих</div>
        </div>
    </a>
</div>

<!-- Filters -->
<div style="margin-bottom:16px">
    <form method="GET" id="bansForm">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Steam ID, ім'я, причина…" class="adm-input" style="width:260px"
                   oninput="debounceSubmit()">
            <select name="type" class="adm-select" onchange="this.form.submit()">
                <option value="">Всі бани</option>
                <option value="active"    <?= $typeF==='active'    ? 'selected':'' ?>>Активні</option>
                <option value="permanent" <?= $typeF==='permanent' ? 'selected':'' ?>>Перманентні</option>
                <option value="temporary" <?= $typeF==='temporary' ? 'selected':'' ?>>Тимчасові</option>
                <option value="expired"   <?= $typeF==='expired'   ? 'selected':'' ?>>Знятий/Протерміновані</option>
            </select>
            <?php if ($hasFilters): ?>
                <a href="bans.php" class="adm-btn adm-btn-ghost">✕ Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table adm-table-hover">
            <thead>
                <tr>
                    <th>Гравець</th>
                    <th>Причина</th>
                    <th>Тип</th>
                    <th>Термін</th>
                    <th>Забанив</th>
                    <th>Дата</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bans as $ban):
                $isExpired  = !$ban['is_active'] || ($ban['banned_until'] && strtotime($ban['banned_until']) < time());
                $isPermanent = $ban['is_active'] && !$ban['banned_until'];
            ?>
            <tr class="<?= $isExpired ? 'adm-row-banned' : '' ?>">
                <td>
                    <div class="adm-user-cell">
                        <?php if ($ban['avatar_url']): ?>
                        <img src="<?= htmlspecialchars($ban['avatar_url']) ?>" class="adm-avatar adm-avatar-lg" alt="">
                        <?php endif; ?>
                        <div>
                            <div class="adm-user-name"><?= htmlspecialchars($ban['steam_name'] ?? 'Невідомий') ?></div>
                            <div class="adm-user-sub"><?= htmlspecialchars($ban['steam_id']) ?></div>
                        </div>
                    </div>
                </td>
                <td class="adm-text-sm" style="max-width:200px">
                    <?= htmlspecialchars(mb_substr($ban['reason'] ?: '—', 0, 80)) ?>
                </td>
                <td>
                    <?php if ($isExpired): ?>
                        <span class="adm-badge adm-badge-gray">Знятий</span>
                    <?php elseif ($isPermanent): ?>
                        <span class="adm-badge adm-badge-red">Перманентний</span>
                    <?php else: ?>
                        <span class="adm-badge adm-badge-yellow">Тимчасовий</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-sm adm-text-muted">
                    <?= $ban['banned_until'] ? date('d.m.y H:i', strtotime($ban['banned_until'])) : ($isExpired ? '—' : '∞') ?>
                </td>
                <td class="adm-text-sm"><?= htmlspecialchars($ban['banned_by_name'] ?? $ban['banned_by']) ?></td>
                <td class="adm-text-muted adm-text-sm"><?= date('d.m.y H:i', strtotime($ban['created_at'])) ?></td>
                <td>
                    <div class="adm-action-group">
                        <?php if (!$isExpired): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Зняти бан?')">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <input type="hidden" name="action"   value="unban">
                            <input type="hidden" name="steam_id" value="<?= htmlspecialchars($ban['steam_id']) ?>">
                            <button class="adm-btn adm-btn-xs adm-btn-secondary">Зняти</button>
                        </form>
                        <button class="adm-btn adm-btn-xs adm-btn-ghost"
                                onclick="openEditBan(<?= htmlspecialchars(json_encode($ban), ENT_QUOTES) ?>)">
                            Редагувати
                        </button>
                        <?php endif; ?>
                        <a href="<?= SITE_URL ?>/profile/<?= $ban['steam_id'] ?>" target="_blank"
                           class="adm-btn adm-btn-xs adm-btn-ghost">↗</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bans)): ?>
            <tr><td colspan="7" class="adm-empty">Банів не знайдено</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="adm-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $qs = http_build_query(array_filter(['q'=>$search,'type'=>$typeF,'p'=>$i]));
        ?>
            <a href="?<?= $qs ?>" class="adm-page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Edit ban modal -->
<div class="adm-modal-overlay" id="editBanModal" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <div class="adm-modal-title">Редагувати бан</div>
            <button onclick="document.getElementById('editBanModal').classList.remove('active')" class="adm-modal-close">✕</button>
        </div>
        <div class="adm-modal-body">
            <form method="POST" class="adm-stack-form">
                <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                <input type="hidden" name="action" value="edit_ban">
                <input type="hidden" name="ban_id" id="edit-ban-id">
                <div>
                    <label class="adm-label">Причина</label>
                    <input type="text" name="reason" id="edit-ban-reason" class="adm-input" required>
                </div>
                <div>
                    <label class="adm-label">Термін (порожньо = перманентний)</label>
                    <input type="datetime-local" name="until" id="edit-ban-until" class="adm-input">
                </div>
                <button type="submit" class="adm-btn adm-btn-primary">Зберегти</button>
            </form>
        </div>
    </div>
</div>

<script>
let debounceTimer;
function debounceSubmit() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => document.getElementById('bansForm').submit(), 500);
}
function openEditBan(ban) {
    document.getElementById('edit-ban-id').value     = ban.id;
    document.getElementById('edit-ban-reason').value = ban.reason || '';
    document.getElementById('edit-ban-until').value  = ban.banned_until
        ? ban.banned_until.replace(' ', 'T').slice(0,16) : '';
    document.getElementById('editBanModal').classList.add('active');
}
</script>

<?php adminLayoutEnd(); ?>
