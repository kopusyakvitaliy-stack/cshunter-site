<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($_GET['id'] ?? ''));
$mode = getMode($pdo, $slug);
if (isLoggedIn()) getCsrfToken(); // генеруємо до закриття сесії
session_write_close();

if (!$mode) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$servers      = getServers($pdo, $mode['id']);
$top_players  = getTopPlayers($pdo, $mode['id']);
$page_title   = $mode['name'];

$gradients = [
    'surf'        => ['from'=>'#071828','to'=>'#0d4070','accent'=>'#42a5f5'],
    'deathmatch'  => ['from'=>'#180707','to'=>'#6e0d0d','accent'=>'#ef5350'],
    '1v1'         => ['from'=>'#0e0618','to'=>'#4a1970','accent'=>'#ce93d8'],
    'kz'          => ['from'=>'#060f06','to'=>'#1b4a1b','accent'=>'#66bb6a'],
    'bhop'        => ['from'=>'#001515','to'=>'#005c55','accent'=>'#26c6da'],
    'rab'         => ['from'=>'#150a00','to'=>'#7a2c00','accent'=>'#ffa726'],
    'duels'       => ['from'=>'#0d0621','to'=>'#4a1970','accent'=>'#ce93d8'],
    'aim'         => ['from'=>'#0a1a12','to'=>'#1b5e20','accent'=>'#69f0ae'],
];
$g = $gradients[$slug] ?? ['from'=>'#111','to'=>'#333','accent'=>'#F0C430'];

include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Mode switcher ── */
.mode-switcher {
  display: flex;
  align-items: center;
  gap: 0;
  margin-bottom: 24px;
  overflow-x: auto;
  scrollbar-width: none;
  border-bottom: 1px solid rgba(255,255,255,.07);
}
.mode-switcher::-webkit-scrollbar { display: none; }
.mode-sw-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 10px 20px 12px;
  text-decoration: none;
  transition: all .15s;
  flex-shrink: 0;
  min-width: 80px;
  text-align: center;
  position: relative;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
}
.mode-sw-item:hover .mode-sw-name { color: var(--text); }
.mode-sw-item.active { border-bottom-color: var(--accent); }
.mode-sw-name {
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  font-weight: 700;
  color: var(--text-3);
  white-space: nowrap;
  transition: color .15s;
}
.mode-sw-item.active .mode-sw-name { color: var(--accent); }
.mode-sw-count {
  font-family: 'Manrope', sans-serif;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-3);
  margin-top: 3px;
}
.mode-sw-item.active .mode-sw-count { color: var(--accent); opacity: .6; }
.mode-sw-item.soon { opacity: .3; pointer-events: none; }

/* ── Mode page ── */
.mode-page { padding: 0 20px; }

/* ── Hero ── */
.mode-hero-new {
  position: relative;
  border-radius: 16px;
  overflow: hidden;
  margin-bottom: 32px;
  min-height: 340px;
  display: flex;
  align-items: flex-end;
  animation: fadeInUp .4s ease both;
}
.mode-hero-bg-img {
  position: absolute; inset: 0;
  background-size: cover;
  background-position: center;
}
.mode-hero-gradient {
  position: absolute; inset: 0;
  background: linear-gradient(
    to top,
    rgba(0,0,0,.95) 0%,
    rgba(0,0,0,.6)  40%,
    rgba(0,0,0,.2)  100%
  );
}
.mode-hero-gradient-side {
  position: absolute; inset: 0;
  background: linear-gradient(
    to right,
    rgba(0,0,0,.5) 0%,
    transparent 60%
  );
}
.mode-hero-content-new {
  position: relative;
  z-index: 2;
  padding: 40px 48px;
  width: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 24px;
}
.mode-hero-left-new {}
.mode-hero-tag-new {
  display: inline-block;
  font-family: 'Manrope', sans-serif;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: #000;
  background: var(--accent);
  padding: 4px 12px;
  border-radius: 5px;
  margin-bottom: 12px;
}
.mode-hero-title-new {
  font-family: 'Unbounded', sans-serif;
  font-size: 72px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 2px;
  line-height: .95;
  color: #fff;
  margin-bottom: 16px;
}
.mode-hero-stats-new {
  display: flex;
  align-items: center;
  gap: 24px;
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  color: rgba(255,255,255,.6);
  font-weight: 600;
}
.mode-hero-online-new {
  display: flex;
  align-items: center;
  gap: 7px;
}
.mode-hero-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 8px var(--green);
}
.mode-hero-right-new {
  display: flex;
  gap: 12px;
  flex-shrink: 0;
}
.hero-stat-card {
  background: rgba(255,255,255,.08);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 12px;
  padding: 14px 20px;
  text-align: center;
  min-width: 80px;
}
.hero-stat-card-num {
  font-family: 'Unbounded', sans-serif;
  font-size: 32px;
  font-weight: 900;
  color: var(--accent);
  line-height: 1;
}
.hero-stat-card-lbl {
  font-family: 'Manrope', sans-serif;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: rgba(255,255,255,.4);
  margin-top: 4px;
}

