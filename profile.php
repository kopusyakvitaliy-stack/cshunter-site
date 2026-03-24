<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/faceit.php';
require_once __DIR__ . '/includes/teams.php';
require_once __DIR__ . '/includes/steam_friends.php';
require_once __DIR__ . '/includes/items.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$me = getUser();

// ── Whose profile ──────────────────────────────────────────────────────────────
// Support both /profile.php?id=STEAMID and /profile/STEAMID (via nginx rewrite)
if (!empty($_GET['id']) && preg_match('/^\d{17}$/', $_GET['id'])) {
    $viewId = $_GET['id'];
} elseif ($me) {
    // Redirect own profile to canonical URL with steam_id
    header('Location: ' . profileUrl($me['steam_id']));
    exit;
} else {
    header('Location: ' . SITE_URL . '/auth/steam_login.php');
    exit;
}
$isOwn = ($me && $me['steam_id'] === $viewId);

// Active tab (from URL path: /profile/STEAMID/friends)
$validTabs = ['profile', 'friends', 'skinchanger', 'items'];
$activeTab = in_array($_GET['tab'] ?? '', $validTabs) ? $_GET['tab'] : 'profile';

// ── Load user from DB (always authoritative) ───────────────────────────────────
$profile = null;
if ($pdo) {
    $s = $pdo->prepare('SELECT * FROM users WHERE steam_id = ?');
    $s->execute([$viewId]);
    $profile = $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── If not in users table — check steam_profile_cache or fetch from Steam API ──
// users = тільки реально залогінені. Переглянуті профілі йдуть в steam_profile_cache.
if (!$profile) {
    // Спочатку перевіряємо кеш (TTL 6 годин)
    $sd = null;
    if ($pdo) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM steam_profile_cache WHERE steam_id = ? AND updated_at >= NOW() - INTERVAL 6 HOUR");
            $stmt->execute([$viewId]);
            $cached = $stmt->fetch();
            if ($cached) {
                $profile = [
                    'id'                => 0,
                    'steam_id'          => $cached['steam_id'],
                    'steam_name'        => $cached['steam_name'],
                    'avatar_url'        => $cached['avatar_url'],
                    'profile_url'       => $cached['profile_url'],
                    'country'           => $cached['country'],
                    'created_at'        => null,
                    'fav_team_id'       => null,
                    'faceit_level'      => 0,
                    'faceit_updated_at' => null,
                    'has_logged_in'     => 0,
                ];
            }
        } catch (Throwable $e) {}
    }

    // Кешу нема або застарілий — тягнемо зі Steam API
    if (!$profile) {
        $sd = fetchSteamUser($viewId);
        if (!$sd) {
            $page_title = 'Профіль не знайдено';
            include __DIR__ . '/includes/header.php';
            echo '<div style="text-align:center;padding:100px 20px;font-family:\'Manrope\',sans-serif">
                    <div style="font-size:52px;opacity:.2;margin-bottom:18px">👤</div>
                    <div style="font-family:\'Unbounded\',sans-serif;font-size:18px;font-weight:900;color:var(--text-2);margin-bottom:16px">Профіль не знайдено</div>
                    <a href="' . SITE_URL . '/" style="color:var(--accent);font-weight:700">← На головну</a>
                  </div>';
            include __DIR__ . '/includes/footer.php';
            exit;
        }

        // Зберігаємо в кеш (не в users!) — UPSERT з TTL
        if ($pdo) {
            try {
                $pdo->prepare("
                    INSERT INTO steam_profile_cache (steam_id, steam_name, avatar_url, profile_url, country, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        steam_name  = VALUES(steam_name),
                        avatar_url  = VALUES(avatar_url),
                        profile_url = VALUES(profile_url),
                        country     = VALUES(country),
                        updated_at  = NOW()
                ")->execute([
                    $sd['steamid'],
                    $sd['personaname'],
                    $sd['avatarfull'] ?? $sd['avatarmedium'] ?? '',
                    $sd['profileurl'] ?? '',
                    $sd['loccountrycode'] ?? '',
                ]);
            } catch (Throwable $e) {}
        }

        $profile = [
            'id'                => 0,
            'steam_id'          => $sd['steamid'],
            'steam_name'        => $sd['personaname'],
            'avatar_url'        => $sd['avatarfull'] ?? $sd['avatarmedium'] ?? '',
            'profile_url'       => $sd['profileurl'] ?? '',
            'country'           => $sd['loccountrycode'] ?? '',
            'created_at'        => null,
            'fav_team_id'       => null,
            'faceit_level'      => 0,
            'faceit_updated_at' => null,
            'has_logged_in'     => 0,
        ];
    }
}

// ── Ensure DB data always wins over stale session ─────────────────────────────
if ($isOwn) {
    // Sync fresh DB data back into session so it's always correct
    if (!empty($profile['steam_name']) && $profile['steam_name'] !== 'Гравець') {
        $_SESSION['user']['steam_name'] = $profile['steam_name'];
    }
    if (!empty($profile['avatar_url'])) {
        $_SESSION['user']['avatar_url'] = $profile['avatar_url'];
    }
    // Fetch country if missing
    if (empty($profile['country']) && !isset($_SESSION['country_checked'])) {
        $freshData = fetchSteamUser($profile['steam_id']);
        if ($freshData) {
            $country = $freshData['loccountrycode'] ?? '';
            if ($country && $pdo && $profile['id']) {
                $pdo->prepare("UPDATE users SET country=? WHERE id=?")->execute([$country, $profile['id']]);
            }
            $profile['country'] = $country;
            $_SESSION['user']['country'] = $country;
        }
        $_SESSION['country_checked'] = true;
    }
}

// ── Fav team ───────────────────────────────────────────────────────────────────
$favTeam = null;
$favTeamId = (int)($profile['fav_team_id'] ?? 0);

if ($isOwn && empty($favTeamId) && !empty($me['fav_team'])) {
    // Fallback: session has it but DB might not (race)
    $favTeam = $me['fav_team'];
} elseif ($favTeamId && $pdo) {
    $ft = getTeamById($pdo, $favTeamId);
    if ($ft) {
        $ftLogo = !empty($ft['logo_local']) && file_exists(__DIR__.'/'.$ft['logo_local'])
            ? SITE_URL.'/'.$ft['logo_local']
            : ($ft['logo_url'] ?? null);
        $favTeam = ['id' => (int)$ft['id'], 'name' => $ft['name'], 'logo' => $ftLogo];
        if ($isOwn) $_SESSION['user']['fav_team'] = $favTeam;
    }
}

// ── FACEIT ────────────────────────────────────────────────────────────────────
$faceit = null;
define('FACEIT_CACHE_VER', 5);
if ($isOwn) {
    // Власний профіль: session-кеш 15 хв + синхронізація в БД
    try {
        $expired = empty($_SESSION['faceit_t']) || time() - $_SESSION['faceit_t'] > 900;
        $stale   = ($_SESSION['faceit_ver'] ?? 0) !== FACEIT_CACHE_VER;
        if ($expired || $stale) {
            $faceit = getFaceitBySteamId($profile['steam_id']);
            $_SESSION['faceit_d'] = $faceit;
            $_SESSION['faceit_t'] = time();
            $_SESSION['faceit_ver'] = FACEIT_CACHE_VER;
            if ($faceit && $pdo) syncFaceitToDb($pdo, $profile['steam_id'], $faceit);
        } else {
            $faceit = $_SESSION['faceit_d'] ?? null;
        }
    } catch (Throwable $e) {
        $faceit = null;
    }
} elseif ($pdo && !empty($profile['steam_id'])) {
    // Чужий профіль: читаємо з БД (TTL 2 год вже в getFaceitFromDb)
    // Якщо кеш застарілий або відсутній — запитуємо API у фоні (тільки якщо юзер є в users)
    try {
        $faceitDb = getFaceitFromDb($pdo, $profile['steam_id']);
        if ($faceitDb) {
            $faceit = ['level' => (int)$faceitDb['faceit_level'], 'elo' => (int)($faceitDb['faceit_elo'] ?? 0)];
        } elseif (!empty($profile['id'])) {
            // Є в users але кеш протух або відсутній — оновлюємо
            $fresh = getFaceitBySteamId($profile['steam_id']);
            if ($fresh) {
                syncFaceitToDb($pdo, $profile['steam_id'], $fresh);
                $faceit = $fresh;
            }
        }
    } catch (Throwable $e) {
        $faceit = null;
    }
}
$faceitLevel = (int)($faceit['level'] ?? $profile['faceit_level'] ?? 0);
$faceitIcon  = $faceitLevel >= 10 ? SITE_URL.'/assets/skill_level_max.png' : SITE_URL."/assets/skill_level_{$faceitLevel}.png";

// ── Friends (own only) ─────────────────────────────────────────────────────────
// Friends loaded asynchronously via /api/friends.php

// ── Items: load equipped frame + run auto-grant ────────────────────────────────
$equippedFrame = null;
$profileUserId = (int)($profile['id'] ?? 0);

if ($pdo && $profileUserId) {
    // Auto-grant only for own profile
    if ($isOwn && !empty($profile['created_at'])) {
        ItemService::checkAndGrantAutoItems($pdo, $profileUserId, $profile);
    }
    $equippedFrame      = ItemService::getEquippedFrame($pdo, $profileUserId);
    $equippedBackground = ItemService::getEquippedBackground($pdo, $profileUserId);
}

// Завантажуємо інвентар тут, щоб $itemCount був доступний для tab badge
// Повторне використання того ж масиву в items tab — без додаткового запиту
$_earlyInventory = ($pdo && $profileUserId) ? ItemService::getUserInventory($pdo, $profileUserId) : [];
$itemCount = count($_earlyInventory);

// ── Follows + Stats: 2 запити замість 5 ──────────────────────────────────────
$followersCount = 0;
$followingCount = 0;
$isFollowing    = false;
$stats          = [];
$totalKills     = 0;
$totalDeaths    = 0;
$totalHours     = 0;

