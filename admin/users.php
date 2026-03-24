<?php
require_once __DIR__ . '/_middleware.php';
$admin = requireAdmin();

$msg = '';
$error = '';

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminVerifyCsrf();
    $action   = $_POST['action']   ?? '';
    $targetId = $_POST['steam_id'] ?? '';

    if (!preg_match('/^\d{17}$/', $targetId)) {
        $error = 'Невірний Steam ID';
    } elseif ($targetId === $admin['steam_id'] && in_array($action, ['ban','set_role'])) {
        $error = 'Не можна змінити власні права';
    } else {
        if ($action === 'ban') {
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 500);
            $until  = !empty($_POST['until']) ? $_POST['until'] : null;
            $pdo->prepare("UPDATE users SET is_banned=1, ban_reason=? WHERE steam_id=?")->execute([$reason, $targetId]);
            $pdo->prepare("INSERT INTO user_bans (steam_id, reason, banned_by, banned_until) VALUES (?,?,?,?)")->execute([$targetId, $reason, $admin['steam_id'], $until]);
            adminLog('ban_user', $targetId, ['reason' => $reason, 'until' => $until]);
            $msg = 'Гравця забанено';
        } elseif ($action === 'unban') {
            $pdo->prepare("UPDATE users SET is_banned=0, ban_reason=NULL WHERE steam_id=?")->execute([$targetId]);
            $pdo->prepare("UPDATE user_bans SET is_active=0 WHERE steam_id=?")->execute([$targetId]);
            adminLog('unban_user', $targetId);
            $msg = 'Бан знятий';
        } elseif ($action === 'set_role') {
            $role = $_POST['role'] ?? 'player';
            if (!in_array($role, ['player','moderator','admin'])) $role = 'player';
            $pdo->prepare("UPDATE users SET role=? WHERE steam_id=?")->execute([$role, $targetId]);
            adminLog('set_role', $targetId, ['role' => $role]);
            $msg = 'Роль змінено на ' . $role;
            if ($targetId === $admin['steam_id']) $_SESSION['user']['role'] = $role;
        } elseif ($action === 'clear_skins') {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE steam_id=?");
            $stmt->execute([$targetId]);
            $uid = $stmt->fetchColumn();
            if ($uid) {
                $pdo->prepare("DELETE FROM skin_selections WHERE user_id=?")->execute([$uid]);
                adminLog('clear_skins', $targetId);
                $msg = 'Скіни очищено';
            }
        }
    }
}

// ── Bulk actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk') {
    adminVerifyCsrf();
    $bulkAction = $_POST['bulk_action'] ?? '';
    $selected   = array_filter($_POST['selected'] ?? [], fn($id) => preg_match('/^\d{17}$/', $id));
    // Remove self from bulk actions
    $selected = array_filter($selected, fn($id) => $id !== $admin['steam_id']);

    if ($selected && in_array($bulkAction, ['ban','unban','clear_skins'])) {
        $count = 0;
        foreach ($selected as $steamId) {
            if ($bulkAction === 'ban') {
                $reason = 'Масовий бан адміном ' . $admin['steam_name'];
                $pdo->prepare("UPDATE users SET is_banned=1, ban_reason=? WHERE steam_id=?")->execute([$reason, $steamId]);
                $pdo->prepare("INSERT INTO user_bans (steam_id,reason,banned_by) VALUES (?,?,?)")->execute([$steamId,$reason,$admin['steam_id']]);
                $count++;
            } elseif ($bulkAction === 'unban') {
                $pdo->prepare("UPDATE users SET is_banned=0, ban_reason=NULL WHERE steam_id=?")->execute([$steamId]);
                $pdo->prepare("UPDATE user_bans SET is_active=0 WHERE steam_id=?")->execute([$steamId]);
                $count++;
            } elseif ($bulkAction === 'clear_skins') {
                $uid = $pdo->prepare("SELECT id FROM users WHERE steam_id=?");
                $uid->execute([$steamId]);
                $uid = $uid->fetchColumn();
                if ($uid) { $pdo->prepare("DELETE FROM skin_selections WHERE user_id=?")->execute([$uid]); $count++; }
            }
        }
        adminLog('bulk_' . $bulkAction, implode(',', $selected), ['count' => $count]);
        $msg = "Виконано для $count гравців";
    }
}

