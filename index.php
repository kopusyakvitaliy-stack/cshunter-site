<?php
// ── Front Controller Router ───────────────────────────────────────────────────
// Обробляє чисті URL без змін nginx конфігу.
// nginx: try_files $uri $uri/ /index.php — всі невідомі шляхи сюди.
$_route_uri = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

// /profile/76561198123456789  або  /profile/76561198123456789/friends|skinchanger|profile
if (preg_match('#^/profile/(\d{17})(?:/([a-z]+))?$#', $_route_uri, $_m)) {
    $_GET['id']  = $_m[1];
    $_GET['tab'] = $_m[2] ?? 'profile';
    require __DIR__ . '/profile.php';
    exit;
}

// /servers/surf, /servers/bhop, /servers/kz ...
if (preg_match('#^/servers/([a-z0-9_-]+)$#', $_route_uri, $_m)) {
    $_GET['id'] = $_m[1];
    require __DIR__ . '/mode.php';
    exit;
}

// /skinchanger — редірект на свій профіль вкладка skinchanger
if ($_route_uri === '/skinchanger') {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/includes/auth.php';
    $__me = getUser();
    if ($__me) {
        header('Location: ' . SITE_URL . '/profile/' . $__me['steam_id'] . '/skinchanger');
    } else {
        header('Location: ' . SITE_URL . '/auth/steam_login.php');
    }
    exit;
}

unset($_route_uri, $_m);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Ігрові сервери CS2';

// Ukrainian pluralization: plural_uk(5, 'сервер', 'сервери', 'серверів') → 'серверів'
function plural_uk(int $n, string $one, string $few, string $many): string {
    $mod100 = $n % 100;
    $mod10  = $n % 10;
    if ($mod100 >= 11 && $mod100 <= 20) return $many;
    if ($mod10 === 1) return $one;
    if ($mod10 >= 2 && $mod10 <= 4) return $few;
    return $many;
}
$modes = getDemoModes();

$serversConfig = require __DIR__ . '/servers_config.php';
foreach ($modes as &$m) {
    $m['real_servers']  = $serversConfig[$m['slug']] ?? [];
    $m['servers_count'] = count($m['real_servers']);
}
unset($m);

$total_servers = array_sum(array_column($modes, 'servers_count'));

// ── Початковий онлайн з кешу (рендеримо одразу в PHP, щоб не мигала «—») ──
$initial_online = 0;
if ($pdo && $total_servers > 0) {
    try {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(players), 0)
            FROM server_status_cache
            WHERE online = 1
              AND updated_at >= NOW() - INTERVAL 10 MINUTE
        ");
        $initial_online = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $initial_online = 0;
    }
}

if (isLoggedIn()) getCsrfToken();
session_write_close();
include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Page ── */
.home-wrap { }

/* ── Hero bar ── */
.home-hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 32px;
  gap: 20px;
}
.home-hero-left h1 {
  font-family: 'Unbounded', sans-serif;
  font-size: 36px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 1px;
  line-height: 1;
  margin-bottom: 6px;
}
.home-hero-left h1 span { color: var(--accent); font-family: 'Unbounded', sans-serif; }
.home-hero-left p {
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  color: var(--text-3);
  font-weight: 500;
}
.home-stats {
  display: flex;
  gap: 24px;
  flex-shrink: 0;
}
.home-stat {
  text-align: center;
}
.home-stat-num {
  font-family: 'Unbounded', sans-serif;
  font-size: 28px;
  font-weight: 900;
  color: var(--accent);
  line-height: 1;
}
.home-stat-lbl {
  font-family: 'Manrope', sans-serif;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--text-3);
  margin-top: 3px;
}

/* ── Modes grid ── */
.new-modes-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

/* ── Tile entrance animation ── */
/* ── Mode tile ── */
.mode-tile {
  position: relative;
  border-radius: 14px;
  overflow: hidden;
  aspect-ratio: 4/3;
  cursor: pointer;
  text-decoration: none;
  display: block;
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.06);
  opacity: 0;
  transform: translateY(18px) scale(0.97);
  transition:
    opacity .45s ease,
    transform .45s cubic-bezier(.22,1,.36,1),
    box-shadow .25s ease;
}
.mode-tile.tile-visible {
  opacity: 1;
  transform: translateY(0) scale(1);
}
.mode-tile:hover {
  transform: translateY(-3px) scale(1.01) !important;
  box-shadow: 0 16px 48px rgba(0,0,0,.5);
}
.mode-tile.soon {
  cursor: default;
  pointer-events: none;
}
.mode-tile.soon.tile-visible {
  opacity: .55;
}