if ($pdo && !empty($profile['id'])) {
    $uid   = (int)$profile['id'];
    $meUid = 0;

    // Знаходимо ID переглядача
    if (!$isOwn && $me) {
        $s = $pdo->prepare('SELECT id FROM users WHERE steam_id = ?');
        $s->execute([$me['steam_id']]);
        $meUid = (int)$s->fetchColumn();
    }

    // Запит 1: всі лічильники підписок + чи підписаний переглядач
    // Positional params замість named — PDO з EMULATE_PREPARES=false не дозволяє повторювати named params
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM follows WHERE followed_id = ?)  AS followers_count,
            (SELECT COUNT(*) FROM follows WHERE follower_id = ?)  AS following_count,
            IF(? > 0,
               (SELECT COUNT(*) FROM follows WHERE follower_id = ? AND followed_id = ?),
               0
            ) AS is_following
    ");
    $stmt->execute([$uid, $uid, $meUid, $meUid, $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $followersCount = (int)($row['followers_count'] ?? 0);
    $followingCount = (int)($row['following_count'] ?? 0);
    $isFollowing    = !$isOwn && (bool)($row['is_following'] ?? false);

    // Запит 2: статистика по режимах
    $st = $pdo->prepare(
        'SELECT s.kills, s.deaths, s.playtime_hours, s.sessions, s.score, m.name AS mode_name
         FROM stats s JOIN modes m ON m.id = s.mode_id
         WHERE s.user_id = ? ORDER BY s.score DESC'
    );
    $st->execute([$uid]);
    $stats = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($stats as $r) {
        $totalKills  += (int)$r['kills'];
        $totalDeaths += (int)$r['deaths'];
        $totalHours  += (int)$r['playtime_hours'];
    }
}

$totalKd = $totalDeaths > 0 ? round($totalKills / $totalDeaths, 2) : $totalKills;

$country       = $profile['country'] ?? '';
$neverLoggedIn = empty($profile['last_login']);

// Дата реєстрації — беремо created_at, якщо є, інакше last_login як fallback
$_joinedTs = !empty($profile['created_at'])
    ? strtotime($profile['created_at'])
    : (!empty($profile['last_login']) ? strtotime($profile['last_login']) : null);

if ($_joinedTs) {
    $_ukMonths = ['01'=>'Січ','02'=>'Лют','03'=>'Бер','04'=>'Квіт','05'=>'Трав','06'=>'Черв',
                  '07'=>'Лип','08'=>'Серп','09'=>'Вер','10'=>'Жовт','11'=>'Лист','12'=>'Груд'];
    $joinedDate = ($_ukMonths[date('m', $_joinedTs)] ?? date('m', $_joinedTs)) . ' ' . date('Y', $_joinedTs);
} else {
    $joinedDate = null;
}
$steamUrl      = $profile['profile_url'] ?: 'https://steamcommunity.com/profiles/' . $viewId;

// ── Open Graph ────────────────────────────────────────────────────────────────
$og_url          = profileUrl($viewId);
$og_title        = ($profile['steam_name'] ?? 'Гравець') . ' — CSHunter';
$og_image        = $profile['avatar_url'] ?? '';
$og_image_width  = 184;
$og_image_height = 184;
$og_parts = [];
if ($faceitLevel > 0)    $og_parts[] = 'FACEIT ' . $faceitLevel;
if ($totalKills > 0)     $og_parts[] = number_format($totalKills) . ' вбивств';
if ($totalHours > 0)     $og_parts[] = $totalHours . ' год на серверах';
if ($followersCount > 0) $og_parts[] = $followersCount . ' фоловерів';
$og_description = !empty($og_parts)
    ? implode(' · ', $og_parts) . ' — CSHunter CS2'
    : 'Профіль гравця на CSHunter — найкращі CS2 сервери';

// Генеруємо CSRF токен поки сесія відкрита
if (isLoggedIn()) getCsrfToken();

session_write_close();
$page_title = h($profile['steam_name'] ?? 'Профіль') . ' — Профіль';
include __DIR__ . '/includes/header.php';
?>
<style>
/* ── Hero ──────────────────────────────────────────────────────────────────── */
/* ── Hero D: Banner style ── */
.profile-hero-wrap{margin-bottom:28px;animation:fadeInUp .4s ease both;position:relative;overflow:visible}
.profile-hero{background:var(--bg-2);border-radius:16px;overflow:visible;position:relative;isolation:auto}
/* Banner top */
.profile-hero-banner{height:110px;background:linear-gradient(135deg,#1a1200 0%,#2a1e00 25%,#1a1400 50%,#0f0e00 100%);position:relative;overflow:hidden;border-radius:16px 16px 0 0;transition:height .4s ease}
.profile-hero-banner.has-bg{height:260px}
.profile-hero-banner.has-bg ~ .profile-hero-body{margin-top:-80px}
.profile-hero-banner::before{content:'';position:absolute;inset:0;background-image:repeating-linear-gradient(45deg,transparent,transparent 20px,rgba(240,196,48,.04) 20px,rgba(240,196,48,.04) 21px)}
.profile-hero-banner::after{content:'';position:absolute;bottom:0;left:0;right:0;height:120px;background:linear-gradient(to bottom,transparent,var(--bg-2));z-index:2}
.profile-hero-banner-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;z-index:1;opacity:0;transition:opacity .4s}
.profile-hero-banner-img.loaded{opacity:1}
/* Blur аватар в банері — менший blur, більш чіткий */
.profile-hero-blur-bg{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%) scale(2.5);width:160px;height:110px;background-size:cover;background-position:center top;filter:blur(3px) saturate(.6) brightness(.4);z-index:0;pointer-events:none;opacity:.85}
/* Body під банером */
.profile-hero-body{padding:0 28px 20px;display:flex;align-items:center;gap:18px;margin-top:-28px;position:relative;z-index:2;overflow:visible}
/* ── Avatar with frame ────────────────────────────────────────────────────── */
.profile-avatar-wrap{position:relative;flex-shrink:0;width:88px;height:88px;overflow:visible}
.profile-avatar{width:88px;height:88px;border-radius:14px;object-fit:cover;border:3px solid var(--bg-2);outline:2px solid rgba(240,196,48,.45);outline-offset:1px;position:relative;z-index:1}
.profile-avatar-wrap.has-frame .profile-avatar{outline:none!important;border-color:transparent!important}
.profile-avatar-frame-css{position:absolute;inset:0;border-radius:14px;z-index:2;pointer-events:none}
.profile-hero-glow-canvas{position:absolute;width:180px;height:180px;border-radius:50%;z-index:3;pointer-events:none;opacity:0;transition:opacity .8s ease;filter:blur(32px)}
/* Frame lives on hero-wrap level — not clipped by border-radius */
.profile-hero-frame-overlay{position:absolute;z-index:10;pointer-events:none;width:160px;height:160px;opacity:0;transition:opacity .3s;overflow:visible}
/* OLD single .profile-avatar (for pages that don't use wrap) */
.profile-hero-body > .profile-avatar{flex-shrink:0}
.profile-avatar-placeholder{width:60px;height:60px;border-radius:10px;background:var(--accent-dim);border:3px solid var(--bg-2);outline:2px solid rgba(240,196,48,.45);outline-offset:1px;display:flex;align-items:center;justify-content:center;font-family:'Unbounded',sans-serif;font-size:22px;font-weight:900;color:var(--accent);flex-shrink:0;position:relative;z-index:2}
.profile-hero-body-info{flex:1;min-width:0;padding-bottom:4px}
.profile-hero-body-actions{padding-bottom:4px;display:flex;gap:8px;align-items:center;flex-shrink:0}
/* Stats strip внизу */
.profile-hero-stats{display:flex;border-top:1px solid rgba(255,255,255,.06);margin:0 28px;padding:12px 0 16px;gap:0}
.profile-hero-stat{flex:1;text-align:center;cursor:pointer;padding:4px 8px;border-radius:8px;transition:background var(--transition)}
.profile-hero-stat:hover{background:rgba(255,255,255,.04)}
/* Info */
.profile-info{flex:1;min-width:0}
.profile-name{font-family:'Manrope',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.3px;line-height:1;margin-bottom:8px}
.profile-steam-link{font-size:13px;color:var(--text-3);font-weight:600;margin-bottom:6px}
.profile-steam-link a{color:var(--accent)}

/* ── Follow counters ──────────────────────────────────────────────────────── */
.follow-counters{display:flex;gap:20px;margin-bottom:12px}
.follow-cnt-btn{cursor:pointer;padding:4px 8px;margin:-4px -8px;border-radius:8px;transition:background var(--transition);text-align:center}
.follow-cnt-btn:hover{background:rgba(255,255,255,.06)}
.follow-cnt-n{font-family:'Unbounded',sans-serif;font-size:16px;font-weight:900;color:var(--text);line-height:1;display:block}
.follow-cnt-l{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:1.5px;margin-top:2px;display:block}

/* ── Fav team badge ───────────────────────────────────────────────────────── */
.fav-team-badge{display:inline-flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--border-2);border-radius:8px;padding:6px 12px;cursor:pointer;transition:all var(--transition)}
.fav-team-badge:hover{border-color:rgba(240,196,48,.35);background:var(--surface-2)}
.fav-team-logo{width:22px;height:22px;object-fit:contain;border-radius:3px;flex-shrink:0}
.fav-team-name{font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:var(--text)}
.fav-team-add{display:inline-flex;align-items:center;gap:7px;background:transparent;border:1px dashed rgba(255,255,255,.15);border-radius:8px;padding:6px 14px;color:var(--text-3);font-size:12px;font-weight:600;cursor:pointer;transition:all var(--transition);font-family:'Manrope',sans-serif}
.fav-team-add:hover{border-color:rgba(240,196,48,.4);color:var(--accent)}

/* ── FACEIT badge ─────────────────────────────────────────────────────────── */
.badge-faceit{display:inline-flex;align-items:center;gap:7px;padding:6px 13px;border-radius:8px;font-size:13px;font-weight:800;border:1px solid rgba(255,94,0,.3);background:rgba(255,94,0,.1);color:#FF5E00}

/* ── Follow btn ───────────────────────────────────────────────────────────── */
.btn-follow{display:inline-flex;align-items:center;gap:7px;padding:7px 17px;border-radius:9px;font-family:'Manrope',sans-serif;font-size:13px;font-weight:800;cursor:pointer;border:none;transition:all .17s;white-space:nowrap;text-decoration:none}
.btn-follow.add{background:var(--accent);color:#000}.btn-follow.add:hover{opacity:.85}
.btn-follow.remove{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.11);color:var(--text-2)}.btn-follow.remove:hover{border-color:rgba(248,113,113,.4);color:#F87171}
.hero-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px}

/* ── Layout ───────────────────────────────────────────────────────────────── */
.profile-cols{display:grid;grid-template-columns:1fr 400px;gap:28px}

/* ── Friends ──────────────────────────────────────────────────────────────── */
/* ── Friends list ─────────────────────── */
.friends-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;overflow:visible;padding:2px}
@media(max-width:900px){.friends-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:600px){.friends-grid{grid-template-columns:repeat(2,1fr)}}
/* Картка */
.friend-card{display:flex;align-items:center;gap:9px;padding:8px 10px;transition:background .15s,box-shadow .15s;overflow:visible;cursor:pointer;min-width:0;background:rgba(255,255,255,.04);box-shadow:0 0 0 1px rgba(255,255,255,.07);border-radius:10px;position:relative}
.friend-card:hover{background:rgba(255,255,255,.07);box-shadow:0 0 0 1px rgba(255,255,255,.14)}
.friend-card:hover .friend-name-text{color:var(--accent)}
/* Avatar wrap */
.friend-avatar-wrap{position:relative;flex-shrink:0;width:63px;height:63px;overflow:visible;z-index:1}
.friend-avatar-frame-img{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);pointer-events:none;z-index:2;object-fit:contain}
.friend-avatar{width:36px;height:36px;border-radius:8px;object-fit:cover;background:var(--surface-2);display:block;position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:1}
.friend-status-dot{position:absolute;width:10px;height:10px;border-radius:50%;border:2px solid var(--bg-2);z-index:4;bottom:13px;right:13px}
.friend-status-dot.online{background:var(--green)}
.friend-status-dot.offline{display:none}
.friend-status-dot.ingame{background:#42a5f5}
/* Info */
.friend-info{flex:1;min-width:0}
.friend-name-row{display:flex;align-items:center;gap:3px;min-width:0;margin-bottom:2px}
.friend-name-text{font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text);transition:color .12s;line-height:1.2;min-width:0}
.friend-name-text.offline{color:var(--text-2);font-weight:600}
.friend-status-row{display:flex;align-items:center;gap:3px}
.friend-state{font-family:'Manrope',sans-serif;font-size:10px;font-weight:600;line-height:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.friend-state.online{color:var(--green)}.friend-state.offline{color:var(--text-3)}.friend-state.ingame{color:#42a5f5}
.friend-game-badge{display:none}
/* Секція заголовок */
.friends-label{font-family:'Manrope',sans-serif;font-size:9px;color:var(--text-3);font-weight:900;text-transform:uppercase;letter-spacing:2px;padding:10px 2px 5px;display:flex;align-items:center;gap:8px}
.friends-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.05)}
.friends-group{margin-bottom:6px}
/* Перемикачі фільтрів */
.friends-filter{display:flex;gap:8px;margin-bottom:14px;align-items:center;flex-wrap:wrap}
.friends-filter-count{font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;color:var(--text-3);margin-right:4px}
.friends-switch{display:inline-flex;align-items:center;gap:7px;padding:6px 13px;border-radius:20px;font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;cursor:pointer;border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.04);color:var(--text-3);transition:all .15s;user-select:none;white-space:nowrap}
.friends-switch:hover{background:rgba(255,255,255,.07);color:var(--text-2);border-color:rgba(255,255,255,.14)}
.friends-switch.active{background:rgba(240,196,48,.1);border-color:rgba(240,196,48,.3);color:var(--accent)}
.friends-switch-dot{width:7px;height:7px;border-radius:50%;background:var(--green);flex-shrink:0;box-shadow:0 0 4px var(--green)}
.friends-switch-logo{width:13px;height:13px;object-fit:contain;opacity:.8;flex-shrink:0}
.friends-switch-count{font-size:10px;font-weight:800;opacity:.55;margin-left:1px}
.filter-btn{background:var(--surface);border:1px solid var(--border);color:var(--text-3);padding:6px 14px;border-radius:20px;font-weight:700;font-size:12px;cursor:pointer;transition:all var(--transition);white-space:nowrap;display:inline-flex;align-items:center}
.filter-btn.active,.filter-btn:hover{background:var(--accent-dim);border-color:rgba(240,196,48,.35);color:var(--accent)}
.notice-box{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:28px;text-align:center;color:var(--text-3);font-size:13px}

