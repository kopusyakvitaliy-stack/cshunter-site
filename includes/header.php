<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
$user = getUser();

// ── Перевірка бану для залогінених юзерів (раз на 5 хвилин) ─────────────────
if ($user && !empty($user['steam_id'])) {
    $now = time();
    $lastBanCheck = $_SESSION['_ban_checked_at'] ?? 0;
    if ($now - $lastBanCheck > 300) {
        global $pdo;
        if (!isset($pdo)) require_once __DIR__ . '/db.php';
        if ($pdo) {
            $ban = checkBan($pdo, $user['steam_id']);
            if ($ban) {
                session_destroy();
                showBanPage($ban);
            }
            $_SESSION['_ban_checked_at'] = $now;
        }
    }
}


// Визначаємо поточну сторінку з URI (підтримує чисті URL)
$_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (preg_match('#^/profile/\d+/skinchanger#', $_uri)) {
    $current_page = 'skinchanger';
} elseif (preg_match('#^/profile/#', $_uri)) {
    $current_page = 'profile';
} elseif (preg_match('#^/servers/#', $_uri)) {
    $current_page = 'mode';
} elseif (preg_match('#^/skinchanger#', $_uri)) {
    $current_page = 'skinchanger';
} else {
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($page_title) ? htmlspecialchars($page_title) . ' — ' : '' ?><?= SITE_NAME ?></title>
<meta name="description" content="<?= htmlspecialchars($og_description ?? 'CSHunter — найкращі CS2 ігрові сервери: Surf, Deathmatch, 1v1, KZ, Bhop, Retake') ?>">

<?php if (isLoggedIn()): ?>
<meta name="csrf-token" content="<?= getCsrfToken() ?>">
<script>window.__CSRF = '<?= getCsrfToken() ?>';</script>
<?php endif; ?>

<!-- Open Graph -->
<meta property="og:site_name" content="<?= SITE_NAME ?>">
<meta property="og:type"      content="website">
<meta property="og:url"       content="<?= htmlspecialchars($og_url ?? SITE_URL . $_SERVER['REQUEST_URI']) ?>">
<meta property="og:title"     content="<?= htmlspecialchars($og_title ?? ($page_title ?? SITE_NAME)) ?>">
<meta property="og:description" content="<?= htmlspecialchars($og_description ?? 'CSHunter — найкращі CS2 ігрові сервери') ?>">
<?php if (!empty($og_image)): ?>
<meta property="og:image"     content="<?= htmlspecialchars($og_image) ?>">
<meta property="og:image:width"  content="<?= $og_image_width  ?? 460 ?>">
<meta property="og:image:height" content="<?= $og_image_height ?? 460 ?>">
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card"        content="<?= !empty($og_image) ? 'summary_large_image' : 'summary' ?>">
<meta name="twitter:title"       content="<?= htmlspecialchars($og_title ?? ($page_title ?? SITE_NAME)) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($og_description ?? 'CSHunter — найкращі CS2 ігрові сервери') ?>">
<?php if (!empty($og_image)): ?>
<meta name="twitter:image"       content="<?= htmlspecialchars($og_image) ?>">
<?php endif; ?>

<!-- Preconnect для Google Fonts — зменшує латентність -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- Preload критичних шрифтів (Unbounded 900 — заголовки, Manrope 700 — основний текст) -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Unbounded:wght@700;900&family=Manrope:wght@600;700;800&display=swap">
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;700;800;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"></noscript>

<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__.'/../assets/css/style.css') ?>">
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <a href="<?= SITE_URL ?>/" class="logo-link">
        <img src="<?= SITE_URL ?>/assets/logo.png" alt="Logo" class="logo-img" style="height:80px;width:auto;display:block;">

      </a>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Навігація</div>
      <a href="<?= SITE_URL ?>/" class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Головна
      </a>

      <?php if ($user): ?>
      <a href="<?= profileUrl($user['steam_id'] ?? '') ?>" class="nav-item <?= $current_page === 'profile' ? 'active' : '' ?>" style="margin-top:4px">
        <img src="<?= htmlspecialchars($user['avatar_url'] ?? '') ?>" alt=""
             style="width:20px;height:20px;border-radius:4px;object-fit:cover;flex-shrink:0"
             onerror="this.style.display='none'">
        <?= htmlspecialchars(mb_substr($user['steam_name'], 0, 14)) . (mb_strlen($user['steam_name']) > 14 ? '…' : '') ?>
      </a>
      <?php endif; ?>

      <div class="nav-label" style="margin-top:16px">Функції</div>
      <a href="<?= $user ? profileUrl($user['steam_id'], 'skinchanger') : (SITE_URL . '/skinchanger') ?>" class="nav-item <?= $current_page === 'skinchanger' ? 'active' : '' ?>">
        <img src="<?= SITE_URL ?>/assets/skinchanger-icon.png" class="nav-icon" style="width:17px;height:17px;object-fit:contain;opacity:.7;transition:opacity var(--transition)" alt="">
        Skinchanger
      </a>

      <div class="nav-label" style="margin-top:16px">Інше</div>
      <a href="<?= SITE_URL ?>/other/reaction.php" class="nav-item <?= ($current_page === 'reaction') ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Тест реакції
      </a>
      <a href="<?= SITE_URL ?>/launch-options.php" class="nav-item <?= $current_page === 'launch-options' ? 'active' : '' ?>">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
        Launch Options
      </a>
    </nav>

    <div class="sidebar-footer" style="display:none">
      <div class="online-strip">
        <span class="online-dot-sm"></span>
        <span id="total-online">—</span> онлайн
      </div>
    </div>
  </aside>

  <!-- Sidebar backdrop for mobile -->
  <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="closeSidebar()"></div>

  <!-- MAIN -->
  <div class="main">

    <!-- HEADER -->
    <header class="header">
      <div class="header-left">
        <button class="burger" id="burgerBtn" onclick="toggleSidebar()" aria-label="Меню" aria-expanded="false">
          <div class="burger-icon"><span></span><span></span><span></span></div>
        </button>
      </div>
      <div class="header-right">
        <?php if ($user): ?>
          <div class="user-dropdown" id="userDropdown">
            <button class="user-dropdown-trigger" id="userDropdownTrigger" onclick="toggleUserDropdown(event)">
              <img src="<?= htmlspecialchars($user['avatar_url'] ?? '') ?>" alt="" class="user-chip-avatar" onerror="this.src='data:image/svg+xml,<svg xmlns=&apos;http://www.w3.org/2000/svg&apos; viewBox=&apos;0 0 36 36&apos;><rect fill=&apos;%23222&apos; width=&apos;36&apos; height=&apos;36&apos;/><text x=&apos;50%25&apos; y=&apos;60%25&apos; text-anchor=&apos;middle&apos; fill=&apos;%23F0C430&apos; font-size=&apos;16&apos; font-family=&apos;sans-serif&apos;><?= substr(htmlspecialchars($user['steam_name']), 0, 1) ?></text></svg>'">
              <svg class="user-dropdown-arrow" width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M2 4L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
            <div class="user-dropdown-menu" id="userDropdownMenu">
              <a href="<?= profileUrl($user['steam_id'] ?? '') ?>" class="user-dropdown-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Профіль
              </a>
              <a href="<?= $user ? profileUrl($user['steam_id'], 'skinchanger') : (SITE_URL . '/skinchanger') ?>" class="user-dropdown-item">
                <img src="<?= SITE_URL ?>/assets/skinchanger-icon.png" width="15" height="15" style="object-fit:contain;opacity:0.6;filter:brightness(10)" class="user-dropdown-sc-icon">
                Skinchanger
              </a>
              <a href="<?= profileUrl($user['steam_id'] ?? '', 'friends') ?>" class="user-dropdown-item">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                Друзі
              </a>
              <div class="user-dropdown-divider"></div>
              <a href="<?= SITE_URL ?>/auth/logout.php" class="user-dropdown-item user-dropdown-logout">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Вийти
              </a>
            </div>
          </div>
        <?php else: ?>
          <button class="btn-steam" onclick="openLoginModal()">
            <img src="<?= SITE_URL ?>/assets/steam-logo.png" alt="Steam" style="width:16px;height:16px;object-fit:contain;filter:invert(1)">
            Увійти через Steam
          </button>
        <?php endif; ?>
      </div>
    </header>
    <!-- /HEADER -->

    <?php if (!$user): ?>
    <!-- STEAM LOGIN MODAL -->
    <div id="steamLoginModal" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.85);backdrop-filter:blur(8px);align-items:center;justify-content:center" onclick="if(event.target===this)closeLoginModal()">
      <div style="background:var(--bg-2);border:1px solid var(--border-2);border-radius:20px;width:min(460px,92vw);overflow:hidden;animation:loginModalIn .3s cubic-bezier(.4,0,.2,1)">
        <style>@keyframes loginModalIn{from{transform:translateY(30px) scale(.96);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}</style>
        <!-- Header gradient -->
        <div style="background:linear-gradient(135deg,#1a1f35 0%,#0d1117 100%);padding:40px 36px 32px;text-align:center;position:relative;border-bottom:1px solid var(--border)">
          <!-- Steam logo big -->
          <div style="width:72px;height:72px;background:rgba(255,255,255,.06);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:1px solid rgba(255,255,255,.1)">
            <img src="<?= SITE_URL ?>/assets/steam-logo.png" alt="Steam" style="width:44px;height:44px;object-fit:contain;filter:invert(1);opacity:.9">
          </div>
          <div style="font-size:22px;font-weight:900;color:#fff;margin-bottom:8px;letter-spacing:-.3px">Увійди через Steam</div>
          <div style="font-size:13px;color:var(--text-3);line-height:1.5">щоб розблокувати всі можливості CSHunter</div>
        </div>
        <!-- Benefits -->
        <div style="padding:28px 36px">
          <?php
          $benefits = [
            ['🎨','Зміна скінів','Налаштуй зброю — скіни відразу з\'являться на всіх серверах'],
            ['📊','Статистика та рейтинги','Відстежуй свої рекорди, ранги та прогрес на surf і bhop'],
            ['👥','Друзі онлайн','Бачи хто з твоїх друзів зараз грає і на якому сервері'],
            ['⭐','VIP та привілеї','Отримуй бонуси та ексклюзивні функції для підписників'],
          ];
          foreach ($benefits as [$icon, $title, $desc]): ?>
          <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px">
            <div style="width:38px;height:38px;background:var(--surface);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;border:1px solid var(--border)"><?= $icon ?></div>
            <div>
              <div style="font-size:13px;font-weight:800;color:var(--text);margin-bottom:2px"><?= $title ?></div>
              <div style="font-size:12px;color:var(--text-3);line-height:1.4"><?= $desc ?></div>
            </div>
          </div>
          <?php endforeach; ?>

          <!-- Steam login button -->
          <a href="<?= SITE_URL ?>/auth/steam_login.php" style="display:flex;align-items:center;justify-content:center;gap:12px;width:100%;padding:14px;background:linear-gradient(135deg,#1b2838,#2a475e);color:#fff;border-radius:12px;font-size:15px;font-weight:900;letter-spacing:.5px;text-decoration:none;margin-top:8px;border:1px solid rgba(102,192,244,.3);transition:all .3s;box-shadow:0 4px 20px rgba(0,0,0,.4)" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 30px rgba(0,0,0,.5)'" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(0,0,0,.4)'">
            <img src="<?= SITE_URL ?>/assets/steam-logo.png" alt="" style="width:22px;height:22px;object-fit:contain;filter:invert(1)">
            Увійти через Steam
          </a>
          <button onclick="closeLoginModal()" style="display:block;width:100%;margin-top:10px;padding:10px;background:transparent;border:none;color:var(--text-3);font-size:12px;font-weight:700;cursor:pointer;font-family:inherit">Закрити</button>
        </div>
      </div>
    </div>
    <script>
    function openLoginModal(){var m=document.getElementById('steamLoginModal');m.style.display='flex';}
    function closeLoginModal(){var m=document.getElementById('steamLoginModal');m.style.display='none';}
    document.addEventListener('keydown',function(e){if(e.key==='Escape')closeLoginModal();});
    </script>
    <?php endif; ?>

    <style>
    /* ===== USER DROPDOWN ===== */
    .user-dropdown {
      position: relative;
    }

    .user-dropdown-trigger {
      display: flex;
      align-items: center;
      gap: 7px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 30px;
      padding: 4px 10px 4px 4px;
      cursor: pointer;
      transition: all var(--transition);
      color: var(--text-3);
    }

    .user-dropdown-trigger:hover,
    .user-dropdown.open .user-dropdown-trigger {
      border-color: rgba(240,196,48,0.4);
      background: var(--surface-2);
      color: var(--accent);
    }

    .user-dropdown-trigger .user-chip-avatar {
      width: 32px; height: 32px;
      border-radius: 50%;
      object-fit: cover;
    }

    .user-dropdown-arrow {
      transition: transform .3s cubic-bezier(.4,0,.2,1);
      flex-shrink: 0;
    }

    .user-dropdown.open .user-dropdown-arrow {
      transform: rotate(180deg);
    }

    .user-dropdown-menu {
      position: absolute;
      top: calc(100% + 10px);
      right: 0;
      min-width: 200px;
      background: var(--bg-2, #131516);
      border: 1px solid var(--border);
      border-radius: 14px;
      padding: 6px;
      z-index: 1000;
      box-shadow: 0 16px 40px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.04);
      opacity: 0;
      transform: translateY(-8px) scale(0.97);
      pointer-events: none;
      transition: opacity .13s cubic-bezier(.4,0,.2,1), transform .13s cubic-bezier(.4,0,.2,1);
      transform-origin: top right;
    }

    .user-dropdown.open .user-dropdown-menu {
      opacity: 1;
      transform: translateY(0) scale(1);
      pointer-events: all;
    }

    .user-dropdown-divider {
      height: 1px;
      background: var(--border);
      margin: 4px 4px;
    }

    .user-dropdown-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      border-radius: 9px;
      font-size: 13px;
      font-weight: 700;
      color: var(--text-2, rgba(255,255,255,0.75));
      text-decoration: none;
      transition: background .18s, color .18s;
    }

    .user-dropdown-item:hover {
      background: var(--surface);
      color: var(--text);
    }

    .user-dropdown-item svg {
      opacity: 0.6;
      flex-shrink: 0;
      transition: opacity .18s;
    }

    .user-dropdown-item:hover svg {
      opacity: 1;
    }

    .user-dropdown-logout {
      color: rgba(255, 80, 80, 0.75);
    }

    .user-dropdown-logout:hover {
      background: rgba(255, 80, 80, 0.08);
      color: #ff5050;
    }

    .user-dropdown-logout svg {
      opacity: 0.7;
    }
    </style>

    <script>
    function toggleUserDropdown(e) {
      e.stopPropagation();
      const dd = document.getElementById('userDropdown');
      dd.classList.toggle('open');
    }
    document.addEventListener('click', function(e) {
      const dd = document.getElementById('userDropdown');
      if (dd && !dd.contains(e.target)) {
        dd.classList.remove('open');
      }
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('open');
      }
    });
    </script>

    <div class="content">