/* ── Breadcrumb ── */
.mode-breadcrumb {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  color: var(--text-3);
  margin-bottom: 20px;
}
.mode-breadcrumb a { color: var(--text-3); transition: color .2s; }
.mode-breadcrumb a:hover { color: var(--accent); }
.mode-breadcrumb-sep { opacity: .35; }

/* ── Layout ── */
.mode-layout {
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: 20px;
}
@media (max-width: 900px) { .mode-layout { grid-template-columns: 1fr; } }

/* ── Section label ── */
.mode-section-lbl {
  font-family: 'Manrope', sans-serif;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 3px;
  text-transform: uppercase;
  color: var(--text-3);
  margin-bottom: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
}
.mode-section-lbl::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(255,255,255,.06);
}

/* ── Servers grid ── */
.servers-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
  column-gap: 16px;
}
@media (max-width: 700px)  { .servers-grid { grid-template-columns: 1fr; } }

/* ── Server card ── */
.mode-server-card {
  position: relative;
  height: 80px;
  border-radius: 12px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid rgba(255,255,255,.07);
  background: #0e0f14;
  transition: border-color .25s, box-shadow .25s, transform .25s;
}
.mode-server-card:hover {
  border-color: rgba(240,196,48,.3);
  box-shadow: 0 4px 20px rgba(0,0,0,.5);
  transform: translateY(-1px);
}

/* Map bg — covers entire card */
.srv-map-bg {
  position: absolute;
  inset: 0;
  background-size: cover;
  background-position: center;
  filter: brightness(.38) saturate(.8);
  transform: scale(1.04);
  transition: filter .3s ease, transform .3s ease;
}
.mode-server-card:hover .srv-map-bg {
  filter: brightness(.62) saturate(1.05);
  transform: scale(1.06);
}

/* Left vignette — keeps text readable, fades to transparent on right */
.srv-overlay {
  position: absolute;
  inset: 0;
  background: linear-gradient(90deg,
    rgba(0,0,0,.72) 0%,
    rgba(0,0,0,.55) 25%,
    rgba(0,0,0,.25) 55%,
    transparent 100%
  );
  transition: background .3s ease;
}
.mode-server-card:hover .srv-overlay {
  background: linear-gradient(90deg,
    rgba(0,0,0,.55) 0%,
    rgba(0,0,0,.35) 25%,
    rgba(0,0,0,.1) 55%,
    transparent 100%
  );
}

/* Content layer */
.srv-content {
  position: relative;
  z-index: 2;
  height: 100%;
  display: flex;
  align-items: center;
  padding: 0 14px;
  gap: 10px;
}