/* ── Stats card ───────────────────────────────────────────────────────────── */
.stats-card{background:linear-gradient(135deg,#141418,#1a1a22);border:1px solid rgba(255,255,255,.08);border-radius:var(--radius);padding:16px;margin-bottom:16px;animation:fadeInUp .4s ease .15s both}
.stats-card-lbl{font-family:'Manrope',sans-serif;font-size:9px;font-weight:900;letter-spacing:2.5px;color:var(--accent);opacity:.7;text-transform:uppercase;margin-bottom:10px;display:block}
.stats-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:7px;margin-bottom:12px}
.stat-box{background:rgba(0,0,0,.2);border-radius:8px;padding:11px;text-align:center}
.stat-box-n{font-family:'Unbounded',sans-serif;font-size:20px;font-weight:900;line-height:1;margin-bottom:3px}
.stat-box-l{font-family:'Manrope',sans-serif;font-size:9px;color:rgba(255,255,255,.3);text-transform:uppercase;letter-spacing:1.5px;font-weight:700}
.kd-g{color:#4ADE80!important}.kd-y{color:var(--accent)!important}.kd-r{color:#F87171!important}
.mode-mini-tbl{width:100%;border-collapse:collapse;font-family:'Manrope',sans-serif;font-size:12px}
.mode-mini-tbl th{color:rgba(255,255,255,.28);font-weight:700;text-transform:uppercase;letter-spacing:1px;padding:4px 6px;text-align:left;font-size:10px;border-bottom:1px solid rgba(255,255,255,.06)}
.mode-mini-tbl td{color:rgba(255,255,255,.6);padding:7px 6px;border-bottom:1px solid rgba(255,255,255,.04);font-weight:600}
.mode-mini-tbl tr:last-child td{border-bottom:none}
.mode-mini-tbl tr:hover td{background:rgba(255,255,255,.03)}
.td-mn{font-weight:800;color:var(--text)!important}

/* ── Info card ────────────────────────────────────────────────────────────── */
.info-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:12px}
.info-card-title{font-family:'Manrope',sans-serif;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:2px;color:var(--text-3);margin-bottom:14px;display:flex;align-items:center;gap:7px}

/* ── Copy btn ─────────────────────────────────────────────────────────────── */
.btn-copy-link{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:9px;background:transparent;border:1px dashed rgba(255,255,255,.11);border-radius:9px;color:var(--text-3);font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;cursor:pointer;transition:all .17s;margin-top:8px}
.btn-copy-link:hover{border-color:rgba(240,196,48,.3);color:var(--accent)}

/* ── Team modal ───────────────────────────────────────────────────────────── */
.team-modal-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;height:calc(min(460px,48vh));overflow-y:auto;overflow-x:hidden;padding-right:6px;align-content:start}
.team-item{display:flex;flex-direction:column;align-items:center;gap:8px;padding:12px 8px;background:var(--bg-2);border:2px solid transparent;border-radius:12px;cursor:pointer;transition:all var(--transition);height:100px;box-sizing:border-box;overflow:hidden}
.team-item:hover{background:var(--surface);border-color:rgba(240,196,48,.3)}
.team-item.fadeIn{animation:teamFadeIn .25s ease both}
@keyframes teamFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.team-load-more{display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:12px;border:1px dashed rgba(255,255,255,.1);background:transparent;transition:all var(--transition);min-height:80px;color:var(--text-3)}
.team-load-more:hover{border-color:rgba(240,196,48,.35);color:var(--accent)}
.team-item.selected{border-color:var(--accent);background:var(--accent-dim)}
.team-item img{width:48px;height:48px;object-fit:contain;border-radius:6px}
.team-item-placeholder{width:48px;height:48px;background:var(--surface-2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:18px;font-weight:900;color:var(--text-3)}
.team-item-name{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;text-align:center;color:var(--text-2);line-height:1.3;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;word-break:break-word;width:100%;height:2.6em;flex-shrink:0}
.team-search{width:100%;background:var(--bg-2);border:1px solid var(--border-2);border-radius:10px;padding:10px 14px;color:var(--text);font-size:14px;font-family:'Manrope',sans-serif;outline:none;transition:border-color var(--transition);margin-bottom:14px}
.team-search:focus{border-color:rgba(240,196,48,.4)}
.team-modal-loading{display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-3);font-size:13px;font-family:'Manrope',sans-serif}