// ── Filters ───────────────────────────────────────────────────────────────────
$search    = trim($_GET['q']       ?? '');
$roleF     = trim($_GET['role']    ?? '');
$countryF  = trim($_GET['country'] ?? '');
$statusF   = trim($_GET['status']  ?? ''); // 'banned' | 'active' | ''
$page      = max(1, (int)($_GET['p'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

$where  = ['has_logged_in = 1'];
$params = [];

if ($search) {
    $where[]  = '(steam_name LIKE ? OR steam_id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleF)    { $where[] = 'role = ?';      $params[] = $roleF; }
if ($countryF) { $where[] = 'country = ?';   $params[] = $countryF; }
if ($statusF === 'banned') { $where[] = 'is_banned = 1'; }
if ($statusF === 'active') { $where[] = 'is_banned = 0'; }

$whereSQL = implode(' AND ', $where);

// Get available countries for filter dropdown
$countries = [];
if ($pdo) {
    try {
        $countries = $pdo->query("SELECT DISTINCT country FROM users WHERE has_logged_in=1 AND country != '' ORDER BY country")->fetchAll(\PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}
}

$total = 0;
$users = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSQL");
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        $stmt2 = $pdo->prepare("SELECT * FROM users WHERE $whereSQL ORDER BY last_login DESC LIMIT $perPage OFFSET $offset");
        $stmt2->execute($params);
        $users = $stmt2->fetchAll();
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$totalPages = max(1, ceil($total / $perPage));
$hasFilters = $search || $roleF || $countryF || $statusF;

$page_title = 'Користувачі';
require_once __DIR__ . '/_layout.php'; adminLayoutStart($page_title, 'users');
?>

<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">Користувачі</h1>
        <p class="adm-page-sub">Знайдено: <?= number_format($total) ?></p>
    </div>
</div>

<?php if ($msg): ?><div class="adm-alert adm-alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($error): ?><div class="adm-alert adm-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Filters -->
<div class="adm-filters-block">
    <form method="GET" id="usersFilterForm">
        <div class="adm-filters-row">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Пошук по імені або Steam ID…" class="adm-input adm-input-search"
                   oninput="debounceSubmit()">

            <!-- Role — вибір одразу фільтрує -->
            <select name="role" class="adm-select" onchange="this.form.submit()">
                <option value="">Всі ролі</option>
                <option value="player"    <?= $roleF==='player'    ? 'selected':'' ?>>👤 Player</option>
                <option value="moderator" <?= $roleF==='moderator' ? 'selected':'' ?>>🛡 Moderator</option>
                <option value="admin"     <?= $roleF==='admin'     ? 'selected':'' ?>>⚡ Admin</option>
            </select>

            <!-- Status -->
            <select name="status" class="adm-select" onchange="this.form.submit()">
                <option value="">Всі статуси</option>
                <option value="active" <?= $statusF==='active' ? 'selected':'' ?>>✅ Active</option>
                <option value="banned" <?= $statusF==='banned' ? 'selected':'' ?>>🚫 Banned</option>
            </select>

            <!-- Country -->
            <select name="country" class="adm-select" onchange="this.form.submit()">
                <option value="">Всі країни</option>
                <?php foreach ($countries as $c): ?>
                <option value="<?= htmlspecialchars($c) ?>" <?= $countryF===$c ? 'selected':'' ?>>
                    <?= htmlspecialchars($c) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <?php if ($hasFilters): ?>
                <a href="users.php" class="adm-btn adm-btn-ghost">✕ Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bulk action bar (hidden until selection) -->
<div id="bulkBar" style="display:none;margin-bottom:12px">
    <form method="POST" id="bulkForm">
        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
        <input type="hidden" name="action" value="bulk">
        <div id="bulkSelectedHidden"></div>
        <div style="display:flex;align-items:center;gap:10px;background:rgba(79,142,247,.1);border:1px solid rgba(79,142,247,.25);border-radius:8px;padding:10px 16px">
            <span id="bulkCount" class="adm-text-sm" style="color:var(--accent);font-weight:600"></span>
            <select name="bulk_action" class="adm-select adm-select-sm">
                <option value="">Оберіть дію…</option>
                <option value="ban">🚫 Забанити</option>
                <option value="unban">✅ Розбанити</option>
                <option value="clear_skins">🗑 Очистити скіни</option>
            </select>
            <button type="submit" class="adm-btn adm-btn-secondary adm-btn-sm"
                    onclick="return confirm('Виконати для вибраних гравців?')">
                Виконати
            </button>
            <button type="button" class="adm-btn adm-btn-ghost adm-btn-sm" onclick="clearSelection()">Скинути</button>
        </div>
    </form>
</div>

<!-- Table -->
<div class="adm-card">
    <div class="adm-table-wrap">
        <table class="adm-table adm-table-hover">
            <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                    <th>Гравець</th>
                    <th>Роль</th>
                    <th>Країна</th>
                    <th>Реєстрація</th>
                    <th>Останній вхід</th>
                    <th>Статус</th>
                    <th>Дії</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="<?= $u['is_banned'] ? 'adm-row-banned' : '' ?>">
                <td><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($u['steam_id']) ?>" class="row-check"></td>
                <td>
                    <div class="adm-user-cell">
                        <img src="<?= htmlspecialchars($u['avatar_url'] ?? '') ?>" class="adm-avatar adm-avatar-lg" alt=""
                             onerror="this.src='data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 40 40\'><rect fill=\'%23333\' width=\'40\' height=\'40\'/></svg>'">
                        <div>
                            <div class="adm-user-name"><?= htmlspecialchars($u['steam_name']) ?></div>
                            <div class="adm-user-sub"><?= htmlspecialchars($u['steam_id']) ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <?php if ($u['role']==='admin'): ?>
                        <span class="adm-badge adm-badge-red">Admin</span>
                    <?php elseif ($u['role']==='moderator'): ?>
                        <span class="adm-badge adm-badge-blue">Mod</span>
                    <?php else: ?>
                        <span class="adm-badge adm-badge-gray">Player</span>
                    <?php endif; ?>
                </td>
                <td class="adm-text-sm"><?= htmlspecialchars($u['country'] ?: '—') ?></td>
                <td class="adm-text-muted adm-text-sm"><?= $u['created_at'] ? date('d.m.y', strtotime($u['created_at'])) : '—' ?></td>
                <td class="adm-text-muted adm-text-sm"><?= $u['last_login'] ? date('d.m.y H:i', strtotime($u['last_login'])) : '—' ?></td>
                <td>
                    <?php if ($u['is_banned']): ?>
                        <span class="adm-badge adm-badge-red" title="<?= htmlspecialchars($u['ban_reason'] ?? '') ?>">Banned</span>
                    <?php else: ?>
                        <span class="adm-badge adm-badge-green">Active</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['steam_id'] !== $admin['steam_id']): ?>
                    <div class="adm-action-group">
                        <button class="adm-btn adm-btn-xs adm-btn-ghost"
                                onclick="openUserModal('<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>')">
                            Керувати
                        </button>
                        <a href="<?= SITE_URL ?>/profile/<?= $u['steam_id'] ?>" target="_blank"
                           class="adm-btn adm-btn-xs adm-btn-ghost">↗</a>
                    </div>
                    <?php else: ?>
                        <span class="adm-text-muted adm-text-sm">Це ви</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
            <tr><td colspan="7" class="adm-empty">Гравців не знайдено</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="adm-pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++):
            $qs = http_build_query(array_filter(['q'=>$search,'role'=>$roleF,'country'=>$countryF,'status'=>$statusF,'p'=>$i]));
        ?>
            <a href="?<?= $qs ?>" class="adm-page-btn <?= $i===$page ? 'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal -->
<div class="adm-modal-overlay" id="userModal" onclick="if(event.target===this)closeUserModal()">
    <div class="adm-modal">
        <div class="adm-modal-header">
            <div class="adm-user-cell">
                <img id="modal-avatar" src="" class="adm-avatar adm-avatar-lg" alt="">
                <div>
                    <div class="adm-modal-title" id="modal-name"></div>
                    <div class="adm-user-sub" id="modal-steamid"></div>
                </div>
            </div>
            <button onclick="closeUserModal()" class="adm-modal-close">✕</button>
        </div>
        <div class="adm-modal-body">
            <div class="adm-modal-section">
                <div class="adm-modal-section-title">Роль</div>
                <form method="POST" class="adm-inline-form">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="action" value="set_role">
                    <input type="hidden" name="steam_id" id="role-steam-id">
                    <select name="role" id="role-select" class="adm-select">
                        <option value="player">Player</option>
                        <option value="moderator">Moderator</option>
                        <option value="admin">Admin</option>
                    </select>
                    <button type="submit" class="adm-btn adm-btn-secondary">Зберегти</button>
                </form>
            </div>
            <div class="adm-modal-section">
                <div class="adm-modal-section-title">Бан</div>
                <div id="ban-form-wrap">
                    <form method="POST" class="adm-stack-form">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="ban">
                        <input type="hidden" name="steam_id" id="ban-steam-id">
                        <input type="text" name="reason" placeholder="Причина бану…" class="adm-input" required>
                        <div class="adm-inline-form">
                            <label class="adm-label">До (порожньо = перманентний)</label>
                            <input type="datetime-local" name="until" class="adm-input">
                        </div>
                        <button type="submit" class="adm-btn adm-btn-danger">Забанити</button>
                    </form>
                </div>
                <div id="unban-form-wrap" style="display:none">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                        <input type="hidden" name="action" value="unban">
                        <input type="hidden" name="steam_id" id="unban-steam-id">
                        <p class="adm-text-muted adm-text-sm" id="ban-reason-display" style="margin-bottom:10px"></p>
                        <button type="submit" class="adm-btn adm-btn-secondary">Зняти бан</button>
                    </form>
                </div>
            </div>
            <div class="adm-modal-section">
                <div class="adm-modal-section-title">Інше</div>
                <form method="POST" onsubmit="return confirm('Очистити всі скіни?')">
                    <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                    <input type="hidden" name="action" value="clear_skins">
                    <input type="hidden" name="steam_id" id="skins-steam-id">
                    <button type="submit" class="adm-btn adm-btn-ghost">🗑 Очистити скіни</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.adm-filters-block { margin-bottom: 16px; }
.adm-filters-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
</style>

<script>
let debounceTimer;
function debounceSubmit() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => document.getElementById('usersFilterForm').submit(), 500);
}

function openUserModal(jsonStr) {
    const u = JSON.parse(jsonStr);
    document.getElementById('modal-avatar').src       = u.avatar_url || '';
    document.getElementById('modal-name').textContent  = u.steam_name;
    document.getElementById('modal-steamid').textContent = u.steam_id;
    document.getElementById('role-steam-id').value    = u.steam_id;
    document.getElementById('role-select').value       = u.role || 'player';
    document.getElementById('ban-steam-id').value      = u.steam_id;
    document.getElementById('unban-steam-id').value    = u.steam_id;
    document.getElementById('skins-steam-id').value    = u.steam_id;
    const isBanned = parseInt(u.is_banned) === 1;
    document.getElementById('ban-form-wrap').style.display   = isBanned ? 'none' : '';
    document.getElementById('unban-form-wrap').style.display = isBanned ? '' : 'none';
    if (isBanned && u.ban_reason) {
        document.getElementById('ban-reason-display').textContent = 'Причина: ' + u.ban_reason;
    }
    document.getElementById('userModal').classList.add('active');
}
function closeUserModal() { document.getElementById('userModal').classList.remove('active'); }
document.addEventListener('keydown', e => { if (e.key==='Escape') closeUserModal(); });

// ── Bulk selection ────────────────────────────────────────────────────────────
function toggleAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}
document.addEventListener('change', e => {
    if (e.target.classList.contains('row-check')) updateBulkBar();
});
function updateBulkBar() {
    const checked = [...document.querySelectorAll('.row-check:checked')];
    const bar     = document.getElementById('bulkBar');
    const count   = document.getElementById('bulkCount');
    const hidden  = document.getElementById('bulkSelectedHidden');
    bar.style.display   = checked.length ? '' : 'none';
    count.textContent   = `Вибрано: ${checked.length}`;
    hidden.innerHTML    = checked.map(cb =>
        `<input type="hidden" name="selected[]" value="${cb.value}">`).join('');
}
function clearSelection() {
    document.querySelectorAll('.row-check, #selectAll').forEach(cb => cb.checked = false);
    document.getElementById('bulkBar').style.display = 'none';
}
</script>

<?php adminLayoutEnd(); ?>