/* Left: text info */
.srv-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.srv-name {
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  font-weight: 800;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.srv-meta {
  display: flex;
  align-items: center;
  gap: 7px;
  font-family: 'Manrope', sans-serif;
  font-size: 11px;
  color: rgba(255,255,255,.45);
  font-weight: 600;
}
.srv-ping-badge {
  padding: 2px 6px;
  border-radius: 5px;
  font-size: 10px;
  font-weight: 700;
  background: rgba(255,255,255,.1);
  color: rgba(255,255,255,.5);
}
.srv-ping-badge.good { background: rgba(74,222,128,.15); color: #4ADE80; }
.srv-ping-badge.bad  { background: rgba(248,113,113,.15); color: #F87171; }

.srv-dot-sep { width: 3px; height: 3px; border-radius: 50%; background: rgba(255,255,255,.2); flex-shrink: 0; }

.srv-players-row {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(255,255,255,.75);
  font-weight: 700;
}
.srv-online-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: #4ADE80;
  box-shadow: 0 0 6px #4ADE80;
  flex-shrink: 0;
}
.srv-online-dot.off { background: #F87171; box-shadow: 0 0 6px #F87171; }

/* Right: action buttons */
.srv-btns {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}
.srv-btn {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid rgba(255,255,255,.15);
  background: rgba(255,255,255,.08);
  color: rgba(255,255,255,.6);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all .18s;
  flex-shrink: 0;
  backdrop-filter: blur(4px);
}
.srv-btn:hover {
  background: rgba(255,255,255,.18);
  border-color: rgba(255,255,255,.3);
  color: #fff;
}
.srv-btn.play {
  background: rgba(240,196,48,.15);
  border-color: rgba(240,196,48,.4);
  color: var(--accent);
}
.srv-btn.play:hover {
  background: var(--accent);
  color: #000;
  box-shadow: 0 0 16px rgba(240,196,48,.5);
}
.srv-btn.ok {
  background: rgba(74,222,128,.12) !important;
  border-color: rgba(74,222,128,.4) !important;
  color: #4ADE80 !important;
}

/* ── Rules card ── */
.mode-info-card {
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  padding: 20px;
  margin-bottom: 14px;
}
.mode-info-card-title {
  font-family: 'Manrope', sans-serif;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 14px;
  padding-bottom: 12px;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex;
  align-items: center;
  gap: 8px;
}
.mode-rules-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.mode-rules-list li {
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  color: var(--text-2);
  display: flex;
  gap: 10px;
  line-height: 1.5;
}
.mode-rules-list li::before {
  content: '·';
  color: var(--accent);
  flex-shrink: 0;
  font-weight: 900;
  font-size: 20px;
  line-height: 1;
}

/* ── Description ── */
.mode-desc-new {
  font-family: 'Manrope', sans-serif;
  font-size: 14px;
  color: var(--text-2);
  line-height: 1.7;
  padding: 16px 20px;
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 14px;
  margin-bottom: 14px;
}
</style>

<div class="mode-page">

  <!-- Mode switcher -->
  <div class="mode-switcher">
    <?php
    $allModes = getDemoModes();
    $srvCfg   = require __DIR__ . '/servers_config.php';
    foreach ($allModes as $m):
      $isActive  = $m['slug'] === $slug;
      $hasSrv    = !empty($srvCfg[$m['slug']]);
      if (!$hasSrv && !$isActive) continue; // hide modes with no servers unless current
    ?>
    <a href="<?= modeUrl($m['slug']) ?>"
       class="mode-sw-item <?= $isActive ? 'active' : '' ?> <?= !$hasSrv ? 'soon' : '' ?>">
      <span class="mode-sw-name"><?= htmlspecialchars($m['name']) ?></span>
      <span class="mode-sw-count" id="sw-count-<?= $m['slug'] ?>">
        <?php
        // Get online from cache
        if ($pdo && $hasSrv) {
          $srvList = $srvCfg[$m['slug']];
          $total = 0;
          foreach ($srvList as $s) {
            $r = $pdo->prepare("SELECT players FROM server_status_cache WHERE ip=? AND port=?");
            $r->execute([$s['ip'], (int)$s['port']]);
            $total += (int)($r->fetchColumn() ?? 0);
          }
          echo $total;
        } else { echo '—'; }
        ?>
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── Hero ── -->
  <div class="mode-hero-new" id="modeHero">
<?php
$modeImgMap = [
  'surf'=>'surf.jpg','deathmatch'=>'deathmatch.jpg','1v1'=>'1v1.jpg',
  'kz'=>'kz.jpg','bhop'=>'bhop.jpg','rab'=>'rab.jpg',
  'duels'=>'duels.jpg','old_maps'=>'old_maps.jpg',
];
$modeVideoMap = [
  'surf'     => 'surf_preview.mp4',
  'bhop'     => 'bhop_preview.mp4',
  'duels'    => 'duels_preview.mp4',
  'kz'       => 'kz_preview.mp4',
  '1v1'      => '1v1_preview.mp4',
  'old_maps' => 'old_maps_preview.mp4',
];
$modePosterMap = [
  'surf'     => 'surf_poster.jpg',
  'bhop'     => 'bhop_poster.jpg',
  'duels'    => 'duels_poster.jpg',
  'kz'       => 'kz_poster.jpg',
  '1v1'      => '1v1_poster.jpg',
  'old_maps' => 'old_maps_poster.jpg',
];
$modeImg    = isset($modeImgMap[$slug])    ? SITE_URL.'/assets/modes/'.$modeImgMap[$slug]    : '';
$modeVideo  = isset($modeVideoMap[$slug])  ? SITE_URL.'/assets/modes/'.$modeVideoMap[$slug]  : '';
$modePoster = isset($modePosterMap[$slug]) ? SITE_URL.'/assets/modes/'.$modePosterMap[$slug] : '';
?>
    <?php if ($modeVideo): ?>
    <!-- Video background -->
    <video class="mode-hero-bg-img" id="heroBg" autoplay loop muted playsinline
           poster="<?= $modePoster ?>"
           style="object-fit:cover;width:100%;height:100%;position:absolute;inset:0;background:#000">
      <source src="<?= $modeVideo ?>" type="video/mp4">
    </video>
    <?php else: ?>
    <div class="mode-hero-bg-img" id="heroBg"
         style="<?= $modeImg ? "background-image:url('{$modeImg}');background-size:cover;background-position:center" : "background:linear-gradient(135deg,{$g['from']},{$g['to']})" ?>"></div>
    <?php endif; ?>
    <div class="mode-hero-gradient"></div>
    <div class="mode-hero-gradient-side"></div>

    <div class="mode-hero-content-new">
      <div class="mode-hero-left-new">
        <div class="mode-hero-tag-new"><?= htmlspecialchars($mode['tag']) ?></div>
        <div class="mode-hero-title-new"><?= htmlspecialchars($mode['name']) ?></div>
        <div class="mode-hero-stats-new">
          <div class="mode-hero-online-new">
            <div class="mode-hero-dot"></div>
            <span id="heroOnline">—</span> гравців онлайн
          </div>
          <span><?= count($servers) ?> серверів</span>
          <?php if (!empty($servers)): ?>
          <span id="heroMap" style="color:rgba(255,255,255,.4)">Завантаження...</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="mode-hero-right-new">
        <div class="hero-stat-card">
          <div class="hero-stat-card-num" id="heroOnlineStat">—</div>
          <div class="hero-stat-card-lbl">Онлайн</div>
        </div>
        <div class="hero-stat-card">
          <div class="hero-stat-card-num"><?= count($servers) ?></div>
          <div class="hero-stat-card-lbl">Серверів</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Main layout ── -->
  <div class="mode-layout">

    <!-- Left: servers -->
    <div>
      <div class="mode-section-lbl">Сервери</div>

      <?php if (empty($servers)): ?>
        <div class="mode-info-card" style="text-align:center;padding:32px;color:var(--text-3)">
          <div style="font-size:32px;margin-bottom:10px">🔌</div>
          <div style="font-weight:700;margin-bottom:6px;color:var(--text-2)">Сервери в розробці</div>
          <div style="font-size:13px">Скоро тут з'являться сервери</div>
        </div>
      <?php else: ?>
      <div class="servers-grid">
        <?php foreach ($servers as $srv): ?>
        <?php $key = $srv['ip'].'-'.(int)$srv['port']; ?>
        <div class="mode-server-card"
             data-server-ip="<?= $srv['ip'] ?>"
             data-server-port="<?= (int)$srv['port'] ?>"
             onclick="openServerModal('<?= addslashes(htmlspecialchars($srv['name'])) ?>','<?= $srv['ip'] ?>',<?= (int)$srv['port'] ?>)">

          <!-- Blurred map background -->
          <div class="srv-map-bg" id="thumb-<?= $key ?>"
               style="background-image:url('<?= $modeImg ?>')"></div>
          <div class="srv-overlay"></div>

          <!-- Content -->
          <div class="srv-content">
            <div class="srv-info">
              <div class="srv-name-row" style="display:flex;align-items:center;gap:8px">
                <div class="srv-name"><?= htmlspecialchars($srv['name']) ?></div>
                <span class="srv-ping-badge" id="ping-<?= $key ?>">— мс</span>
              </div>
              <div class="srv-meta">
                <span class="srv-players-row">
                  <span class="srv-online-dot" id="dot-<?= $key ?>"></span>
                  <span id="playerstext-<?= $key ?>">—</span>
                </span>
                <span class="srv-dot-sep"></span>
                <span id="map-<?= $key ?>">...</span>
              </div>
            </div>
            <div class="srv-btns" onclick="event.stopPropagation()">
              <button class="srv-btn play" title="Підключитись"
                onclick="connectToServer('<?= $srv['ip'] ?>',<?= (int)$srv['port'] ?>)">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
              </button>
              <button class="srv-btn" id="copybtn-<?= $key ?>" title="Копіювати IP"
                onclick="copySrvIp(this,'<?= $srv['ip'] ?>:<?= $srv['port'] ?>')">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

<!-- Server detail modal -->
<div id="serverModal" style="display:none;position:fixed;inset:0;z-index:3000;background:rgba(0,0,0,.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)closeServerModal()">
  <div style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:20px;width:min(680px,96vw);max-height:85vh;overflow:hidden;display:flex;flex-direction:column;animation:loginModalIn .25s ease">

    <!-- Hero image -->
    <div id="srvModalHero" style="height:140px;background-size:cover;background-position:center;position:relative;flex-shrink:0">
      <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.2),rgba(9,9,11,.95))"></div>
      <div style="position:absolute;bottom:0;left:0;right:0;padding:16px 24px;z-index:2">
        <div id="srvModalName" style="font-family:'Unbounded',sans-serif;font-size:20px;font-weight:900;color:#fff;margin-bottom:6px"></div>
        <div style="display:flex;align-items:center;gap:10px;font-family:'Manrope',sans-serif;font-size:12px;color:rgba(255,255,255,.55);font-weight:600">
          <span id="srvModalDot" style="width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 6px var(--green);display:inline-block"></span>
          <span id="srvModalPlayers"></span>
          <span>·</span>
          <span id="srvModalMap"></span>
          <span>·</span>
          <span id="srvModalIp" style="font-family:'Unbounded',sans-serif;font-size:11px;color:var(--accent)"></span>
        </div>
      </div>
      <button onclick="closeServerModal()" style="position:absolute;top:12px;right:12px;z-index:3;width:32px;height:32px;border-radius:50%;background:rgba(0,0,0,.5);border:1px solid rgba(255,255,255,.15);color:#fff;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">×</button>
    </div>

    <!-- Players list -->
    <div style="flex:1;overflow-y:auto;padding:20px 24px">
      <div style="font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--text-3);margin-bottom:12px">Гравці на сервері</div>
      <div id="srvModalPlayersList" style="display:flex;flex-direction:column;gap:6px">
        <div style="text-align:center;padding:24px;color:var(--text-3);font-size:13px">Завантаження...</div>
      </div>
    </div>

    <!-- Footer buttons -->
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;flex-shrink:0">
      <button id="srvModalPlayBtn" style="flex:1;background:var(--accent);color:#000;font-family:'Unbounded',sans-serif;font-size:13px;font-weight:900;border:none;padding:13px;border-radius:12px;cursor:pointer;letter-spacing:.5px;text-transform:uppercase">▶ Підключитись</button>
      <button id="srvModalCopyBtn" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);color:var(--text-2);font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;padding:13px 20px;border-radius:12px;cursor:pointer">🗐 Копіювати IP</button>
    </div>
  </div>