/* ── Follow modal ─────────────────────────────────────────────────────────── */
.follow-modal-tabs{display:flex;gap:0;margin-bottom:20px;background:var(--bg-2);border-radius:10px;padding:3px}
.follow-tab{flex:1;padding:8px;text-align:center;font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:var(--text-3);cursor:pointer;border-radius:8px;transition:all var(--transition)}
.follow-tab.active{background:var(--surface);color:var(--text);box-shadow:0 1px 4px rgba(0,0,0,.3)}
.follow-user-list{display:flex;flex-direction:column;gap:6px;height:380px;overflow-y:auto;padding-right:4px}
.follow-user-list::-webkit-scrollbar{width:4px}.follow-user-list::-webkit-scrollbar-track{background:transparent}.follow-user-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}
.follow-user-item{display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg-2);border-radius:10px;border:1px solid var(--border);transition:all var(--transition)}
.follow-user-item:hover{border-color:rgba(240,196,48,.2);background:var(--surface)}
.follow-user-avatar{width:42px;height:42px;border-radius:9px;object-fit:cover;flex-shrink:0;background:var(--surface-2)}
.follow-user-name{font-family:'Manrope',sans-serif;font-size:14px;font-weight:700;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.follow-user-name a{color:inherit;text-decoration:none}.follow-user-name a:hover{color:var(--accent)}
.follow-unfollow-btn{background:transparent;border:1px solid rgba(255,255,255,.1);color:var(--text-3);width:30px;height:30px;border-radius:7px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all var(--transition)}
.follow-unfollow-btn:hover{border-color:rgba(248,113,113,.4);color:#F87171;background:rgba(248,113,113,.08)}
.follow-load-more{width:100%;padding:10px;background:transparent;border:1px dashed rgba(255,255,255,.1);border-radius:10px;color:var(--text-3);font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;cursor:pointer;transition:all var(--transition);margin-top:6px}
.follow-load-more:hover{border-color:rgba(240,196,48,.3);color:var(--accent)}
.follow-empty{text-align:center;padding:40px 20px;color:var(--text-3);font-family:'Manrope',sans-serif;font-size:13px}
.follow-empty-icon{font-size:36px;opacity:.2;margin-bottom:10px}
.follow-loading{display:flex;align-items:center;justify-content:center;padding:40px;color:var(--text-3);font-family:'Manrope',sans-serif;font-size:13px;gap:10px}
@keyframes followSpin{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
/* ── Friends skeleton ─────────────────────────────────────────────────────── */
.friends-skeleton-wrap{padding:4px 0}
.fskel-header{display:flex;align-items:center;gap:10px;margin-bottom:16px;padding:0 2px}
.fskel-logo{width:22px;height:22px;object-fit:contain;opacity:.15;animation:fskelpulse 1.4s ease-in-out infinite}
.fskel-title{font-family:'Manrope',sans-serif;font-size:12px;font-weight:700;color:var(--text-3);opacity:.5}
.fskel-card{display:flex;align-items:center;gap:9px;padding:8px 10px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:8px;height:60px}
.fskel-avatar{width:44px;height:44px;border-radius:8px;background:linear-gradient(90deg,var(--surface-2) 25%,rgba(255,255,255,.06) 50%,var(--surface-2) 75%);background-size:200% 100%;animation:fskelshine 1.4s ease-in-out infinite;flex-shrink:0}
.fskel-info{flex:1;display:flex;flex-direction:column;gap:7px}
.fskel-name{height:13px;border-radius:6px;width:60%;background:linear-gradient(90deg,var(--surface-2) 25%,rgba(255,255,255,.06) 50%,var(--surface-2) 75%);background-size:200% 100%;animation:fskelshine 1.4s ease-in-out infinite}
.fskel-status{height:10px;border-radius:6px;width:35%;background:linear-gradient(90deg,var(--surface-2) 25%,rgba(255,255,255,.06) 50%,var(--surface-2) 75%);background-size:200% 100%;animation:fskelshine 1.4s ease-in-out infinite .1s}
@keyframes fskelshine{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes fskelpulse{0%,100%{opacity:.15}50%{opacity:.35}}
@media(max-width:1100px){.profile-cols{grid-template-columns:1fr}}
@media(max-width:768px){.profile-hero-body{padding:0 16px 16px;gap:12px;margin-top:-10px}.profile-avatar-wrap{width:90px!important;height:90px!important}.profile-avatar{width:45px;height:45px}.profile-avatar-placeholder{width:60px;height:60px}.profile-name{font-size:20px}.profile-hero-stats{margin:0 16px;gap:0}.profile-hero-banner{height:80px}}

/* ── Profile Tabs ─────────────────────────────────────────────────────────── */
/* ── Profile Tabs — xplay style ──────────────────────────────────────────── */
.profile-tabs{
  display:flex;
  align-items:center;
  gap:0;
  border-bottom:1px solid var(--border);
  margin-bottom:28px;
}
.profile-tabs::-webkit-scrollbar{display:none}
.profile-tab{
  display:flex;
  align-items:center;
  gap:8px;
  padding:14px 22px;
  font-family:'Manrope',sans-serif;
  font-size:14px;
  font-weight:700;
  color:var(--text-3);
  cursor:pointer;
  border:none;
  background:transparent;
  border-bottom:2px solid transparent;
  margin-bottom:-1px;
  transition:color .18s, border-color .18s;
  white-space:nowrap;
  user-select:none;
  position:relative;
}
.profile-tab:hover{color:var(--text-2)}
.profile-tab.active{
  color:var(--text);
  border-bottom-color:var(--accent);
}
.profile-tab svg{opacity:.55;transition:opacity .18s;flex-shrink:0}
.profile-tab.active svg,.profile-tab:hover svg{opacity:1}
.profile-tab-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:18px;
  height:18px;
  padding:0 5px;
  background:rgba(255,255,255,.08);
  border-radius:20px;
  font-size:10px;
  font-weight:800;
  color:var(--text-3);
  line-height:1;
}
.profile-tab.active .profile-tab-badge{
  background:rgba(240,196,48,.15);
  color:var(--accent);
}
.profile-tab-panel{display:none;animation:tabFadeIn .2s ease both}
.profile-tab-panel.active{display:block}
@keyframes tabFadeIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}
.profile-tab-cols{display:grid;grid-template-columns:1fr 380px;gap:28px}
@media(max-width:1100px){.profile-tab-cols{grid-template-columns:1fr}}
#tab-panel-skinchanger .sc{margin-top:0}

</style>

<div class="breadcrumb">
  <a href="<?= SITE_URL ?>/">Головна</a>
  <span class="breadcrumb-sep">›</span>
  <?php if (!$isOwn): ?>
    <a href="<?= profileUrl($me ? h($me['steam_id']) : '') ?>">Мій профіль</a>
    <span class="breadcrumb-sep">›</span>
  <?php endif; ?>
  <a href="<?= profileUrl($viewId) ?>" style="color:var(--text)"><?= h($profile['steam_name'] ?? 'Гравець') ?></a>
  <?php if ($activeTab !== 'profile'): ?>
    <span class="breadcrumb-sep">›</span>
    <span style="color:var(--text)"><?= match($activeTab) {
        'friends'    => 'Друзі',
        'skinchanger'=> 'Skinchanger',
        'items'      => 'Предмети',
        default      => $activeTab,
    } ?></span>
  <?php endif; ?>
</div>

<!-- HERO -->
<div class="profile-hero-wrap">
<div class="profile-hero" id="profileHero">

  <!-- Banner -->
  <div class="profile-hero-banner <?= !empty($equippedBackground) && !empty($equippedBackground['image_lg']) ? 'has-bg' : '' ?>" id="profileBanner">
    <?php if (!empty($equippedBackground) && !empty($equippedBackground['image_lg'])): ?>
    <img src="<?= h(SITE_URL.'/'.$equippedBackground['image_lg']) ?>"
         class="profile-hero-banner-img"
         id="heroBannerImg"
         alt=""
         loading="eager"
         onload="this.classList.add('loaded')">
    <?php elseif (!empty($profile['avatar_url'])): ?>
    <div class="profile-hero-blur-bg" id="heroBannerBlur" style="background-image:url('<?= h($profile['avatar_url']) ?>')"></div>
    <?php endif; ?>
  </div>

  <!-- Body: аватар + інфо + actions -->
  <div class="profile-hero-body">
    <?php if (!empty($profile['avatar_url'])): ?>
      <?php $_frameShape = $equippedFrame ? ($equippedFrame['avatar_shape'] ?? 'rounded') : 'rounded'; ?>
      <div class="profile-avatar-wrap <?= $equippedFrame ? 'has-frame' : '' ?>" id="heroAvatarWrap">
        <img src="<?= h($profile['avatar_url']) ?>" class="profile-avatar" alt=""
             <?= ($equippedFrame && $_frameShape === 'square') ? 'style="border-radius:2px"' : '' ?>>
        <?php if ($equippedFrame && empty($equippedFrame['image_lg'])): ?>
          <div class="profile-avatar-frame-css"
               style="<?= ItemService::getPlaceholderBorderStyle($equippedFrame['rarity']) ?>"></div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="profile-avatar-placeholder"><?= mb_strtoupper(mb_substr($profile['steam_name'] ?? '?', 0, 1)) ?></div>
    <?php endif; ?>

    <div class="profile-hero-body-info">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <?php if ($country): ?>
          <div style="width:26px;height:26px;border-radius:50%;overflow:hidden;flex-shrink:0;border:2px solid rgba(255,255,255,.12)">
            <img src="https://flagcdn.com/w80/<?= h(strtolower($country)) ?>.png"
                 style="width:100%;height:100%;object-fit:cover"
                 onerror="this.parentElement.style.display='none'" alt="">
          </div>
        <?php endif; ?>
        <div class="profile-name"><?= h($profile['steam_name'] ?? 'Гравець') ?></div>
      </div>

      <?php if ($neverLoggedIn && !$isOwn): ?>
      <div style="display:inline-flex;align-items:center;gap:6px;color:var(--accent);font-size:12px;font-weight:700;margin-bottom:6px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Не реєструвався на CSHUNTER
      </div>
      <?php endif; ?>

      <!-- Badges row -->
      <div class="hero-actions" style="margin-top:0">
        <?php if ($faceitLevel > 0): ?>
          <span class="badge-faceit">
            <img src="<?= h($faceitIcon) ?>" style="width:16px;height:16px;object-fit:contain" alt="">
            FACEIT <?= $faceitLevel ?>
          </span>
        <?php endif; ?>
        <?php if ($favTeam): ?>
          <div class="fav-team-badge" <?= $isOwn ? 'onclick="openTeamModal()"' : '' ?> title="<?= $isOwn ? 'Змінити команду' : '' ?>">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="#f44336" stroke="none" style="flex-shrink:0"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
            <?php if (!empty($favTeam['logo'])): ?>
              <img src="<?= h($favTeam['logo']) ?>" alt="" class="fav-team-logo" onerror="this.style.display='none'">
            <?php endif; ?>
            <span class="fav-team-name"><?= h($favTeam['name']) ?></span>
            <?php if ($isOwn): ?>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.4;flex-shrink:0"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <?php endif; ?>
          </div>
        <?php elseif ($isOwn): ?>
          <button class="fav-team-add" onclick="openTeamModal()">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Вибрати улюблену команду
          </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Actions -->
    <div class="profile-hero-body-actions">
      <?php if (!$isOwn): ?>
        <?php if ($me): ?>
          <button id="followBtn" class="btn-follow <?= $isFollowing ? 'remove' : 'add' ?>" onclick="doFollow()">
            <?php if ($isFollowing): ?>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Підписаний
            <?php else: ?>
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Підписатись
            <?php endif; ?>
          </button>
        <?php else: ?>
          <a href="<?= SITE_URL ?>/auth/steam_login.php" class="btn-follow add">Увійти щоб підписатись</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats strip -->
  <div class="profile-hero-stats">
    <div class="profile-hero-stat" onclick="openFollowModal('followers')">
      <div class="follow-cnt-n" id="cntFollowers" style="font-family:'Unbounded',sans-serif;font-size:17px;font-weight:900;color:var(--text);line-height:1"><?= $followersCount ?></div>
      <div class="follow-cnt-l" style="font-family:'Manrope',sans-serif;font-size:9px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:1.5px;margin-top:3px">Фоловери</div>
    </div>
    <div class="profile-hero-stat" onclick="openFollowModal('following')">
      <div class="follow-cnt-n" id="cntFollowing" style="font-family:'Unbounded',sans-serif;font-size:17px;font-weight:900;color:var(--text);line-height:1"><?= $followingCount ?></div>
      <div class="follow-cnt-l" style="font-family:'Manrope',sans-serif;font-size:9px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:1.5px;margin-top:3px">Підписки</div>
    </div>
    <div class="profile-hero-stat">
      <div style="font-family:'Unbounded',sans-serif;font-size:17px;font-weight:900;color:var(--text);line-height:1"><?= number_format($totalKills) ?></div>
      <div style="font-family:'Manrope',sans-serif;font-size:9px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:1.5px;margin-top:3px">Вбивств</div>
    </div>
    <div class="profile-hero-stat">
      <div style="font-family:'Unbounded',sans-serif;font-size:17px;font-weight:900;color:var(--text);line-height:1"><?= $totalHours ?></div>
      <div style="font-family:'Manrope',sans-serif;font-size:9px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:1.5px;margin-top:3px">Годин</div>
    </div>
  </div>

  <?php if ($equippedFrame && !empty($equippedFrame['image_lg'])): ?>
  <canvas id="heroFrameGlowCanvas" width="176" height="176" class="profile-hero-glow-canvas"></canvas>
  <img src="<?= h(SITE_URL . '/' . $equippedFrame['image_lg']) ?>"
       id="heroFrameOverlay"
       class="profile-hero-frame-overlay"
       alt=""
       loading="eager"
       onload="positionHeroFrame(); heroExtractGlow(this)">
  <?php endif; ?>
</div><!-- /profile-hero-wrap -->

<script>
// ── Profile page config (PHP → JS) ───────────────────────────────────────────
window.__P = {
    steamId:    '<?= $viewId ?>',
    siteUrl:    '<?= SITE_URL ?>',
    profileUrl: '<?= profileUrl($viewId) ?>',
    isOwn:      <?= $isOwn ? 'true' : 'false' ?>,
    loggedIn:   <?= $me ? 'true' : 'false' ?>,
    isFollowing:<?= $isFollowing ? 'true' : 'false' ?>,
    favTeamId:  <?= !empty($favTeam['id']) ? (int)$favTeam['id'] : 'null' ?>,
    csrf:       '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>',
};

// ── Position hero frame overlay + glow canvas precisely over avatar ─────────
function positionHeroFrame() {
    const overlay  = document.getElementById('heroFrameOverlay');
    const wrap     = document.getElementById('heroAvatarWrap');
    if (!wrap) return;
    const heroWrap = wrap.closest('.profile-hero-wrap');
    if (!heroWrap) return;
    // Walk offsetParent chain to get position relative to heroWrap
    let el = wrap, left = 0, top = 0;
    while (el && el !== heroWrap) {
        left += el.offsetLeft;
        top  += el.offsetTop;
        el    = el.offsetParent;
    }
    const cx = left + wrap.offsetWidth  / 2;
    const cy = top  + wrap.offsetHeight / 2;
    if (overlay) {
        const frameSize = overlay.offsetWidth || 160;
        overlay.style.left    = (cx - frameSize / 2) + 'px';
        overlay.style.top     = (cy - frameSize / 2) + 'px';
        overlay.style.opacity = '1';
    }
    const gc = document.getElementById('heroFrameGlowCanvas');
    if (gc) {
        const glowSize = gc.offsetWidth || 220;
        gc.style.left    = (cx - glowSize / 2) + 'px';
        gc.style.top     = (cy - glowSize / 2) + 'px';
    }
}

function animateHeroFrame() {
    const duration = 400;
    const start = performance.now();
    function tick(now) {
        positionHeroFrame();
        if (now - start < duration + 50) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}
document.addEventListener('DOMContentLoaded', () => {
    positionHeroFrame();
    setTimeout(positionHeroFrame, 450);
    const overlay = document.getElementById('heroFrameOverlay');
    if (overlay) overlay.addEventListener('load', positionHeroFrame);
});
window.addEventListener('resize', positionHeroFrame);
</script>
<script src="<?= SITE_URL ?>/assets/js/profile.js?v=<?= filemtime(__DIR__.'/assets/js/profile.js') ?>"></script>


<!-- TABS -->
<div class="profile-tabs" id="profileTabs">
  <button class="profile-tab <?= $activeTab === 'profile' ? 'active' : '' ?>" data-tab="profile" onclick="switchProfileTab('profile',this)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    Профіль
  </button>
  <button class="profile-tab <?= $activeTab === 'friends' ? 'active' : '' ?>" data-tab="friends" onclick="switchProfileTab('friends',this)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Друзі
    <span class="profile-tab-badge" id="friendsTabBadge" style="display:none"></span>
  </button>
  <button class="profile-tab <?= $activeTab === 'skinchanger' ? 'active' : '' ?>" data-tab="skinchanger" onclick="switchProfileTab('skinchanger',this)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M7 13.5s1.5 2 5 2 5-2 5-2"/><path d="M9 9h.01M15 9h.01"/></svg>
    Skinchanger
  </button>
  <button class="profile-tab <?= $activeTab === 'items' ? 'active' : '' ?>" data-tab="items" onclick="switchProfileTab('items',this)">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
    Предмети
    <?php if ($itemCount > 0): ?><span class="profile-tab-badge"><?= $itemCount ?></span><?php endif; ?>
  </button>
</div>

<!-- TAB: Профіль -->
<div class="profile-tab-panel <?= $activeTab === 'profile' ? 'active' : '' ?>" id="tab-panel-profile">
  <div class="profile-tab-cols">

    <!-- LEFT: Stats -->
    <div>
      <!-- Stats card -->
    <div class="stats-card">
      <span class="stats-card-lbl">Статистика на наших серверах</span>
      <?php if (!empty($stats)): ?>
        <div class="stats-grid">
          <div class="stat-box">
            <div class="stat-box-n count-up" data-target="<?= $totalKills ?>" style="color:var(--accent)"><?= $totalKills ?></div>
            <div class="stat-box-l">Вбивств</div>
          </div>
          <div class="stat-box">
            <div class="stat-box-n <?= $totalKd >= 1.5 ? 'kd-g' : ($totalKd >= 1.0 ? 'kd-y' : 'kd-r') ?>"><?= $totalKd ?></div>
            <div class="stat-box-l">K/D</div>
          </div>
          <div class="stat-box">
            <div class="stat-box-n count-up" data-target="<?= $totalHours ?>" style="color:var(--text)"><?= $totalHours ?></div>
            <div class="stat-box-l">Годин</div>
          </div>
        </div>
        <table class="mode-mini-tbl">
          <thead>
            <tr><th>Режим</th><th>Кілі</th><th>K/D</th><th>Год</th></tr>
          </thead>
          <tbody>
          <?php foreach ($stats as $r):
            $mkd = (int)$r['deaths'] > 0 ? round($r['kills'] / $r['deaths'], 2) : (int)$r['kills'];
            $cls = $mkd >= 1.5 ? 'kd-g' : ($mkd >= 1.0 ? 'kd-y' : 'kd-r');
          ?>
          <tr>
            <td class="td-mn"><?= h($r['mode_name']) ?></td>
            <td><?= number_format((int)$r['kills']) ?></td>
            <td class="<?= $cls ?>"><?= $mkd ?></td>
            <td><?= (int)$r['playtime_hours'] ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div style="text-align:center;padding:24px 0;color:rgba(255,255,255,.25);font-family:'Manrope',sans-serif;font-size:13px">
          <div style="font-size:32px;opacity:.2;margin-bottom:8px">🎮</div>
          <?php if ($isOwn): ?>
            Ти ще не грав на наших серверах.<br>
            <a href="<?= SITE_URL ?>/" style="color:var(--accent);font-weight:700;margin-top:6px;display:inline-block">Підключитись →</a>
          <?php else: ?>
            Гравець ще не грав на наших серверах.
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Info card -->
    <div class="info-card">
      <div class="info-card-title">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Інформація
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-3)">Steam ID</span>
          <span style="font-weight:700;font-size:11px;font-family:monospace"><?= h($viewId) ?></span>
        </div>
        <?php if ($neverLoggedIn && !$isOwn): ?>
        <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;background:rgba(255,255,255,.04);border-radius:8px;border:1px solid rgba(255,255,255,.07)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.3)" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span style="font-size:12px;color:var(--text-3);font-weight:600">Ще не реєструвався на CSHunter</span>
        </div>
        <?php endif; ?>
        <?php if ($joinedDate): ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-3)">На сайті з</span>
          <span style="font-weight:700"><?= h($joinedDate) ?></span>
        </div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-3)">Фоловери</span>
          <span style="font-weight:700" id="cntFollowersSide"><?= $followersCount ?></span>
        </div>
        <?php if ($isOwn): ?>
        <div style="display:flex;justify-content:space-between">
          <span style="color:var(--text-3)">Друзів</span>
          <span style="font-weight:700" id='friendCountSide'>—</span>
        </div>
        <?php endif; ?>
        <?php if ($faceitLevel > 0): ?>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="color:var(--text-3)">FACEIT</span>
          <span style="display:flex;align-items:center;gap:6px;font-weight:700;color:<?= getFaceitLevelColor($faceitLevel) ?>">
            <img src="<?= h($faceitIcon) ?>" style="width:18px;height:18px;object-fit:contain" alt="">
            Рівень <?= $faceitLevel ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <button class="btn-copy-link" id="copyLinkBtn" onclick="copyProfileLink()">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
      Скопіювати посилання на профіль
    </button>
    </div>

    <!-- RIGHT: external links + share -->
    <div>
      <div class="info-card" style="margin-bottom:12px">
        <div class="info-card-title">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
          Посилання
        </div>
        <div style="display:flex;flex-direction:column;gap:8px">
          <a href="<?= h($steamUrl) ?>" target="_blank" rel="noopener"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:9px;text-decoration:none;transition:all var(--transition)"
             onmouseover="this.style.borderColor='rgba(240,196,48,.3)';this.style.background='rgba(240,196,48,.04)'"
             onmouseout="this.style.borderColor='rgba(255,255,255,.08)';this.style.background='rgba(255,255,255,.04)'">
            <img src="<?= SITE_URL ?>/assets/steam-logo.png" style="width:18px;height:18px;object-fit:contain;opacity:.7;flex-shrink:0" alt="">
            <span style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:var(--text-2)">Steam профіль</span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;opacity:.3"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
          <?php if ($faceitLevel > 0 && !empty($faceit['url'])): ?>
          <a href="<?= h($faceit['url']) ?>" target="_blank" rel="noopener"
             style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(255,94,0,.06);border:1px solid rgba(255,94,0,.15);border-radius:9px;text-decoration:none;transition:all var(--transition)"
             onmouseover="this.style.borderColor='rgba(255,94,0,.4)'"
             onmouseout="this.style.borderColor='rgba(255,94,0,.15)'">
            <img src="<?= h($faceitIcon) ?>" style="width:18px;height:18px;object-fit:contain;flex-shrink:0" alt="">
            <span style="font-family:'Manrope',sans-serif;font-size:13px;font-weight:700;color:#FF5E00">FACEIT — Рівень <?= $faceitLevel ?></span>
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#FF5E00" stroke-width="2" style="margin-left:auto;opacity:.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- TAB: Друзі -->
<div class="profile-tab-panel <?= $activeTab === 'friends' ? 'active' : '' ?>" id="tab-panel-friends">
  <div class="section-title"><span class="accent-dot"></span>Друзі</div>
    <div id="friendsFilterWrap" style="display:none">
      <div class="friends-filter" id="friendsFilter"></div>
    </div>
    <div id="friendsContainer">
      <!-- Skeleton loader -->
      <div id="friendsSkeleton">
        <div class="friends-skeleton-wrap">
          <div class="fskel-header">
            <img src="<?= SITE_URL ?>/assets/logo.png" alt="" class="fskel-logo">
            <div class="fskel-title">Завантаження друзів...</div>
          </div>
          <div class="friends-grid" style="margin-top:4px">
          <?php for ($i=0;$i<8;$i++): ?>
          <div class="fskel-card">
            <div class="fskel-avatar"></div>
            <div class="fskel-info">
              <div class="fskel-name"></div>
              <div class="fskel-status"></div>
            </div>
          </div>
          <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- TAB: Skinchanger -->