/* Background image */
.mode-tile-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  transition: transform .4s ease;
}
.mode-tile:hover .mode-tile-bg { transform: scale(1.06); }

/* Gradient overlay */
.mode-tile-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(
    to top,
    rgba(0,0,0,.85) 0%,
    rgba(0,0,0,.3) 50%,
    rgba(0,0,0,.1) 100%
  );
  transition: background .25s;
}
.mode-tile:hover .mode-tile-overlay {
  background: linear-gradient(
    to top,
    rgba(0,0,0,.95) 0%,
    rgba(0,0,0,.6) 55%,
    rgba(0,0,0,.2) 100%
  );
}

/* Content */

.mode-tile-tag {
  display: inline-block;
  font-family: 'Manrope', sans-serif;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: #000;
  background: var(--accent);
  padding: 3px 8px;
  border-radius: 4px;
  margin-bottom: 6px;
  align-self: flex-start;
}
.mode-tile-name {
  font-family: 'Unbounded', sans-serif;
  font-size: 24px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 1px;
  line-height: 1;
  color: #fff;
  margin-bottom: 6px;
  transition: color .3s ease;
}
.mode-tile:hover .mode-tile-name {
  color: var(--accent);
}

.mode-tile-content {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  padding: 16px 18px;
  /* On hover shift everything up */
  transition: transform .3s cubic-bezier(.25,.46,.45,.94);
}
.mode-tile:hover .mode-tile-content {
  transform: translateY(-16px);
}

/* Tag fades out on hover */
.mode-tile-tag {
  transition: opacity .2s ease, transform .25s ease;
}
.mode-tile:hover .mode-tile-tag {
  opacity: 0;
  transform: translateY(-4px);
}

/* Desc hidden by default, appears on hover */
.mode-tile-desc {
  font-family: 'Manrope', sans-serif;
  font-size: 12px;
  color: rgba(255,255,255,.72);
  line-height: 1.55;
  margin-bottom: 8px;
  display: none;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  opacity: 0;
  transform: translateY(6px);
  transition: opacity .25s ease .05s, transform .25s ease .05s;
}
.mode-tile:hover .mode-tile-desc {
  display: -webkit-box;
  opacity: 1;
  transform: translateY(0);
}
.mode-tile-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  font-family: 'Manrope', sans-serif;
  font-size: 12px;
  color: rgba(255,255,255,.55);
  font-weight: 600;
  transition: opacity .2s ease;
}
.mode-tile:hover .mode-tile-meta {
  opacity: 1;
}

.mode-tile-online {
  display: flex;
  align-items: center;
  gap: 5px;
}
.mode-tile-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 6px var(--green);
  flex-shrink: 0;
}

/* Hover: show play button */
.mode-tile-play { display: none; }

/* Soon badge */
.mode-tile-soon-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 6px;
  padding: 4px 10px;
  font-family: 'Manrope', sans-serif;
  font-size: 9px;
  font-weight: 800;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: rgba(255,255,255,.35);
}

/* Accent border on hover */
.mode-tile::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 16px;
  border: 1px solid transparent;
  transition: border-color .25s;
  pointer-events: none;
}
.mode-tile:hover::after {
  border-color: rgba(240,196,48,.25);
}

@media (max-width: 1100px) {
  .new-modes-grid { grid-template-columns: repeat(3, 1fr); }
  .new-modes-grid .mode-tile:first-child { grid-column: span 2; }
}
@media (max-width: 768px) {
  .new-modes-grid { grid-template-columns: repeat(2, 1fr); }
  .new-modes-grid .mode-tile:first-child { grid-column: span 2; }
  .home-hero { flex-direction: column; align-items: flex-start; }
}
</style>