</div>
      <?php endif; ?>
    </div>

    <!-- Right: info -->
    <div>
      <!-- Description -->
      <div class="mode-section-lbl" style="margin-top:0">Про режим</div>
      <div class="mode-desc-new"><?= htmlspecialchars($mode['description']) ?></div>

      <!-- Rules -->
      <div class="mode-section-lbl">Правила</div>
      <div class="mode-info-card">
        <ul class="mode-rules-list">
          <?php
          $rules = $mode['rules'] ?? [];
          if (is_string($rules)) $rules = json_decode($rules, true) ?? [];
          foreach ($rules as $rule): ?>
            <li><?= htmlspecialchars($rule) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Top players -->
      <?php if (!empty($top_players)): ?>
      <div class="mode-section-lbl">Топ гравців</div>
      <div class="mode-info-card">
        <?php foreach ($top_players as $i => $p):
          $rank = $i + 1;
          $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : $rank));
        ?>
        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04)">
          <div style="font-family:'Unbounded',sans-serif;font-size:16px;font-weight:900;width:24px;text-align:center"><?= $medal ?></div>
          <?php if (!empty($p['avatar_url'])): ?>
            <img src="<?= htmlspecialchars($p['avatar_url']) ?>" style="width:28px;height:28px;border-radius:6px;object-fit:cover">
          <?php endif; ?>
          <div style="flex:1;font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
            <?= htmlspecialchars($p['steam_name']) ?>
          </div>
          <div style="font-family:'Unbounded',sans-serif;font-size:18px;font-weight:900;color:var(--accent)">
            <?= number_format($p['score']) ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