<?php if ($isOwn): ?>
<div class="modal-bg" id="teamModalBg" onclick="if(event.target===this)closeTeamModal()">
  <div class="modal" style="max-width:680px;width:95%">
    <div class="modal-title" style="margin-bottom:4px">Улюблена команда</div>
    <div class="modal-sub">Обери CS2 команду за яку вболіваєш</div>
    <input type="text" class="team-search" id="teamSearchInput" placeholder="🔍  Пошук команди..." autocomplete="off">
    <div id="teamModalGrid" class="team-modal-grid">
      <div class="team-modal-loading">Завантаження команд...</div>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px">
      <?php if (!empty($favTeam)): ?>
        <button class="btn-secondary" onclick="removeTeam()" id="removTeamBtn" style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px">
          <?php if (!empty($favTeam['logo'])): ?>
            <img src="<?= h($favTeam['logo']) ?>" style="width:20px;height:20px;object-fit:contain;border-radius:3px" onerror="this.style.display='none'" alt="">
          <?php endif; ?>
          Прибрати команду
        </button>
      <?php endif; ?>
      <button class="btn-primary" onclick="closeTeamModal()" style="flex:1">Закрити</button>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Follow Modal ──────────────────────────────────────────────────────────── -->
<div class="modal-bg" id="followModalBg" onclick="if(event.target===this)closeFollowModal()">
  <div class="modal" style="max-width:520px;width:95%">
    <div class="modal-title" id="followModalTitle">Фоловери</div>

    <?php if ($followersCount > 0 || $followingCount > 0): ?>
    <div class="follow-modal-tabs">
      <div class="follow-tab active" id="tabFollowers" onclick="switchFollowTab('followers')">
        Фоловери <span id="tabCntFollowers" style="opacity:.5"><?= $followersCount ?></span>
      </div>
      <div class="follow-tab" id="tabFollowing" onclick="switchFollowTab('following')">
        Підписки <span id="tabCntFollowing" style="opacity:.5"><?= $followingCount ?></span>
      </div>
    </div>
    <?php endif; ?>

    <div id="followUserList" class="follow-user-list">
      <div class="follow-loading">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:followSpin .8s linear infinite"><path d="M12 2a10 10 0 0 1 10 10"/></svg>
        Завантаження...
      </div>
    </div>

    <button class="btn-primary" onclick="closeFollowModal()" style="width:100%;margin-top:16px">Закрити</button>
  </div>
</div>


<div class="profile-tab-panel <?= $activeTab === 'skinchanger' ? 'active' : '' ?>" id="tab-panel-skinchanger">
<?php
// Підключаємо логіку та HTML skinchanger для цієї вкладки
// $viewId, $isOwn, $me — вже визначені вище в profile.php
$_GET['sc_id'] = $viewId;
try {
    include __DIR__ . '/includes/skinchanger_embed.php';
} catch (Throwable $e) {
    error_log('[CSHunter] skinchanger_embed error for ' . $viewId . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo '<div style="padding:40px;text-align:center;color:var(--text-3);font-family:Manrope,sans-serif">';
    echo '<div style="font-size:32px;opacity:.2;margin-bottom:12px">⚙️</div>';
    echo '<div style="font-weight:700">Skinchanger тимчасово недоступний</div>';
    echo '</div>';
}
?>
</div>





<!-- TAB: Предмети -->
<?php
// ── Inventory data ────────────────────────────────────────────────────────────
$inventory = [];
$allCatalogItems = []; // всі видимі предмети для показу "недоступних"
if ($profileUserId && $pdo) {
    // Використовуємо вже завантажений інвентар (без повторного запиту до БД)
    $inventory = $_earlyInventory ?? ItemService::getUserInventory($pdo, $profileUserId);
    // Для власного профілю — завантажуємо весь каталог щоб показати недоступні
    if ($isOwn) {
        $allCatalogItems = ItemService::getAllVisibleItems($pdo);
    }
}

// $itemCount вже визначено вище (рядок ~225), тут просто підтвердження
// $itemCount = count($inventory); // не потрібно — вже є

// Набір id предметів що вже є у гравця (для швидкої перевірки)
$ownedItemIds = array_column($inventory, 'id');

// Формуємо "недоступні" предмети (є в каталозі, але немає у гравця) — тільки для власного профілю
$lockedItems = [];
if ($isOwn) {
    foreach ($allCatalogItems as $ci) {
        if (!in_array($ci['id'], $ownedItemIds)) {
            $lockedItems[] = $ci;
        }
    }
}

// Групуємо по type
$inventoryByType = [];
foreach ($inventory as $item) { $inventoryByType[$item['type']][] = $item; }

$lockedByType = [];
foreach ($lockedItems as $item) { $lockedByType[$item['type']][] = $item; }

$userAvatarUrl = $profile['avatar_url'] ?? '';

$typeConfig = [
    'frame'      => ['label' => 'Рамки аватара',  'icon' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/>'],
    'background' => ['label' => 'Фони профілю',   'icon' => '<rect x="2" y="2" width="20" height="20" rx="3"/><path d="M2 8h20M8 2v6"/>'],
    'badge'      => ['label' => 'Значки',          'icon' => '<path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/>'],
    'card_style' => ['label' => 'Стилі картки',   'icon' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/>'],
];

// Визначаємо всі типи що присутні (з інвентарю або з каталогу)
$allTypes = array_unique(array_merge(array_keys($inventoryByType), array_keys($lockedByType)));
$allTypes = array_filter(array_keys($typeConfig), fn($t) => in_array($t, $allTypes));

// Екіпірована рамка та фон для панелі
$equippedInPanel = null;
$equippedBgInPanel = null;
foreach ($inventory as $it) {
    if ($it['is_equipped'] && $it['type']==='frame' && !$equippedInPanel) $equippedInPanel = $it;
    if ($it['is_equipped'] && $it['type']==='background' && !$equippedBgInPanel) $equippedBgInPanel = $it;
}
?>
<div class="profile-tab-panel <?= $activeTab === 'items' ? 'active' : '' ?>" id="tab-panel-items">
<style>
/* ═══════════════════════════════════════════════════════════
   Items Tab v2 — CSHunter
   ═══════════════════════════════════════════════════════════ */

.inv-shell {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding-bottom: 80px;
}

/* ── Left panel ─────────────────────────────────────────── */
.inv-panel {
    width: 200px;
    flex-shrink: 0;
    background: var(--bg-2);
    border: 1px solid var(--border);
    border-radius: 14px;
    overflow: hidden;
    position: sticky;
    top: 20px;
}
.inv-panel-nav-inner { padding: 6px 6px 4px; }
.inv-panel-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 9px 10px;
    border-radius: 9px;
    cursor: pointer;
    font-family: 'Manrope', sans-serif;
    font-size: 13px;
    font-weight: 700;
    color: var(--text-3);
    background: transparent;
    border: none;
    width: 100%;
    text-align: left;
    transition: all .15s;
    position: relative;
}
.inv-panel-btn svg { width: 14px; height: 14px; flex-shrink: 0; opacity: .5; transition: opacity .15s; }
.inv-panel-btn:hover { background: rgba(255,255,255,.04); color: var(--text-2); }
.inv-panel-btn:hover svg { opacity: .8; }
.inv-panel-btn.active { color: var(--accent); background: var(--accent-dim); }
.inv-panel-btn.active svg { opacity: 1; }
.inv-panel-btn.active::before {
    content: '';
    position: absolute;
    left: 0; top: 25%; bottom: 25%;
    width: 3px;
    background: var(--accent);
    border-radius: 0 3px 3px 0;
}
.inv-cnt {
    margin-left: auto;
    min-width: 20px; height: 18px;
    padding: 0 5px;
    background: rgba(255,255,255,.07);
    border-radius: 9px;
    font-size: 10px; font-weight: 900;
    color: var(--text-3);
    display: inline-flex; align-items: center; justify-content: center;
}
.inv-panel-btn.active .inv-cnt { background: rgba(240,196,48,.15); color: var(--accent); }
.inv-panel-divider {
    padding: 8px 14px 2px;
    font-size: 9px; font-weight: 900;
    text-transform: uppercase; letter-spacing: 2px;
    color: var(--text-3); opacity: .5;
}
/* Equipped preview */
.inv-panel-eq {
    border-top: 1px solid var(--border);
    padding: 10px 12px;
}
.inv-panel-eq-label {
    font-family: 'Manrope', sans-serif;
    font-size: 9px; font-weight: 900;
    text-transform: uppercase; letter-spacing: 2px;
    color: var(--text-3); margin-bottom: 7px; display: block;
}
.inv-panel-eq-card {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 9px;
    background: var(--bg-3);
    border: 1px solid var(--border);
    border-radius: 9px;
}
.inv-pq-ava {
    position: relative; width: 36px; height: 36px; flex-shrink: 0;
}
.inv-pq-ava img.ava {
    width: 22px; height: 22px;
    border-radius: 5px; object-fit: cover;
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%); z-index: 1;
}
.inv-pq-ava img.ava.sq { border-radius: 1px; }
.inv-pq-frame {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    width: calc(100% + 10px); height: calc(100% + 10px);
    object-fit: contain; z-index: 2; pointer-events: none;
}
.inv-pq-frame-css {
    position: absolute; inset: 1px; border-radius: 5px;
    z-index: 2; pointer-events: none;
}
.inv-pq-info { min-width: 0; }
.inv-pq-name { font-family:'Manrope',sans-serif; font-size:11px; font-weight:800; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.inv-pq-rar  { font-size:10px; font-weight:700; margin-top:1px; }
.inv-pq-empty { font-size:11px; color:var(--text-3); font-weight:600; padding:4px 0; text-align:center; }

/* ── Right content ──────────────────────────────────────── */
.inv-content { flex: 1; min-width: 0; }

/* Header with count */
.inv-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; padding: 0 2px;
}
.inv-header-title {
    font-family: 'Manrope', sans-serif;
    font-size: 13px; font-weight: 900;
    color: var(--text-2);
    text-transform: uppercase; letter-spacing: 1.5px;
}
.inv-header-cnt {
    font-family: 'Manrope', sans-serif;
    font-size: 12px; font-weight: 700; color: var(--text-3);
}

/* ── Grid ───────────────────────────────────────────────── */
.inv-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, 152px);
    gap: 10px;
    overflow: visible;
}