<div class="home-wrap">

  <!-- ── Hero bar ── -->
  <div class="home-hero">
    <div class="home-hero-left">
      <h1>CS2 <span>сервери</span></h1>
      <p>Вибери режим і підключайся в один клік</p>
    </div>
    <div class="home-stats">
      <div class="home-stat">
        <div class="home-stat-num" id="stat-online"><?= $initial_online ?></div>
        <div class="home-stat-lbl">Онлайн</div>
      </div>
      <div class="home-stat">
        <div class="home-stat-num count-up" data-target="<?= $total_servers ?>">0</div>
        <div class="home-stat-lbl">Серверів</div>
      </div>
      <div class="home-stat">
        <div class="home-stat-num count-up" data-target="<?= count($modes) ?>">0</div>
        <div class="home-stat-lbl">Режимів</div>
      </div>
    </div>
  </div>

  <!-- ── Modes grid ── -->
  <div class="new-modes-grid" id="modesGrid">
  <?php
  // Map background images per mode (placeholder until real ones added)
  $modeBgs = [
    'surf'        => SITE_URL . '/assets/modes/surf.jpg',
    'deathmatch'  => SITE_URL . '/assets/modes/deathmatch.jpg',
    '1v1'         => SITE_URL . '/assets/modes/1v1.jpg',
    'kz'          => SITE_URL . '/assets/modes/kz.jpg',
    'bhop'        => SITE_URL . '/assets/modes/bhop.jpg',
    'rab'         => SITE_URL . '/assets/modes/rab.jpg',
    'duels'       => SITE_URL . '/assets/modes/duels.jpg',
    'old_maps'    => SITE_URL . '/assets/modes/old_maps.jpg',
  ];

  foreach ($modes as $mode):
    $hasServers = $mode['servers_count'] > 0;
    $bg = $modeBgs[$mode['slug']] ?? $placeholderBg;
    $href = $hasServers ? modeUrl($mode['slug']) : '#';
  ?>
  <a href="<?= $href ?>"
     class="mode-tile <?= !$hasServers ? 'soon' : '' ?>"
     data-mode="<?= $mode['slug'] ?>"
     <?php if ($hasServers): ?>
       data-servers='<?= json_encode(array_map(fn($s) => ['ip'=>$s['ip'],'port'=>$s['port']], $mode['real_servers'])) ?>'
     <?php endif; ?>>

    <!-- Background -->
    <div class="mode-tile-bg" style="background-image:url('<?= $bg ?>')"></div>
    <div class="mode-tile-overlay"></div>

    <!-- Play button on hover -->
    <?php if ($hasServers): ?>
    <div class="mode-tile-play">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    </div>
    <?php else: ?>
    <div class="mode-tile-soon-badge">Скоро</div>
    <?php endif; ?>

    <!-- Content -->
    <div class="mode-tile-content">
      <span class="mode-tile-tag"><?= htmlspecialchars($mode['tag']) ?></span>
      <div class="mode-tile-name"><?= htmlspecialchars($mode['name']) ?></div>
      <div class="mode-tile-desc"><?= htmlspecialchars(mb_substr($mode['description'], 0, 140)) ?>...</div>
      <div class="mode-tile-meta">
        <?php if ($hasServers): ?>
          <div class="mode-tile-online">
            <div class="mode-tile-dot"></div>
            <span class="mode-online" data-mode="<?= $mode['slug'] ?>">...</span> онлайн
          </div>
          <span><?= $mode['servers_count'] ?> <?= plural_uk($mode['servers_count'], 'сервер', 'сервери', 'серверів') ?></span>
        <?php else: ?>
          <span style="color:rgba(255,255,255,.3)">Незабаром</span>
        <?php endif; ?>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
  </div>

</div>

<script>
// ── Tile entrance animation ───────────────────────────────────────────────────
(function() {
  const tiles = document.querySelectorAll('.mode-tile');
  tiles.forEach((tile, i) => {
    setTimeout(() => {
      tile.classList.add('tile-visible');
    }, 80 + i * 60);
  });
})();

// ── Count-up animation ────────────────────────────────────────────────────────
function countUp(el, target, duration) {
  const start = performance.now();
  const update = (now) => {
    const elapsed = now - start;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    el.textContent = Math.round(eased * target);
    if (progress < 1) requestAnimationFrame(update);
  };
  requestAnimationFrame(update);
}

document.querySelectorAll('.count-up').forEach(el => {
  const target = parseInt(el.dataset.target, 10);
  setTimeout(() => countUp(el, target, 900), 200);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>