const SVG_COPY  = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>`;
const SVG_CHECK = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`;

function connectToServer(ip, port) {
  window.location.href = `steam://connect/${ip}:${port}`;
}

function copyText(text, cb) {
  const fb = () => {
    const ta = document.createElement('textarea');
    ta.value = text; ta.style.cssText = 'position:fixed;left:-9999px;opacity:0';
    document.body.appendChild(ta); ta.focus(); ta.select();
    try { document.execCommand('copy'); } catch(e){}
    document.body.removeChild(ta); if(cb) cb();
  };
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(()=>{ if(cb) cb(); }).catch(fb);
  } else { fb(); }
}

function copySrvIp(btn, ip) {
  const orig = btn.innerHTML;
  copyText(ip, () => {
    btn.classList.add('ok'); btn.innerHTML = SVG_CHECK;
    setTimeout(()=>{ btn.classList.remove('ok'); btn.innerHTML = orig; }, 2000);
  });
}

// ── Modal ─────────────────────────────────────────────────────────────────────
function openServerModal(name, ip, port) {
  const modal = document.getElementById('serverModal');
  const key   = ip + '-' + port;
  document.getElementById('srvModalName').textContent    = name;
  document.getElementById('srvModalIp').textContent      = ip + ':' + port;
  document.getElementById('srvModalPlayers').textContent = document.getElementById('playerstext-'+key)?.textContent || '—';
  document.getElementById('srvModalMap').textContent     = document.getElementById('map-'+key)?.textContent || '—';
  const thumb = document.getElementById('thumb-'+key);
  if (thumb) document.getElementById('srvModalHero').style.backgroundImage = thumb.style.backgroundImage;
  document.getElementById('srvModalPlayBtn').onclick = () => connectToServer(ip, port);
  const cb = document.getElementById('srvModalCopyBtn');
  cb.textContent = '🗐 Копіювати IP'; cb.style.color = '';
  cb.onclick = () => copyText(ip+':'+port, () => {
    cb.textContent = '✓ Скопійовано'; cb.style.color = 'var(--green)';
    setTimeout(()=>{ cb.textContent = '🗐 Копіювати IP'; cb.style.color=''; }, 2000);
  });
  document.getElementById('srvModalPlayersList').innerHTML =
    '<div style="text-align:center;padding:32px;color:var(--text-3);font-size:13px">Завантаження...</div>';
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  fetch(`/api/server_players.php?ip=${ip}&port=${port}`)
    .then(r=>r.json()).then(d=>{
      const list = document.getElementById('srvModalPlayersList');
      const pl = d.players||[];
      if (!pl.length) {
        list.innerHTML = `
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;gap:10px">
            <div style="font-size:32px;opacity:.3">👥</div>
            <div style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;color:var(--text-3)">Сервер порожній</div>
            <div style="font-family:'Manrope',sans-serif;font-size:12px;color:var(--text-3);opacity:.6">Будь першим хто зайде</div>
          </div>`;
        return;
      }
      list.innerHTML = pl.map(p=>`
        <div style="display:flex;align-items:center;gap:12px;padding:9px 12px;background:var(--surface);border-radius:10px;border:1px solid rgba(255,255,255,.05)">
          <img src="${p.avatar||''}" onerror="this.style.display='none'" style="width:36px;height:36px;border-radius:8px;object-fit:cover;flex-shrink:0;background:var(--surface-2)">
          <span style="flex:1;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name||'Гравець'}</span>
        </div>`).join('');
    }).catch(()=>{
      document.getElementById('srvModalPlayersList').innerHTML =
        '<div style="text-align:center;padding:32px;color:var(--text-3);font-size:13px">Не вдалось завантажити список</div>';
    });
}
function closeServerModal() {
  document.getElementById('serverModal').style.display='none';
  document.body.style.overflow='';
}
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeServerModal(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
.srv-mini-bar-wrap {
  width: 17%;
  height: 2px;
  background: rgba(255,255,255,.08);
  border-radius: 2px;
  margin-top: 5px;
  overflow: hidden;
}
.srv-mini-bar {
  height: 100%;
  width: 0%;
  border-radius: 2px;
  transition: width 1.2s cubic-bezier(.4,0,.2,1);
}
.srv-mini-bar.fill-low  { background: #4ADE80; box-shadow: 0 0 4px rgba(74,222,128,.6); }
.srv-mini-bar.fill-mid  { background: #FACC15; box-shadow: 0 0 4px rgba(250,204,21,.6); }
.srv-mini-bar.fill-high { background: #FB923C; box-shadow: 0 0 4px rgba(251,146,60,.6); }
.srv-mini-bar.fill-full { background: #F87171; box-shadow: 0 0 4px rgba(248,113,113,.6); }
</style>

<script>
// ── Mini bars: ініціалізуємо DOM-елементи ─────────────────────────────────────
(function() {
  document.querySelectorAll('.mode-server-card').forEach(function(card) {
    const meta = card.querySelector('.srv-meta');
    if (!meta) return;
    const wrap = document.createElement('div');
    wrap.className = 'srv-mini-bar-wrap';
    const bar = document.createElement('div');
    bar.className = 'srv-mini-bar';
    bar.id = 'minibar-' + card.dataset.serverIp + '-' + card.dataset.serverPort;
    wrap.appendChild(bar);
    meta.insertAdjacentElement('afterend', wrap);
  });
})();

function updateMiniBar(key, players, maxPlayers) {
  const bar = document.getElementById('minibar-' + key);
  if (!bar || !maxPlayers) return;
  const pct = Math.min(100, Math.round((players / maxPlayers) * 100));
  bar.style.width = pct + '%';
  bar.className = 'srv-mini-bar ' + (
    pct >= 90 ? 'fill-full' :
    pct >= 70 ? 'fill-high' :
    pct >= 40 ? 'fill-mid'  : 'fill-low'
  );
}

// ── Єдиний polling через servers_all.php ─────────────────────────────────────
let firstMap = false;

function applyModePageData(cache) {
  let heroTotal = 0;

  document.querySelectorAll('.mode-server-card').forEach(function(card) {
    const ip  = card.dataset.serverIp;
    const port = card.dataset.serverPort;
    const key  = ip + '-' + port;
    const d    = cache[ip + ':' + port];
    if (!d) return;

    const dot = document.getElementById('dot-' + key);
    if (dot) dot.className = 'srv-online-dot' + (d.online ? '' : ' off');

    const pt = document.getElementById('playerstext-' + key);
    if (pt) pt.textContent = d.online ? `${d.players||0} / ${d.max_players||'?'}` : 'Офлайн';

    const pg = document.getElementById('ping-' + key);
    if (pg) {
      if (d.online && d.ping != null) {
        pg.textContent = d.ping + ' мс';
        pg.className = 'srv-ping-badge' + (d.ping < 60 ? ' good' : d.ping > 120 ? ' bad' : '');
      } else {
        pg.textContent = '— мс';
        pg.className = 'srv-ping-badge';
      }
    }

    const mp = document.getElementById('map-' + key);
    if (mp && d.map) mp.textContent = d.map;

    if (d.online && d.map) {
      const th = document.getElementById('thumb-' + key);
      if (th) {
        const img = new Image();
        img.onload = function() {
          th.style.backgroundImage = `url('/assets/maps/${d.map}.jpg')`;
          if (!firstMap) {
            firstMap = true;
            const hb = document.getElementById('heroBg');
            if (hb && hb.tagName !== 'VIDEO') {
              hb.style.backgroundImage = `url('/assets/maps/${d.map}.jpg')`;
              hb.style.backgroundSize = 'cover';
            }
            const hm = document.getElementById('heroMap');
            if (hm) hm.textContent = d.map;
          }
        };
        img.src = `/assets/maps/${d.map}.jpg`;
      }
    }

    if (d.online) {
      heroTotal += (d.players || 0);
      setTimeout(function() { updateMiniBar(key, d.players||0, d.max_players||0); }, 100);
    }
  });

  ['heroOnline', 'heroOnlineStat'].forEach(function(id) {
    const e = document.getElementById(id);
    if (e) e.textContent = heroTotal;
  });
}

// Реєструємо callback і запускаємо polling (спільний з main.js)
if (typeof registerPollCallback === 'function') {
  registerPollCallback(applyModePageData);
  startServerPolling(60000);
}
</script>