/* ── Divider "Ще можна отримати" ────────────────────────── */
.inv-locked-divider {
    grid-column: 1 / -1;
    display: flex; align-items: center; gap: 12px;
    padding: 16px 0 6px;
}
.inv-locked-divider-line {
    flex: 1; height: 1px;
    background: rgba(255,255,255,.08);
}
.inv-locked-divider-label {
    font-family: 'Manrope', sans-serif;
    font-size: 10px; font-weight: 900;
    text-transform: uppercase; letter-spacing: 2px;
    color: var(--text-3);
    white-space: nowrap;
}

/* ── Card ───────────────────────────────────────────────── */
.inv-card {
    position: relative;
    width: 152px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: visible;
    cursor: pointer;
    user-select: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 14px 10px 11px;
    text-align: center;
    box-sizing: border-box;
    transition: border-color .18s, box-shadow .18s, transform .18s;
}
.inv-card:hover {
    border-color: var(--rc, rgba(255,255,255,.2));
    box-shadow: 0 4px 20px rgba(0,0,0,.25), 0 0 0 1px var(--rc, rgba(255,255,255,.1));
    transform: translateY(-2px);
}
.inv-card.equipped {
    border-color: var(--rc, var(--accent));
    box-shadow: 0 0 0 1px var(--rc, var(--accent)), 0 4px 20px var(--rg, rgba(240,196,48,.15));
    background: rgba(255,255,255,.03);
}
.inv-card.equipped:hover {
    border-color: #F87171;
    box-shadow: 0 0 0 1px #F87171, 0 4px 16px rgba(248,113,113,.12);
}
/* Locked (недоступна) картка */
.inv-card.locked {
    cursor: default;
    opacity: .55;
    filter: grayscale(.4);
}
.inv-card.locked:hover { transform: none; box-shadow: none; border-color: var(--border); }

.inv-badge {
    position: absolute; top: 8px; right: 8px;
    background: var(--accent); color: #000;
    font-family: 'Manrope', sans-serif;
    font-size: 8px; font-weight: 900;
    padding: 2px 7px; border-radius: 5px;
    text-transform: uppercase; letter-spacing: .5px;
    z-index: 10;
}

.inv-card-bar {
    position: absolute; bottom: 0; left: 0; right: 0;
    height: 2px;
    background: var(--rc, #b0b0b0);
    opacity: .85;
    border-radius: 0 0 12px 12px;
}

/* Preview container */
.inv-card-preview {
    position: relative;
    width: 104px; height: 104px;
    margin-bottom: 10px;
    flex-shrink: 0;
    overflow: visible;
}
/* Avatar inside preview */
.inv-card-ava {
    width: 52px; height: 52px;
    border-radius: 10px;
    object-fit: cover;
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    z-index: 1;
    transition: border-radius .25s;
}
.inv-card-ava.sq { border-radius: 2px; }
.inv-card-ava-ph {
    width: 52px; height: 52px;
    border-radius: 10px;
    background: var(--bg-3);
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    z-index: 1;
}
.inv-card-frame-img {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    width: 100%; height: 100%;
    object-fit: contain; object-position: center;
    z-index: 2; pointer-events: none;
}
.inv-card-frame-css {
    position: absolute;
    inset: 8px; border-radius: 11px;
    z-index: 2; pointer-events: none;
    border: 2px solid var(--rc, #b0b0b0);
    box-shadow: 0 0 8px var(--rg, rgba(176,176,176,.3)), inset 0 0 6px var(--rg, rgba(176,176,176,.1));
}

/* Background preview in card */
.inv-card-bg-preview {
    position: absolute; inset: 0; border-radius: 10px;
    background-size: cover; background-position: center;
    z-index: 0; overflow: hidden;
}
.inv-card-bg-preview::after {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,.1), rgba(0,0,0,.5));
    border-radius: 10px;
}
.inv-card-bg-icon {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    z-index: 1;
}

/* Glow overlay (canvas-based, applied via JS) */
.inv-card-glow {
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%,-50%);
    width: 100%; height: 100%;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
    opacity: 0;
    transition: opacity .4s;
    filter: blur(18px);
}
.inv-card:hover .inv-card-glow,
.inv-card.equipped .inv-card-glow { opacity: .6; }

.inv-card-name {
    font-family: 'Manrope', sans-serif;
    font-size: 11px; font-weight: 700;
    color: var(--text-2); line-height: 1.3;
    margin-bottom: 3px; width: 100%;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
.inv-card.equipped .inv-card-name { color: var(--text); }
.inv-card-rar { font-size: 10px; font-weight: 800; margin-bottom: 2px; }
.inv-card-hint {
    font-size: 9px; font-weight: 700;
    color: var(--text-3); height: 12px;
    opacity: 0; transition: opacity .15s; margin-top: 2px;
}
.inv-card:hover .inv-card-hint { opacity: 1; }
.inv-card:not(.equipped):not(.locked):hover .inv-card-hint { color: var(--accent); }
.inv-card.equipped:hover .inv-card-hint { color: #F87171; }

/* Lock icon for locked cards */
.inv-lock-icon {
    position: absolute; top: 8px; left: 8px;
    width: 20px; height: 20px;
    background: rgba(0,0,0,.5);
    border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    z-index: 10;
}

/* Tooltip "як отримати" */
.inv-tooltip-wrap { position: relative; width: 100%; }
.inv-how-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 9px; font-weight: 800; color: var(--text-3);
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 4px; padding: 2px 6px; margin-top: 3px;
    width: 100%; justify-content: center;
    text-align: center; line-height: 1.3;
    cursor: default;
}

/* Empty state */
.inv-empty {
    padding: 60px 20px;
    text-align: center;
    color: var(--text-3);
    font-family: 'Manrope', sans-serif;
}
.inv-empty-icon { font-size: 48px; opacity: .12; margin-bottom: 14px; }
.inv-empty-title { font-size: 15px; font-weight: 700; color: var(--text-2); margin-bottom: 8px; }
.inv-empty-sub { font-size: 13px; max-width: 280px; margin: 0 auto; line-height: 1.6; }

@media(max-width: 860px) {
    .inv-shell { flex-direction: column; }
    .inv-panel { width: 100%; position: static; }
    .inv-panel-nav-inner { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px; }
    .inv-panel-btn { width: auto; padding: 7px 10px; }
    .inv-panel-btn.active::before { display: none; }
    .inv-panel-eq { display: none; }
}
@keyframes invToastIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
</style>

<div class="inv-shell">

<!-- ── Left panel ─────────────────────────────────────────────────────── -->
<div class="inv-panel">
    <div class="inv-panel-nav-inner">
        <button class="inv-panel-btn active" data-filter="all" onclick="invFilter('all',this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2"/>
                <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/>
            </svg>
            Весь інвентар
            <span class="inv-cnt"><?= count($inventory) ?></span>
        </button>
    </div>

    <?php if (!empty($allTypes)): ?>
    <div class="inv-panel-divider">Категорії</div>
    <div class="inv-panel-nav-inner" style="padding-top:2px">
        <?php foreach ($typeConfig as $tk => $tc):
            if (!in_array($tk, $allTypes)) continue;
            $cnt = count($inventoryByType[$tk] ?? []);
            $cntAll = $cnt + count($lockedByType[$tk] ?? []);
        ?>
        <button class="inv-panel-btn" data-filter="<?= $tk ?>" onclick="invFilter('<?= $tk ?>',this)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $tc['icon'] ?></svg>
            <?= $tc['label'] ?>
            <span class="inv-cnt"><?= $cnt ?><?= $isOwn && count($lockedByType[$tk] ?? []) > 0 ? '<span style="opacity:.4">+'.count($lockedByType[$tk]).'</span>' : '' ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Equipped preview -->
    <?php if ($isOwn): ?>
    <div class="inv-panel-eq">
        <span class="inv-panel-eq-label" id="invPanelEqLabel">Активна рамка</span>
        <?php if ($equippedInPanel):
            $efColor = ItemService::getRarityColor($equippedInPanel['rarity']);
            $efShape = $equippedInPanel['avatar_shape'] ?? 'rounded';
        ?>
        <div class="inv-panel-eq-card" id="invPanelEqCard">
            <div class="inv-pq-ava">
                <?php if ($userAvatarUrl): ?>
                <img src="<?= h($userAvatarUrl) ?>" class="ava <?= $efShape==='square'?'sq':'' ?>" id="invPanelAva" alt="">
                <?php endif; ?>
                <?php if (!empty($equippedInPanel['image_lg'])): ?>
                <img src="<?= h(SITE_URL.'/'.$equippedInPanel['image_lg']) ?>" class="inv-pq-frame" id="invPanelFrame" alt="">
                <?php else: ?>
                <div class="inv-pq-frame-css" id="invPanelFrameCss" style="border:2px solid <?= $efColor ?>"></div>
                <?php endif; ?>
            </div>
            <div class="inv-pq-info">
                <div class="inv-pq-name" id="invPanelEqName"><?= h($equippedInPanel['name']) ?></div>
                <div class="inv-pq-rar" id="invPanelEqRar" style="color:<?= $efColor ?>"><?= ItemService::getRarityLabel($equippedInPanel['rarity']) ?></div>
            </div>
        </div>
        <?php else: ?>
        <div class="inv-panel-eq-card" id="invPanelEqCard" style="display:none">
            <div class="inv-pq-ava">
                <?php if ($userAvatarUrl): ?><img src="<?= h($userAvatarUrl) ?>" class="ava" id="invPanelAva" alt=""><?php endif; ?>
                <div class="inv-pq-frame-css" id="invPanelFrameCss"></div>
            </div>
            <div class="inv-pq-info">
                <div class="inv-pq-name" id="invPanelEqName"></div>
                <div class="inv-pq-rar" id="invPanelEqRar"></div>
            </div>
        </div>
        <div class="inv-pq-empty" id="invPanelEqEmpty">Не обрано</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Right content ───────────────────────────────────────────────────── -->
<div class="inv-content">
    <?php if (empty($inventory) && empty($lockedItems)): ?>
    <div class="inv-empty">
        <div class="inv-empty-icon">🎁</div>
        <div class="inv-empty-title"><?= $isOwn ? 'Твій інвентар порожній' : 'Інвентар порожній' ?></div>
        <div class="inv-empty-sub">
            <?= $isOwn
                ? 'Предмети видаються автоматично за активність на сайті та серверах.'
                : 'У цього гравця ще немає жодного предмету.' ?>
        </div>
    </div>
    <?php else: ?>

    <!-- Всі категорії (all view) та окремі категорії -->
    <?php
    // Формуємо масив секцій для виводу
    // Для "all" — тільки owned, без locked
    // Для конкретної категорії — owned + divider + locked (лише для isOwn)

    // Виводимо одну загальну сітку з data-type на кожній картці
    // JS сам фільтрує
    ?>
    <div class="inv-grid" id="invGrid">

    <?php foreach ($inventory as $item):
        $rColor  = ItemService::getRarityColor($item['rarity']);
        $rGlow   = ItemService::getRarityGlow($item['rarity']);
        $isEq    = (bool)$item['is_equipped'];
        $hasImg  = !empty($item['image_lg']);
        $shape   = $item['avatar_shape'] ?? 'rounded';
        $howTo   = ItemService::getHowToObtain($item);
    ?>
    <div class="inv-card <?= $isEq ? 'equipped' : '' ?>"
         data-type="<?= h($item['type']) ?>"
         data-item-id="<?= $item['id'] ?>"
         data-item-type="<?= h($item['type']) ?>"
         data-equipped="<?= $isEq ? '1' : '0' ?>"
         data-owned="1"
         data-name="<?= h($item['name']) ?>"
         data-rcolor="<?= $rColor ?>"
         data-rglow="<?= $rGlow ?>"
         data-rlabel="<?= ItemService::getRarityLabel($item['rarity']) ?>"
         data-image-lg="<?= $hasImg ? h(SITE_URL.'/'.$item['image_lg']) : '' ?>"
         data-shape="<?= h($shape) ?>"
         style="--rc:<?= $rColor ?>;--rg:<?= $rGlow ?>"
         <?= $isOwn ? 'onclick="invClick(this)"' : '' ?>>

        <?php if ($isEq): ?><div class="inv-badge">Екіп.</div><?php endif; ?>

        <div class="inv-card-preview">
            <!-- Canvas glow -->
            <canvas class="inv-card-glow" id="glow-<?= $item['id'] ?>" width="104" height="104"></canvas>

            <?php if ($userAvatarUrl): ?>
            <img src="<?= h($userAvatarUrl) ?>" class="inv-card-ava <?= $shape==='square'?'sq':'' ?>" alt="">
            <?php else: ?>
            <div class="inv-card-ava-ph"></div>
            <?php endif; ?>

            <?php if ($item['type'] === 'frame'): ?>
                <?php if ($hasImg): ?>
                <img src="<?= h(SITE_URL.'/'.$item['image_lg']) ?>"
                     class="inv-card-frame-img"
                     alt=""
                     data-glow-target="glow-<?= $item['id'] ?>"
                     onload="invExtractGlow(this)">
                <?php else: ?>
                <div class="inv-card-frame-css"></div>
                <?php endif; ?>
            <?php elseif ($item['type'] === 'background'): ?>
                <?php if ($hasImg): ?>
                <div class="inv-card-bg-preview" style="background-image:url('<?= h(SITE_URL.'/'.$item['image_lg']) ?>')"></div>
                <?php else: ?>
                <div class="inv-card-bg-preview" style="background:linear-gradient(135deg,<?= $rColor ?>33,<?= $rColor ?>11)"></div>
                <?php endif; ?>
                <div class="inv-card-bg-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.7)" stroke-width="1.5">
                        <rect x="2" y="3" width="20" height="18" rx="2"/>
                        <circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>
                    </svg>
                </div>
            <?php endif; ?>
        </div>

        <div class="inv-card-name"><?= h($item['name']) ?></div>
        <div class="inv-card-rar" style="color:<?= $rColor ?>"><?= ItemService::getRarityLabel($item['rarity']) ?></div>
        <?php if ($isOwn): ?>
        <div class="inv-card-hint"><?= $isEq ? 'Натисни щоб зняти' : 'Натисни щоб екіпірувати' ?></div>
        <?php endif; ?>
        <div class="inv-card-bar"></div>
    </div>
    <?php endforeach; ?>

    <?php if ($isOwn && !empty($lockedItems)):
        // Locked items — з роздільником по типу
        // Але в сітці "all" вони приховані (показуються тільки при фільтрі по типу)
        foreach ($lockedItems as $item):
            $rColor = ItemService::getRarityColor($item['rarity']);
            $rGlow  = ItemService::getRarityGlow($item['rarity']);
            $hasImg = !empty($item['image_lg']);
            $shape  = $item['avatar_shape'] ?? 'rounded';
            $howTo  = ItemService::getHowToObtain($item);
    ?>
    <div class="inv-card locked"
         data-type="<?= h($item['type']) ?>"
         data-item-id="<?= $item['id'] ?>"
         data-owned="0"
         data-name="<?= h($item['name']) ?>"
         data-rcolor="<?= $rColor ?>"
         data-image-lg="<?= $hasImg ? h(SITE_URL.'/'.$item['image_lg']) : '' ?>"
         data-shape="<?= h($shape) ?>"
         style="--rc:<?= $rColor ?>;--rg:<?= $rGlow ?>">

        <div class="inv-lock-icon">
            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2.5">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
        </div>

        <div class="inv-card-preview">
            <?php if ($userAvatarUrl): ?>
            <img src="<?= h($userAvatarUrl) ?>" class="inv-card-ava <?= $shape==='square'?'sq':'' ?>" alt="">
            <?php else: ?>
            <div class="inv-card-ava-ph"></div>
            <?php endif; ?>
            <?php if ($item['type'] === 'frame' && $hasImg): ?>
            <img src="<?= h(SITE_URL.'/'.$item['image_lg']) ?>" class="inv-card-frame-img" alt="">
            <?php elseif ($item['type'] === 'frame'): ?>
            <div class="inv-card-frame-css"></div>
            <?php elseif ($item['type'] === 'background' && $hasImg): ?>
            <div class="inv-card-bg-preview" style="background-image:url('<?= h(SITE_URL.'/'.$item['image_lg']) ?>')"></div>
            <div class="inv-card-bg-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="1.5"><rect x="2" y="3" width="20" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg></div>
            <?php elseif ($item['type'] === 'background'): ?>
            <div class="inv-card-bg-preview" style="background:linear-gradient(135deg,<?= $rColor ?>33,<?= $rColor ?>11)"></div>
            <?php endif; ?>
        </div>

        <div class="inv-card-name"><?= h($item['name']) ?></div>
        <div class="inv-card-rar" style="color:<?= $rColor ?>"><?= ItemService::getRarityLabel($item['rarity']) ?></div>
        <div class="inv-how-chip" title="<?= h($howTo) ?>">
            🔒 <?= h(mb_substr($howTo, 0, 38)) ?><?= mb_strlen($howTo) > 38 ? '…' : '' ?>
        </div>
        <div class="inv-card-bar"></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    </div><!-- /inv-grid -->
    <?php endif; ?>
</div><!-- /inv-content -->

</div><!-- /inv-shell -->

<script>
// ── Filter ──────────────────────────────────────────────────────────────────
window.invCurrentFilter = 'all';

// Ховаємо locked картки одразу при завантаженні
(function() {
    document.querySelectorAll('#invGrid .inv-card[data-owned="0"]').forEach(c => {
        c.style.display = 'none';
    });
})();

window.invFilter = function(filter, btn) {
    window.invCurrentFilter = filter;
    document.querySelectorAll('.inv-panel-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    // Update panel equipped label based on filter
    const label = document.getElementById('invPanelEqLabel');
    if (label) {
        if (filter === 'background') label.textContent = 'Активний фон';
        else label.textContent = 'Активна рамка';
    }

    const cards = document.querySelectorAll('#invGrid .inv-card');

    if (filter === 'all') {
        // "Весь інвентар" — тільки owned
        cards.forEach(c => {
            const owned = c.dataset.owned === '1';
            c.style.display = owned ? '' : 'none';
        });
        // Прибираємо divider якщо є
        document.querySelectorAll('.inv-locked-divider').forEach(d => d.remove());
    } else {
        // Категорія: показуємо owned цього типу + locked цього типу (з divider)
        let hasOwned = false, hasLocked = false;
        // Remove existing divider
        document.querySelectorAll('.inv-locked-divider').forEach(d => d.remove());

        cards.forEach(c => {
            const owned  = c.dataset.owned === '1';
            const match  = c.dataset.type === filter;
            if (!match) { c.style.display = 'none'; return; }
            c.style.display = '';
            if (owned) hasOwned = true; else hasLocked = true;
        });

        // Insert divider before first locked card (if any owned exist)
        if (hasLocked && hasOwned) {
            const firstLocked = [...cards].find(c => c.dataset.owned === '0' && c.dataset.type === filter);
            if (firstLocked) {
                const divider = document.createElement('div');
                divider.className = 'inv-locked-divider';
                divider.innerHTML = `
                    <div class="inv-locked-divider-line"></div>
                    <div class="inv-locked-divider-label">Ще можна отримати</div>
                    <div class="inv-locked-divider-line"></div>`;
                firstLocked.parentNode.insertBefore(divider, firstLocked);
            }
        }
    }
};

<?php if ($isOwn): ?>
// ── Equip / Unequip ─────────────────────────────────────────────────────────
window.invClick = function(card) {
    if (card.dataset.owned !== '1') return; // locked — ignore
    const itemId   = parseInt(card.dataset.itemId);
    const itemType = card.dataset.itemType;
    const isEq     = card.dataset.equipped === '1';

    card.style.opacity = '.45';
    card.style.pointerEvents = 'none';

    fetch('/api/items_equip.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.__P.csrf   // FIX: використовуємо window.__P.csrf
        },
        body: JSON.stringify(
            isEq
                ? { action: 'unequip', item_type: itemType, csrf: window.__P.csrf }
                : { action: 'equip',   item_id: itemId,     csrf: window.__P.csrf }
        ),
    })
    .then(r => r.json())
    .then(data => {
        if (!data.ok) {
            invToast('Помилка: ' + (data.message || data.error), 'error');
            return;
        }

        if (!isEq) {
            // Unequip all of same type
            document.querySelectorAll(`.inv-card[data-item-type="${itemType}"]`).forEach(c => {
                c.classList.remove('equipped');
                c.dataset.equipped = '0';
                c.querySelector('.inv-badge')?.remove();
                const h = c.querySelector('.inv-card-hint');
                if (h) h.textContent = 'Натисни щоб екіпірувати';
            });
            // Equip this
            card.classList.add('equipped');
            card.dataset.equipped = '1';
            const b = document.createElement('div');
            b.className = 'inv-badge'; b.textContent = 'Екіп.';
            card.insertBefore(b, card.firstChild);
            const h = card.querySelector('.inv-card-hint');
            if (h) h.textContent = 'Натисни щоб зняти';

            if (itemType === 'frame') {
                const shape = card.dataset.shape || 'rounded';
                invSetHeroFrame(card.dataset.imageLg, card.dataset.rcolor, card.dataset.rglow, shape);
                invUpdatePanel(card.dataset.name, card.dataset.rlabel, card.dataset.rcolor, card.dataset.imageLg, shape);
            } else if (itemType === 'background') {
                invSetHeroBanner(card.dataset.imageLg);
                invUpdatePanelBg(card.dataset.name, card.dataset.rlabel, card.dataset.rcolor, card.dataset.imageLg);
            }
        } else {
            card.classList.remove('equipped');
            card.dataset.equipped = '0';
            card.querySelector('.inv-badge')?.remove();
            const h = card.querySelector('.inv-card-hint');
            if (h) h.textContent = 'Натисни щоб екіпірувати';
            if (itemType === 'frame') { invClearHeroFrame(); invClearPanel(); }
            else if (itemType === 'background') { invClearHeroBanner(); invClearPanel(); }
        }
        const toastMsg = isEq
            ? (itemType === 'background' ? 'Фон знято' : 'Рамку знято')
            : 'Екіпіровано!';
        invToast(toastMsg);
    })
    .catch(() => invToast('Помилка мережі', 'error'))
    .finally(() => { card.style.opacity = ''; card.style.pointerEvents = ''; });
};

// ── Hero glow extraction ─────────────────────────────────────────────────────
function heroExtractGlow(img) {
    const canvas = document.getElementById('heroFrameGlowCanvas');
    if (!canvas) return;
    try {
        const tmp = document.createElement('canvas');
        tmp.width = 176; tmp.height = 176;
        const ctx = tmp.getContext('2d');
        ctx.drawImage(img, 0, 0, 176, 176);
        const data = ctx.getImageData(0, 0, 176, 176).data;
        let r = 0, g = 0, b = 0, cnt = 0;
        for (let i = 0; i < data.length; i += 4) {
            if (data[i+3] < 50) continue;
            r += data[i]; g += data[i+1]; b += data[i+2];
            cnt++;
        }
        if (cnt < 50) return;
        r = Math.round(r/cnt);
        g = Math.round(g/cnt);
        b = Math.round(b/cnt);
        // Підвищуємо насиченість
        const max = Math.max(r,g,b), min = Math.min(r,g,b);
        if (max > min) {
            const boost = 1.6;
            const mid = (max+min)/2;
            r = Math.min(255, Math.round(mid + (r-mid)*boost));
            g = Math.min(255, Math.round(mid + (g-mid)*boost));
            b = Math.min(255, Math.round(mid + (b-mid)*boost));
        }
        const glowCtx = canvas.getContext('2d');
        glowCtx.clearRect(0, 0, 176, 176);
        const grad = glowCtx.createRadialGradient(88, 88, 20, 88, 88, 88);
        grad.addColorStop(0,    `rgba(${r},${g},${b},0.72)`);
        grad.addColorStop(0.5,  `rgba(${r},${g},${b},0.44)`);
        grad.addColorStop(0.85, `rgba(${r},${g},${b},0.12)`);
        grad.addColorStop(1,    `rgba(${r},${g},${b},0)`);
        glowCtx.fillStyle = grad;
        glowCtx.fillRect(0, 0, 176, 176);
        // Show canvas
        const gcEl = document.getElementById('heroFrameGlowCanvas');
        if (gcEl) gcEl.style.opacity = '0.3';
    } catch(e) { /* CORS — мовчки */ }
}

// ── Hero avatar frame live update ────────────────────────────────────────────
function invSetHeroFrame(imageLg, rcolor, rglow, shape) {
    const wrap = document.querySelector('.profile-avatar-wrap');
    if (!wrap) return;
    wrap.classList.add('has-frame');

    // Apply avatar shape
    const ava = wrap.querySelector('.profile-avatar');
    if (ava) ava.style.borderRadius = shape === 'square' ? '2px' : '';

    // Remove old CSS frame if any
    wrap.querySelectorAll('.profile-avatar-frame-css').forEach(el => el.remove());

    if (imageLg) {
        // Update or create the overlay element on hero-wrap level
        let overlay = document.getElementById('heroFrameOverlay');
        if (!overlay) {
            overlay = document.createElement('img');
            overlay.id = 'heroFrameOverlay';
            overlay.className = 'profile-hero-frame-overlay';
            overlay.alt = '';
            const heroWrap = wrap.closest('.profile-hero-wrap');
            if (heroWrap) heroWrap.appendChild(overlay);
        }
        overlay.style.opacity = '0';
        overlay.onload = () => { positionHeroFrame(); heroExtractGlow(overlay); };
        overlay.src = imageLg;

        // Ensure glow canvas exists inside wrap
        if (!document.getElementById('heroFrameGlowCanvas')) {
            const gc = document.createElement('canvas');
            gc.id = 'heroFrameGlowCanvas'; gc.width = 176; gc.height = 176;
            gc.className = 'profile-hero-glow-canvas';
            const heroWrap2 = wrap.closest('.profile-hero-wrap');
            if (heroWrap2) heroWrap2.appendChild(gc);
        }
    } else {
        // CSS border frame
        const div = document.createElement('div');
        div.className = 'profile-avatar-frame-css';
        div.style.cssText = `position:absolute;inset:0;border-radius:14px;z-index:2;pointer-events:none;border:2px solid ${rcolor||'#b0b0b0'};box-shadow:0 0 8px ${rglow||'rgba(176,176,176,.3)'}`;
        wrap.appendChild(div);
        // Hide overlay if exists
        const overlay = document.getElementById('heroFrameOverlay');
        if (overlay) overlay.style.opacity = '0';
    }
}

// ── Hero banner live update ─────────────────────────────────────────────────
function invSetHeroBanner(imageLg) {
    const banner = document.getElementById('profileBanner');
    if (!banner) return;
    banner.classList.add('has-bg');
const body = document.querySelector('.profile-hero-body');
    if (body) body.style.marginTop = '-80px';
    animateHeroFrame();
    // Remove blur bg
    const blur = document.getElementById('heroBannerBlur');
    if (blur) blur.style.opacity = '0';
    // Update or create banner img
    let img = document.getElementById('heroBannerImg');
    if (!img) {
        img = document.createElement('img');
        img.id = 'heroBannerImg';
        img.className = 'profile-hero-banner-img';
        img.alt = '';
        banner.insertBefore(img, banner.firstChild);
    }
    img.classList.remove('loaded');
    img.onload = () => img.classList.add('loaded');
    img.src = imageLg;
}

function invClearHeroBanner() {
    const banner = document.getElementById('profileBanner');
    if (banner) banner.classList.remove('has-bg');
    const body = document.querySelector('.profile-hero-body');
    if (body) body.style.marginTop = '';
    animateHeroFrame();
    const img = document.getElementById('heroBannerImg');
    if (img) { img.classList.remove('loaded'); setTimeout(() => img.remove(), 400); }
    const blur = document.getElementById('heroBannerBlur');
    if (blur) blur.style.opacity = '';
}

function invUpdatePanelBg(name, rlabel, rcolor, imageLg) {
    const label = document.getElementById('invPanelEqLabel');
    if (label) label.textContent = 'Активний фон';
    const card    = document.getElementById('invPanelEqCard');
    const nameEl  = document.getElementById('invPanelEqName');
    const rarEl   = document.getElementById('invPanelEqRar');
    const emptyEl = document.getElementById('invPanelEqEmpty');
    if (!card) return;
    if (nameEl) nameEl.textContent = name;
    if (rarEl)  { rarEl.textContent = rlabel; rarEl.style.color = rcolor; }
    // Show bg thumbnail in panel
    const avaWrap = card.querySelector('.inv-pq-ava');
    if (avaWrap && imageLg) {
        avaWrap.style.cssText = 'background-image:url('+imageLg+');background-size:cover;background-position:center;border-radius:5px;overflow:hidden';
        avaWrap.querySelectorAll('img, div').forEach(el => el.style.display = 'none');
    }
    card.style.display = '';
    if (emptyEl) emptyEl.style.display = 'none';
}

function invClearHeroFrame() {
    const wrap = document.querySelector('.profile-avatar-wrap');
    if (!wrap) return;
    wrap.classList.remove('has-frame');
    wrap.querySelectorAll('.profile-avatar-frame, .profile-avatar-frame-css').forEach(el => el.remove());
    // Remove overlay entirely
    const overlay = document.getElementById('heroFrameOverlay');
    if (overlay) overlay.remove();
    // Clear glow
    const gc = document.getElementById('heroFrameGlowCanvas');
    if (gc) { gc.getContext('2d').clearRect(0, 0, gc.width, gc.height); gc.style.opacity = '0'; }
    // Restore avatar radius
    const ava = wrap.querySelector('.profile-avatar');
    if (ava) ava.style.borderRadius = '';
}

// ── Panel equipped preview live update ──────────────────────────────────────
function invUpdatePanel(name, rlabel, rcolor, imageLg, shape) {
    const card    = document.getElementById('invPanelEqCard');
    const nameEl  = document.getElementById('invPanelEqName');
    const rarEl   = document.getElementById('invPanelEqRar');
    const emptyEl = document.getElementById('invPanelEqEmpty');
    const avaEl   = document.getElementById('invPanelAva');
    const frameEl = document.getElementById('invPanelFrame');
    const frameCss= document.getElementById('invPanelFrameCss');
    if (!card) return;
    if (nameEl) nameEl.textContent = name;
    if (rarEl)  { rarEl.textContent = rlabel; rarEl.style.color = rcolor; }
    if (avaEl)  { avaEl.className = 'ava' + (shape === 'square' ? ' sq' : ''); }
    // Update frame
    const avaWrap = card.querySelector('.inv-pq-ava');
    if (avaWrap) {
        avaWrap.querySelectorAll('.inv-pq-frame, .inv-pq-frame-css').forEach(el => el.remove());
        if (imageLg) {
            const img = document.createElement('img');
            img.className = 'inv-pq-frame'; img.alt = ''; img.src = imageLg; img.id = 'invPanelFrame';
            avaWrap.appendChild(img);
        } else {
            const div = document.createElement('div');
            div.className = 'inv-pq-frame-css'; div.id = 'invPanelFrameCss';
            div.style.border = '2px solid ' + rcolor;
            avaWrap.appendChild(div);
        }
    }
    card.style.display = '';
    if (emptyEl) emptyEl.style.display = 'none';
}

function invClearPanel() {
    const card    = document.getElementById('invPanelEqCard');
    const emptyEl = document.getElementById('invPanelEqEmpty');
    const label   = document.getElementById('invPanelEqLabel');
    if (card) {
        card.style.display = 'none';
        const avaWrap = card.querySelector('.inv-pq-ava');
        if (avaWrap) { avaWrap.style.cssText = ''; avaWrap.querySelectorAll('img, div').forEach(el => el.style.display = ''); }
    }
    if (emptyEl) emptyEl.style.display = '';
    if (label) label.textContent = 'Активна рамка';
    const avaEl = document.getElementById('invPanelAva');
    if (avaEl) avaEl.className = 'ava';
}
<?php endif; ?>

// ── Canvas glow extraction ───────────────────────────────────────────────────
// Витягуємо домінантний колір з PNG рамки через Canvas API
function invExtractGlow(img) {
    const canvasId = img.dataset.glowTarget;
    if (!canvasId) return;
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    // Анімовані WebP/APNG — не можна достовірно читати через canvas (security)
    // Просто пропускаємо без помилки
    try {
        const ctx = canvas.getContext('2d');
        const w = canvas.width, h = canvas.height;

        const tmpCanvas = document.createElement('canvas');
        tmpCanvas.width = w; tmpCanvas.height = h;
        const tmpCtx = tmpCanvas.getContext('2d');
        tmpCtx.drawImage(img, 0, 0, w, h);

        const data = tmpCtx.getImageData(0, 0, w, h).data;

        // Збираємо всі непрозорі пікселі (alpha > 50)
        let r = 0, g = 0, b = 0, cnt = 0;
        for (let i = 0; i < data.length; i += 4) {
            if (data[i+3] < 50) continue; // прозорий — пропускаємо
            r += data[i]; g += data[i+1]; b += data[i+2];
            cnt++;
        }
        if (cnt < 50) return; // майже весь прозорий — нічого не робимо

        r = Math.round(r / cnt);
        g = Math.round(g / cnt);
        b = Math.round(b / cnt);

        // Підвищуємо насиченість
        const max = Math.max(r,g,b), min = Math.min(r,g,b);
        if (max > min) {
            const boost = 1.4;
            const mid = (max + min) / 2;
            r = Math.min(255, Math.round(mid + (r - mid) * boost));
            g = Math.min(255, Math.round(mid + (g - mid) * boost));
            b = Math.min(255, Math.round(mid + (b - mid) * boost));
        }

        // Малюємо радіальний гlow на canvas
        const gradient = ctx.createRadialGradient(w/2, h/2, 0, w/2, h/2, w/2);
        gradient.addColorStop(0, `rgba(${r},${g},${b},0.8)`);
        gradient.addColorStop(1, `rgba(${r},${g},${b},0)`);
        ctx.clearRect(0, 0, w, h);
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, w, h);

        // Зберігаємо колір як CSS-змінну на картці (для border glow)
        const card = canvas.closest('.inv-card');
        if (card && card.dataset.rcolor) {
            // Оновлюємо glow і border тільки якщо колір не надто сірий
            const saturation = max - min;
            if (saturation > 30) {
                const glowColor = `rgba(${r},${g},${b},0.5)`;
                card.style.setProperty('--rg', glowColor);
                if (card.classList.contains('equipped')) {
                    card.style.setProperty('--rc', `rgb(${r},${g},${b})`);
                }
            }
        }
    } catch(e) {
        // CORS або security error — мовчки ігноруємо
    }
}

// Apply glow to already-loaded images (якщо onload не спрацював)
document.addEventListener('DOMContentLoaded', () => {
    // Glow в картках предметів
    document.querySelectorAll('.inv-card-frame-img[data-glow-target]').forEach(img => {
        if (img.complete && img.naturalWidth > 0) invExtractGlow(img);
    });
    // Glow в hero профілі — використовуємо overlay
    const heroFrame = document.getElementById('heroFrameOverlay');
    if (heroFrame) {
        if (heroFrame.complete && heroFrame.naturalWidth > 0) {
            heroExtractGlow(heroFrame);
        } else {
            heroFrame.addEventListener('load', () => heroExtractGlow(heroFrame));
        }
    }
});

// ── Toast ────────────────────────────────────────────────────────────────────
function invToast(msg, type = 'success') {
    document.getElementById('invToast')?.remove();
    const t = document.createElement('div'); t.id = 'invToast';
    t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:9999;
        padding:11px 18px;border-radius:10px;font-family:'Manrope',sans-serif;
        font-size:13px;font-weight:700;box-shadow:0 4px 20px rgba(0,0,0,.5);animation:invToastIn .2s ease;
        ${type==='error'
            ?'background:#1a0a0a;border:1px solid rgba(248,113,113,.3);color:#F87171'
            :'background:#0a1a0a;border:1px solid rgba(74,222,128,.3);color:#4ADE80'}`;
    t.textContent = msg; document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}
</script>
</div><!-- /tab-panel-items -->

<?php include __DIR__ . '/includes/footer.php'; ?>