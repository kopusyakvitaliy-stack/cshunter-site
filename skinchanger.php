<?php
error_reporting(0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Skinchanger';
$me   = getUser();
$user = $me; // за замовчуванням — свій профіль

// ── Визначаємо чий скінченжер переглядаємо ───────────────────────────────────
$viewId   = $_GET['sc_id'] ?? ($me['steam_id'] ?? null);
$isOwn    = $me && $viewId === $me['steam_id'];
$isViewer = !$isOwn; // переглядаємо чужий

// Якщо чужий профіль — завантажуємо його дані
$viewProfile = null;
if ($viewId && !$isOwn) {
    if ($pdo) {
        $s = $pdo->prepare('SELECT * FROM users WHERE steam_id = ?');
        $s->execute([$viewId]);
        $viewProfile = $s->fetch() ?: null;
    }
    if (!$viewProfile) {
        $sd = fetchSteamUser($viewId);
        if ($sd) {
            $viewProfile = [
                'steam_id'   => $sd['steamid'],
                'steam_name' => $sd['personaname'],
                'avatar_url' => $sd['avatarfull'] ?? '',
            ];
        }
    }
}

// Для завантаження скінів використовуємо $viewId (може бути чужий)
$steamIdForSkins = $viewId ?? ($me['steam_id'] ?? null);

$CT_ONLY_DEF = [32,34,3,38,8,10,16,27,60,61];
$T_ONLY_DEF  = [4,39,7,11,13,17,29,30];

$userSkins = []; $userKnife = []; $userGloves = []; $userMusic = []; $userPins = []; $userAgent = [];
if ($steamIdForSkins) {
  try {
    $spdo = new PDO("mysql:host=".SHARED_DB_HOST.";dbname=".SHARED_DB_NAME.";charset=utf8mb4",
      SHARED_DB_USER, SHARED_DB_PASS,
      [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $st=$spdo->prepare("SELECT * FROM wp_player_skins WHERE steamid=?"); $st->execute([$steamIdForSkins]);
    foreach($st->fetchAll() as $r) $userSkins[$r['weapon_defindex'].'_'.$r['weapon_team']]=$r;
    $st=$spdo->prepare("SELECT * FROM wp_player_knife WHERE steamid=?"); $st->execute([$steamIdForSkins]);
    foreach($st->fetchAll() as $r) $userKnife[$r['weapon_team']]=$r['knife'];
    $st=$spdo->prepare("SELECT * FROM wp_player_gloves WHERE steamid=?"); $st->execute([$steamIdForSkins]);
    foreach($st->fetchAll() as $r) $userGloves[$r['weapon_team']]=$r['weapon_defindex'];
    $st=$spdo->prepare("SELECT * FROM wp_player_music WHERE steamid=?"); $st->execute([$steamIdForSkins]);
    foreach($st->fetchAll() as $r) $userMusic[$r['weapon_team']]=$r['music_id'];
    $st=$spdo->prepare("SELECT * FROM wp_player_pins WHERE steamid=?"); $st->execute([$steamIdForSkins]);
    foreach($st->fetchAll() as $r) $userPins[$r['weapon_team']]=$r['id'];
    // Agents - try both possible table structures
    try {
      $st = $spdo->prepare("SELECT * FROM wp_player_agents WHERE steamid=?");
      $st->execute([$user['steam_id']]);
      foreach ($st->fetchAll() as $r) {
        // Structure A: agent_ct / agent_t columns
        if (isset($r['agent_ct'])) {
          $userAgent[3] = $r['agent_ct'] ?? '';
          $userAgent[2] = $r['agent_t']  ?? '';
          break;
        }
        // Structure B: weapon_team + agent columns
        if (isset($r['weapon_team'])) {
          $userAgent[(int)$r['weapon_team']] = $r['agent'] ?? '';
        }
      }
    } catch (Throwable $e) { /* table may not exist */ }
  } catch(Throwable $e) {}
}

$IMG = 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/';

// Build paint_id -> paint_name lookup from skins_uk.json
$paintNameMap = [];
$skinsFile = __DIR__ . '/data/skins_uk.json';
if (file_exists($skinsFile)) {
  $skinsRaw = json_decode(file_get_contents($skinsFile), true) ?? [];
  foreach ($skinsRaw as $s) {
    $paint = (string)$s['paint'];
    if (!isset($paintNameMap[$paint])) {
      // Extract short name (after |)
      $name = $s['paint_name'] ?? '';
      if (strpos($name,'|') !== false) $name = trim(explode('|',$name,2)[1]);
      $paintNameMap[$paint] = $name;
    }
  }
}
// Add paint_name to userSkins
foreach ($userSkins as $k => &$sk) {
  $paintId = (string)($sk['weapon_paint_id'] ?? 0);
  $sk['_paint_name'] = $paintNameMap[$paintId] ?? '';
}
unset($sk);

$CATS = [
  'rifles'  => ['🎯','Гвинтівки',[
    'weapon_ak47'=>['АК-47',7],'weapon_aug'=>['АУГ',8],'weapon_famas'=>['ФАМАС',10],
    'weapon_galilar'=>['Ґаліл',13],'weapon_m4a1'=>['М4А4',16],'weapon_m4a1_silencer'=>['М4А1-С',60],
    'weapon_sg556'=>['СГ 553',39],
  ]],
  'pistols' => ['🔫','Пістолети',[
    'weapon_deagle'=>['Deagle',1],'weapon_elite'=>['Беретти',2],'weapon_fiveseven'=>['Five-seveN',3],
    'weapon_glock'=>['Ґлок',4],'weapon_hkp2000'=>['П2000',32],'weapon_p250'=>['П250',36],
    'weapon_cz75a'=>['ЧЗ-75',63],'weapon_usp_silencer'=>['УСП-С',61],'weapon_tec9'=>['ТЕК-9',30],
    'weapon_revolver'=>['Р8',64],
  ]],
  'snipers' => ['🔭','Снайперки',[
    'weapon_awp'=>['АВП',9],'weapon_ssg08'=>['ССГ 08',40],'weapon_g3sg1'=>['Г3-СГ1',11],'weapon_scar20'=>['СКАР-20',38],
  ]],
  'smgs'    => ['⚡','ПК',[
    'weapon_bizon'=>['Бізон',26],'weapon_mac10'=>['МАК-10',17],'weapon_mp5sd'=>['МП5-СД',23],
    'weapon_mp7'=>['МП7',33],'weapon_mp9'=>['МП9',34],'weapon_p90'=>['П90',19],'weapon_ump45'=>['УМП-45',24],
  ]],
  'heavy'   => ['💣','Важка',[
    'weapon_m249'=>['М249',14],'weapon_negev'=>['Неґев',28],'weapon_mag7'=>['МАГ-7',27],
    'weapon_nova'=>['Нова',35],'weapon_sawedoff'=>['Обріз',29],'weapon_xm1014'=>['М1014',25],
  ]],
  'knives'  => ['🗡️','Ножі ★',[
    'weapon_bayonet'=>['Штик-ніж',500],'weapon_knife_butterfly'=>['Балісонг',515],
    'weapon_knife_canis'=>['Виживання',518],'weapon_knife_css'=>['Класичний',503],
    'weapon_knife_falchion'=>['Фальшіон',512],'weapon_knife_flip'=>['Складаний',505],
    'weapon_knife_gut'=>['Білувальний',506],'weapon_knife_gypsy_jackknife'=>['Наваха',520],
    'weapon_knife_karambit'=>['Керамбіт',507],'weapon_knife_kukri'=>['Кукрі',526],
    'weapon_knife_m9_bayonet'=>['Штик М9',508],'weapon_knife_outdoor'=>['Польовий',521],
    'weapon_knife_push'=>['Тіньові',516],'weapon_knife_skeleton'=>['Скелетний',525],
    'weapon_knife_stiletto'=>['Стилет',522],'weapon_knife_survival_bowie'=>['Бові',514],
    'weapon_knife_tactical'=>['Мисливський',509],'weapon_knife_ursus'=>['Ведмежий',519],
    'weapon_knife_widowmaker'=>['Кіготь',523],'weapon_knife_cord'=>['Тактичний',517],
  ]],
  'gloves'  => ['🧤','Рукавиці ★',[
    'glove_0'    =>['Default',0],
    'glove_4725' =>['Зламане ікло',4725],
    'glove_5027' =>['Дойда',5027],
    'glove_5030' =>['Спортивні',5030],
    'glove_5031' =>['Водія',5031],
    'glove_5032' =>['Бинти',5032],
    'glove_5033' =>['Мотоцикліста',5033],
    'glove_5034' =>['Спеціаліста',5034],
    'glove_5035' =>['Гідра',5035],
  ]],
];

// Glove preview images (first skin of each type)
$GLOVE_PREVIEW = [
  4725 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/studded_brokenfang_gloves-10087.png',
  5027 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/studded_bloodhound_gloves-10006.png',
  5030 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/sporty_gloves-10047.png',
  5031 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/slick_gloves-10013.png',
  5032 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/leather_handwraps-10010.png',
  5033 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/motorcycle_gloves-10024.png',
  5034 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/specialist_gloves-10030.png',
  5035 => 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/studded_hydra_gloves-10057.png',
];
$MUSIC_PREVIEW = 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/music_kit-1.png';
$PIN_PREVIEW   = 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/collectible-875.png';

include __DIR__ . '/includes/header.php';
?>
<style>
.sc{display:flex;flex-direction:column;--team-rgb:66,165,245}
.sc-topbar{display:flex;align-items:center;gap:10px;margin-bottom:28px;flex-wrap:wrap}
.sc-team-selector{
  display:flex;
  gap:16px;
  flex:1;
  justify-content:center;
  align-items:center;
  padding:10px 0;
}

.sc-team-btn{
  width:80px;
  height:80px;
  background:transparent;
  border:none;
  cursor:pointer;
  padding:0;
  display:flex;
  align-items:center;
  justify-content:center;
  transition:all .5s cubic-bezier(.34,.1,.68,.55);
  position:relative;
  opacity:.5;
}

/* Hover state - small scale increase */
.sc-team-btn:hover{
  opacity:.75;
  transform:scale(1.08);
}

/* Active state - bigger and glowing with faster animation */
.sc-team-btn.active{
  opacity:1;
  transform:scale(1.2);
  animation:teamBreathe 1.6s ease-in-out infinite;
}

/* Breathing animation for active button */
@keyframes teamBreathe{
  0%, 100%{transform:scale(1.2)}
  50%{transform:scale(1.18)}
}

/* Glow effect for active button */
.sc-team-btn.active::before{
  content:'';
  position:absolute;
  inset:-10px;
  border-radius:50%;
  background:radial-gradient(circle, currentColor 0%, transparent 70%);
  opacity:.25;
  z-index:-1;
}

.sc-team-btn.ct-btn.active::before{
  color:#42a5f5;
}

.sc-team-btn.t-btn.active::before{
  color:#c8a85a;
}

/* Image styling */
.sc-team-btn img{
  width:100%;
  height:100%;
  object-fit:contain;
  transition:all .3s ease;
}

.sc-team-btn.ct-btn img{
  filter:drop-shadow(0 0 12px rgba(66,165,245,.4));
}

.sc-team-btn.t-btn img{
  filter:drop-shadow(0 0 12px rgba(200,168,90,.4));
}

/* Active button enhanced glow */
.sc-team-btn.ct-btn.active img{
  filter:drop-shadow(0 0 25px rgba(66,165,245,.7)) brightness(1.15);
}

.sc-team-btn.t-btn.active img{
  filter:drop-shadow(0 0 25px rgba(200,168,90,.7)) brightness(1.15);
}
.sc-rbtn{display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius-sm);border:1px solid var(--border-2);background:transparent;color:var(--text-3);font-size:11px;font-weight:700;cursor:pointer;transition:all var(--transition)}
.sc-rbtn:hover{border-color:var(--red);color:var(--red)}
.sc-dice-btn{display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--radius-sm);border:1px solid rgba(240,196,48,.35);background:rgba(240,196,48,.06);color:var(--accent);font-size:11px;font-weight:700;cursor:pointer;transition:all var(--transition)}
.sc-dice-btn:hover{border-color:var(--accent);background:rgba(240,196,48,.12);transform:scale(1.04)}
.sc-dice-btn img{width:14px;height:14px;object-fit:contain;filter:invert(1) sepia(1) saturate(3) hue-rotate(5deg)}
.sc-rand-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:2000;backdrop-filter:blur(6px);align-items:center;justify-content:center}
.sc-rand-bg.open{display:flex}
.sc-rand-m{background:var(--bg-2);border:1px solid rgba(240,196,48,.3);border-radius:var(--radius);width:min(480px,94vw);padding:28px;animation:scUp .22s cubic-bezier(.4,0,.2,1);text-align:center;position:relative}
.sc-rand-icon{width:64px;height:64px;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;background:rgba(240,196,48,.1);border-radius:50%;border:1.5px solid rgba(240,196,48,.3)}
.sc-rand-icon img{width:36px;height:36px;object-fit:contain;filter:invert(1) sepia(1) saturate(3) hue-rotate(5deg)}
.sc-rand-title{font-size:20px;font-weight:900;letter-spacing:1px;color:var(--text);margin-bottom:8px}
.sc-rand-sub{font-size:13px;color:var(--text-3);line-height:1.6;margin-bottom:16px}
.sc-rand-warn{font-size:11px;color:rgba(240,196,48,.9);background:rgba(240,196,48,.07);border:1px solid rgba(240,196,48,.2);border-radius:8px;padding:10px 14px;margin-bottom:22px;line-height:1.5;text-align:left}
.sc-rand-btns{display:flex;gap:10px;justify-content:center}
.sc-rand-cancel{flex:1;padding:11px;border-radius:var(--radius-sm);border:1px solid var(--border-2);background:transparent;color:var(--text-3);font-size:13px;font-weight:700;cursor:pointer;transition:all var(--transition)}
.sc-rand-cancel:hover{border-color:var(--border);color:var(--text)}
.sc-rand-confirm{flex:1;padding:11px;border-radius:var(--radius-sm);border:1px solid rgba(240,196,48,.5);background:linear-gradient(135deg,rgba(240,196,48,.18),rgba(240,196,48,.08));color:var(--accent);font-size:13px;font-weight:900;cursor:pointer;transition:all var(--transition);letter-spacing:.5px}
.sc-rand-confirm:hover{background:linear-gradient(135deg,rgba(240,196,48,.28),rgba(240,196,48,.14));border-color:var(--accent)}
.sc-rand-confirm:disabled{opacity:.4;cursor:not-allowed;pointer-events:none}
.sc-rand-progress{display:none;margin-top:18px}
.sc-rand-progress-bar{width:100%;height:4px;background:var(--surface-2);border-radius:2px;overflow:hidden;margin-bottom:8px}
.sc-rand-progress-fill{height:100%;background:linear-gradient(90deg,var(--accent),#f0a500);border-radius:2px;transition:width .25s ease;width:0%}
.sc-rand-progress-txt{font-size:11px;color:var(--text-3)}
.sc-sw{position:relative;margin-bottom:24px}
.sc-si{width:100%;background:rgba(255,255,255,.03);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:14px;font-family:'Manrope',sans-serif;padding:12px 16px 12px 44px;outline:none;transition:all .3s;backdrop-filter:blur(4px)}
.sc-si:focus{border-color:var(--accent);background:rgba(240,196,48,.04);box-shadow:0 0 0 3px rgba(240,196,48,.08)}
.sc-sico{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none}
.sc-cat{margin-bottom:32px}
.sc-cat-title{font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase;color:var(--text-3);margin-bottom:14px;display:flex;align-items:center;gap:10px}
.sc-cat-title::after{content:'';flex:1;height:1px;background:linear-gradient(to right,var(--border),transparent)}
.sc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.sc-card{position:relative;cursor:pointer;border-radius:12px;overflow:hidden;background:linear-gradient(145deg,rgba(255,255,255,.04),rgba(255,255,255,.01));border:1px solid rgba(255,255,255,.07);transition:all .35s cubic-bezier(.4,0,.2,1)}
.sc-card::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(255,255,255,.06),transparent 60%);opacity:0;transition:opacity .35s;pointer-events:none;z-index:1}
.sc-card:hover{border-color:rgba(255,255,255,.18);transform:translateY(-4px) scale(1.01);box-shadow:0 16px 40px rgba(0,0,0,.55)}
.sc-card:hover::before{opacity:1}
.sc-card.has-skin{border-color:rgba(var(--team-rgb),.35);background:linear-gradient(145deg,rgba(var(--team-rgb),.07),rgba(255,255,255,.01));transition:background .3s ease, border-color .3s ease, transform .3s ease, box-shadow .3s ease}
.sc-card.has-skin.loaded{animation:scCardEnter .5s cubic-bezier(.34,.1,.68,.55) forwards}
@keyframes scCardEnter{
  from{
    opacity:0;
    transform:translateY(20px) scale(.98);
    background:linear-gradient(145deg,rgba(var(--team-rgb),.02),rgba(255,255,255,.005));
  }
  to{
    opacity:1;
    transform:translateY(0) scale(1);
    background:linear-gradient(145deg,rgba(var(--team-rgb),.12),rgba(255,255,255,.01));
  }
}
.sc-card.has-skin:hover{border-color:rgba(var(--team-rgb),.6);background:linear-gradient(145deg,rgba(var(--team-rgb),.18),rgba(255,255,255,.02));transform:translateY(-4px) scale(1.03);box-shadow:0 16px 40px rgba(0,0,0,.55), 0 0 30px rgba(var(--team-rgb),.2)}
.sc-card:not(.has-skin){opacity:.55;background:linear-gradient(145deg,rgba(255,255,255,.02),rgba(255,255,255,.005))}
.sc-card:not(.has-skin) .sc-card-img img{filter:grayscale(.8) brightness(.7) drop-shadow(0 4px 14px rgba(0,0,0,.7))}
.sc-card:not(.has-skin):hover{opacity:1;background:linear-gradient(145deg,rgba(255,255,255,.04),rgba(255,255,255,.01))}
.sc-card:not(.has-skin):hover .sc-card-img img{filter:grayscale(0) brightness(1) drop-shadow(0 10px 24px rgba(0,0,0,.8))}
.sc-card.locked{display:none}
.sc-card-img{position:relative;padding:20px 16px 10px;display:flex;align-items:center;justify-content:center;min-height:130px;background:radial-gradient(ellipse at 50% 60%,rgba(255,255,255,.03),transparent 70%)}
.sc-card-img img{width:100%;max-height:100px;object-fit:contain;filter:drop-shadow(0 4px 14px rgba(0,0,0,.7));transition:transform .4s cubic-bezier(.4,0,.2,1),filter .4s}
.sc-card:hover .sc-card-img img{transform:scale(1.04) translateY(-3px) rotate(4deg);filter:drop-shadow(0 10px 24px rgba(0,0,0,.8))}
.skin-dot{position:absolute;top:10px;right:10px;width:8px;height:8px;border-radius:50%;background:rgb(var(--team-rgb));box-shadow:0 0 10px rgb(var(--team-rgb)),0 0 20px rgba(var(--team-rgb),.4);display:none;animation:scDotPulse 2s infinite}
@keyframes scDotPulse{0%,100%{box-shadow:0 0 6px rgb(var(--team-rgb))}50%{box-shadow:0 0 16px rgb(var(--team-rgb)),0 0 30px rgba(var(--team-rgb),.5)}}
.sc-card.has-skin .skin-dot{display:block}
.sc-card-label{padding:10px 14px 14px;font-size:13px;font-weight:800;color:var(--text-2);letter-spacing:.2px}
.sc-card.has-skin .sc-card-label{color:var(--text)}
.sc-card-sub{font-size:10px;font-weight:700;display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:3px;color:rgb(var(--team-rgb))}

/* MODAL */
.sc-mbg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:1000;backdrop-filter:blur(4px);align-items:center;justify-content:center;animation:scFadeIn .4s ease-out}
.sc-mbg.open{display:flex}
@keyframes scFadeIn{from{opacity:0;backdrop-filter:blur(0)}to{opacity:1;backdrop-filter:blur(4px)}}
.sc-m{background:var(--bg-2);border:1px solid var(--border-2);border-radius:var(--radius);width:min(1300px,97vw);max-height:94vh;display:flex;flex-direction:column;overflow:hidden;animation:scUp .4s cubic-bezier(.17,.67,.83,.67)}
@keyframes scUp{from{transform:translateY(16px);opacity:0}to{transform:translateY(0);opacity:1}}
.sc-mh{display:flex;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.sc-mh-img{width:52px;height:30px;background:var(--bg-3);border-radius:5px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.sc-mh-img img{width:88%;object-fit:contain;filter:drop-shadow(0 1px 4px rgba(0,0,0,.5))}
.sc-mt{font-size:14px;font-weight:900;color:var(--text)}
.sc-ms{font-size:11px;color:var(--text-3);margin-top:1px}
.sc-mx{margin-left:auto;background:none;border:none;color:var(--text-3);cursor:pointer;font-size:17px;width:28px;height:28px;border-radius:5px;display:flex;align-items:center;justify-content:center;transition:all var(--transition)}
.sc-mx:hover{background:var(--surface);color:var(--text)}
.sc-msw{padding:9px 13px 0;flex-shrink:0;position:relative}
.sc-msi{width:100%;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:12px;font-family:'Manrope',sans-serif;padding:7px 11px 7px 30px;outline:none;transition:border-color var(--transition)}
.sc-msi:focus{border-color:var(--accent)}
.sc-msico{position:absolute;left:23px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none}
.sc-mb{display:flex;flex:1;overflow:hidden}
.sc-mg{flex:1;overflow-y:auto;padding:12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;align-content:start}
.sc-si2{background:var(--surface);border:1.5px solid var(--border);border-radius:var(--radius-sm);overflow:visible;cursor:pointer;transition:all .6s cubic-bezier(.34,.1,.68,.55);position:relative;animation:scCardIn .3s ease both}
@keyframes scCardIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
.sc-si2:hover{border-color:var(--border-2);transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.4)}
.sc-si2:hover img{transform:scale(1.08)}
.sc-si2:hover .sc-si2-inner::before{opacity:.65;transform:scaleX(1.3)}
.sc-si2.sel{border-color:var(--accent);transform:scale(.94) !important;box-shadow:0 1px 6px rgba(0,0,0,.8), inset 0 0 30px rgba(var(--rc,255,255,255),.35);transition:all .6s cubic-bezier(.34,.1,.68,.55) !important}
.sc-si2-inner{border-radius:var(--radius-sm);overflow:hidden;position:relative;background:var(--surface)}
.sc-si2 img{width:100%;aspect-ratio:16/9;object-fit:contain;background:var(--bg-3);display:block;padding:6px 4px;transition:transform .3s ease, opacity .25s ease}
.sc-si2 img.sc-img-loading{
  opacity:0;
  background:linear-gradient(90deg,var(--bg-3) 25%,rgba(255,255,255,.06) 50%,var(--bg-3) 75%);
  background-size:200% 100%;
  animation:scShimmer 1.2s infinite;
}
@keyframes scShimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sc-si2-n{padding:5px 7px;font-size:10px;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;background:var(--surface)}
/* Rarity bar at bottom of image */
.sc-si2-rarity{height:3px;width:100%;border-radius:0}
/* Rarity glow overlay */

.sc-si2-inner::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at bottom,var(--rc,transparent) -20%,transparent 60%);opacity:.12;pointer-events:none;z-index:1;transition:opacity .35s ease}
/* ── RIGHT EDITOR PANEL ── */
.sc-ed{
  width:260px;flex-shrink:0;border-left:1px solid var(--border);
  display:flex;flex-direction:column;overflow:hidden;
  background:var(--bg-2);
}
/* Preview area — tall, image floats with fade-out bottom */
.sc-ep{
  position:relative;
  height:200px;
  flex-shrink:0;
  overflow:hidden;
  background:var(--bg-3);
  display:flex;align-items:center;justify-content:center;
}
.sc-ep img{
  width:85%;
  height:85%;
  object-fit:contain;
  filter:drop-shadow(0 8px 28px rgba(0,0,0,.7));
  transition:transform .4s cubic-bezier(.4,0,.2,1), opacity .3s ease;
  animation:scEpIn .2s cubic-bezier(.34,.1,.68,.55);
}
@keyframes scEpIn{from{transform:translateY(6px) scale(.96);opacity:0}to{transform:translateY(0) scale(1);opacity:1}}
/* Glow behind image — colour set via JS */
.sc-ep-glow{
  position:absolute;inset:0;
  background:radial-gradient(ellipse at 50% 80%, var(--ep-glow,rgba(255,255,255,.08)) 0%, transparent 65%);
  pointer-events:none;
  transition:background .5s ease;
}
/* Fade out to panel bg at bottom */
.sc-ep::after{
  content:'';position:absolute;left:0;right:0;bottom:0;height:60px;
  background:linear-gradient(to bottom,transparent,var(--bg-2));
  pointer-events:none;z-index:2;
}
.sc-ep-e{color:var(--text-3);font-size:11px;text-align:center;padding:8px;z-index:3;position:relative}

/* Fields area */
.sc-ef{flex:1;overflow-y:auto;padding:12px 14px 6px;display:flex;flex-direction:column;gap:0}
.sc-f{margin-bottom:12px}
.sc-f label{display:block;font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:var(--text-3);margin-bottom:5px}
.sc-fr{display:flex;gap:5px;align-items:center}
.sc-f input[type=range]{flex:1;accent-color:var(--accent);cursor:pointer;height:4px}
/* Wear slider */
.sc-wear-slider-wrap{position:relative;margin-bottom:4px}
.sc-wear-track{
  height:14px;border-radius:7px;
  background:linear-gradient(to right,#4dd0e1 0%,#81c784 20%,#aed581 38%,#ffb74d 55%,#ef5350 100%);
  position:relative;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.3);
}
.sc-wear-thumb{
  position:absolute;top:50%;transform:translate(-50%,-50%);
  width:18px;height:18px;border-radius:50%;
  background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.5);
  border:2.5px solid rgba(0,0,0,.2);cursor:grab;transition:transform .1s;
  pointer-events:none;
}
.sc-wear-thumb:active{cursor:grabbing;transform:translate(-50%,-50%) scale(1.2)}
.sc-fv{width:52px;background:var(--bg-3);border:1px solid var(--border);border-radius:5px;color:var(--text);font-size:11px;font-family:'Manrope',sans-serif;padding:3px 5px;outline:none;text-align:center;transition:border-color var(--transition)}
.sc-fv:focus{border-color:var(--accent)}
/* StatTrak toggle */
.sc-st{display:flex;align-items:center;justify-content:space-between;background:var(--bg-3);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 10px;cursor:pointer;transition:all var(--transition);margin-bottom:10px}
.sc-st.on{border-color:#ffa726;background:rgba(255,167,38,.08)}
.sc-st span{font-size:11px;font-weight:700;color:var(--text-2)}
.sc-st.on span{color:#ffa726}
.sc-tog{width:30px;height:16px;background:var(--surface-2);border-radius:8px;position:relative;transition:background var(--transition);flex-shrink:0}
.sc-tog::after{content:'';position:absolute;width:10px;height:10px;background:#fff;border-radius:50%;top:3px;left:3px;transition:transform var(--transition)}
.sc-st.on .sc-tog{background:#ffa726}
.sc-st.on .sc-tog::after{transform:translateX(14px)}
/* Save button */
.sc-foot{padding:10px 12px;border-top:1px solid var(--border);flex-shrink:0}
.sc-sv{width:100%;padding:10px;background:var(--accent);color:#000;border:none;border-radius:var(--radius-sm);font-size:13px;font-weight:900;font-family:'Manrope',sans-serif;cursor:pointer;transition:all var(--transition)}
.sc-sv.sc-sv-login:hover{box-shadow:0 0 20px rgba(66,165,245,.35),0 4px 20px rgba(0,0,0,.5);transform:translateY(-1px)}
.sc-sv:hover{background:var(--accent-2);box-shadow:var(--accent-glow)}
.sc-sv:disabled{opacity:.4;cursor:not-allowed}
#sc-toast{position:fixed;bottom:20px;right:20px;background:var(--surface);border:1px solid var(--border-2);border-radius:var(--radius-sm);padding:10px 15px;font-size:12px;font-weight:700;color:var(--text);z-index:9999;transform:translateY(60px);opacity:0;transition:all .25s cubic-bezier(.4,0,.2,1);max-width:280px;pointer-events:none}
#sc-toast.show{transform:translateY(0);opacity:1}
#sc-toast.ok{border-color:var(--green)}
#sc-toast.err{border-color:var(--red)}
/* Loader */
.sc-loader{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 40px;gap:14px;min-height:200px}
.sc-loader-gun{font-size:52px;animation:scGB .7s ease-in-out infinite alternate;display:block;filter:drop-shadow(0 4px 12px rgba(240,196,48,.3))}
@keyframes scGB{from{transform:translateY(0) rotate(-10deg) scale(1)}to{transform:translateY(-14px) rotate(10deg) scale(1.08)}}
.sc-loader-text{font-size:12px;font-weight:800;color:var(--text-2);letter-spacing:1.5px;text-transform:uppercase}
.sc-dots{display:flex;gap:6px;margin-top:4px}
.sc-dots span{width:6px;height:6px;border-radius:50%;background:var(--accent);animation:scD .7s ease-in-out infinite}
.sc-dots span:nth-child(2){animation-delay:.14s}
.sc-dots span:nth-child(3){animation-delay:.28s}
@keyframes scD{0%,80%,100%{transform:scale(.5);opacity:.3}40%{transform:scale(1.2);opacity:1}}
.sc-ln{background:rgba(240,196,48,.08);border:1px solid rgba(240,196,48,.25);border-radius:var(--radius-sm);padding:11px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px}
</style>

<div class="page-header">
  <h1 class="page-title">Skinchanger</h1>
  <?php if ($isViewer && $viewProfile): ?>
  <p class="page-subtitle">
    Скіни гравця
    <a href="<?= profileUrl($viewId) ?>" style="color:var(--accent);font-weight:800">
      <?php if (!empty($viewProfile['avatar_url'])): ?>
        <img src="<?= htmlspecialchars($viewProfile['avatar_url']) ?>" style="width:18px;height:18px;border-radius:4px;object-fit:cover;vertical-align:middle;margin-right:4px" alt="">
      <?php endif; ?>
      <?= htmlspecialchars($viewProfile['steam_name'] ?? 'Гравець') ?>
    </a>
    — тільки перегляд
  </p>
  <?php else: ?>
  <p class="page-subtitle">Налаштуй зброю — зміни застосуються на всіх серверах після <strong>!wp</strong></p>
  <?php endif; ?>
</div>

<?php if ($isViewer): ?>
<div style="display:flex;align-items:center;gap:10px;background:rgba(240,196,48,.07);border:1px solid rgba(240,196,48,.2);border-radius:var(--radius-sm);padding:11px 16px;margin-bottom:16px;font-size:13px">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
  <span style="color:var(--text-2)">Режим перегляду — ти бачиш скіни цього гравця, але не можеш їх змінювати</span>
  <?php if ($me): ?>
  <a href="<?= skinchangerUrl($me['steam_id']) ?>" style="margin-left:auto;color:var(--accent);font-weight:700;font-size:12px;white-space:nowrap">Мої скіни →</a>
  <?php endif; ?>
</div>
<?php elseif (!$me): ?>
<div class="sc-ln">
  <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#F0C430" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  Щоб змінювати скіни — <a href="<?= SITE_URL ?>/auth/steam_login.php" style="color:var(--accent);font-weight:800;margin-left:4px">увійди через Steam</a>
</div>
<?php endif; ?>

<div class="sc">
  <div class="sc-topbar">
    <div class="sc-team-selector">
      <button class="sc-team-btn ct-btn active" id="scBtnCT" onclick="scSetTeam(3)">
        <img src="<?= SITE_URL ?>/assets/ct-patch.webp" alt="CT">
      </button>
      <button class="sc-team-btn t-btn" id="scBtnT" onclick="scSetTeam(2)">
        <img src="<?= SITE_URL ?>/assets/t-patch.webp" alt="T">
      </button>
    </div>
    <?php if (!$isViewer): ?>
    <button class="sc-dice-btn" onclick="scShowRandom()" title="Рандомні скіни для вибраної команди">
      <img src="<?= SITE_URL ?>/assets/dice.png" alt="🎲">
      Рандом
    </button>
    <button class="sc-rbtn" onclick="scReset()">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
      Скинути команду
    </button>
    <?php endif; ?>
  </div>

  <div class="sc-sw">
    <svg class="sc-sico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" class="sc-si" id="scSearch" placeholder="Пошук зброї..." oninput="scFilter(this.value)">
  </div>

<?php
$CT_ONLY_DEF = [32,34,3,38,8,10,16,27,60,61];
$T_ONLY_DEF  = [4,39,7,11,13,17,29,30];

foreach ($CATS as $catKey => [$icon,$label,$items]):
?>
<div class="sc-cat" data-cat="<?= $catKey ?>">
  <div class="sc-cat-title"><?= $label ?></div>
  <div class="sc-grid">
  <?php foreach ($items as $wkey => [$wname,$defindex]):
    $isCT = in_array($defindex, $CT_ONLY_DEF);
    $isT  = in_array($defindex, $T_ONLY_DEF);
    $defaultImg = ($catKey === 'gloves' && $defindex == 0) ? '' :
                  ($catKey === 'gloves' ? ($GLOVE_PREVIEW[$defindex] ?? '') : $IMG . $wkey . '.png');

    // For CT (team=3) and T (team=2): find saved skin image
    $skinImgCT = $defaultImg;
    $skinImgT  = $defaultImg;
    $hasSkinCT = false;
    $hasSkinT  = false;
    $skinNameCT = '+ скін';
    $skinNameT  = '+ скін';

    if ($catKey === 'knives') {
      $hasSkinCT = isset($userKnife[3]) && $userKnife[3] === $wkey;
      $hasSkinT  = isset($userKnife[2]) && $userKnife[2] === $wkey;
      // For knife skin image, look in userSkins by defindex
      foreach ([3 => &$skinImgCT, 2 => &$skinImgT] as $t => &$sImg) {
        $sk = $defindex . '_' . $t;
        if (isset($userSkins[$sk]) && $userSkins[$sk]['weapon_paint_id'] > 0) {
          $sImg = $IMG . $wkey . '-' . $userSkins[$sk]['weapon_paint_id'] . '.png';
        }
      }
      if ($hasSkinCT) $skinNameCT = '✓ ' . str_replace('weapon_knife_','',str_replace('weapon_','',$wkey));
      if ($hasSkinT)  $skinNameT  = '✓ ' . str_replace('weapon_knife_','',str_replace('weapon_','',$wkey));
    } elseif ($catKey === 'gloves') {
      $hasSkinCT = isset($userGloves[3]) && $userGloves[3] == $defindex && $defindex > 0;
      $hasSkinT  = isset($userGloves[2]) && $userGloves[2] == $defindex && $defindex > 0;
      foreach ([3 => &$skinImgCT, 2 => &$skinImgT] as $t => &$sImg) {
        $sk = $defindex . '_' . $t;
        if (isset($userSkins[$sk]) && $userSkins[$sk]['weapon_paint_id'] > 0) {
          $gloveName = ['4725'=>'leather_handwraps','5027'=>'studded_brawler_gloves','5030'=>'sporty_gloves','5031'=>'motorcycle_gloves','5032'=>'leather_handwraps','5033'=>'motorcycle_gloves','5034'=>'specialist_gloves','5035'=>'hydra_gloves'];
          $gn = $gloveName[$defindex] ?? 'sporty_gloves';
          $sImg = $IMG . $gn . '-' . $userSkins[$sk]['weapon_paint_id'] . '.png';
        }
      }
      if ($hasSkinCT) $skinNameCT = '✓ встановлено';
      if ($hasSkinT)  $skinNameT  = '✓ встановлено';
    } else {
      foreach ([3 => [&$hasSkinCT, &$skinImgCT, &$skinNameCT], 2 => [&$hasSkinT, &$skinImgT, &$skinNameT]] as $t => &$ref) {
        $sk = $defindex . '_' . $t;
        if (isset($userSkins[$sk])) {
          $ref[0] = true;
          $paintId = $userSkins[$sk]['weapon_paint_id'];
          if ($paintId > 0) {
            $ref[1] = $IMG . $wkey . '-' . $paintId . '.png';
            $ref[2] = $userSkins[$sk]['_paint_name'] ?: '✓ скін';
          } else {
            $ref[2] = 'Default';
          }
        }
      }
    }
  ?>
    <?php if ($isViewer && !$hasSkinCT && !$hasSkinT) continue; ?>
    <div class="sc-card <?= $hasSkinCT ? 'has-skin' : '' ?>"
         id="scCard_<?= $wkey ?>"
         data-wkey="<?= $wkey ?>"
         data-defindex="<?= $defindex ?>"
         data-wname="<?= htmlspecialchars($wname) ?>"
         data-cat="<?= $catKey ?>"
         data-img="<?= htmlspecialchars($defaultImg) ?>"
         data-img-ct="<?= htmlspecialchars($skinImgCT) ?>"
         data-img-t="<?= htmlspecialchars($skinImgT) ?>"
         data-ct="<?= $isCT?'1':'0' ?>"
         data-t="<?= $isT?'1':'0' ?>"
         <?= $isViewer ? '' : 'onclick="scOpen(this)"' ?>
         style="<?= $isViewer ? 'cursor:default;' : '' ?>">
      <div class="sc-card-img">
        <?php if($defaultImg): ?>
        <img id="scImg_<?= $wkey ?>" src="<?= htmlspecialchars($skinImgCT) ?>" alt="<?= htmlspecialchars($wname) ?>" loading="lazy" onerror="this.src='<?= htmlspecialchars($defaultImg) ?>'">
        <?php else: ?>
        <span style="font-size:22px"><?= $icon ?></span>
        <?php endif; ?>
        <span class="skin-dot"></span>
      </div>
      <div class="sc-card-label">
        <?= htmlspecialchars($wname) ?>
        <span class="sc-card-sub" id="scSub_<?= $wkey ?>"><?= $hasSkinCT ? htmlspecialchars($skinNameCT) : '+ скін' ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Music & Pins -->
<div class="sc-cat" data-cat="extras">
  <div class="sc-cat-title">Музика & Піни</div>
  <div class="sc-grid">
    <?php if (!$isViewer || !empty($userMusic)): ?>
    <div class="sc-card" id="scCard_music" data-wkey="music" data-defindex="0" data-wname="Музика" data-cat="music" data-img="<?= $MUSIC_PREVIEW ?>" data-img-ct="<?= $MUSIC_PREVIEW ?>" data-img-t="<?= $MUSIC_PREVIEW ?>" data-ct="0" data-t="0" onclick="scOpen(this)">
      <div class="sc-card-img">
        <img id="scImg_music" src="<?= $MUSIC_PREVIEW ?>" alt="Музика" loading="lazy" onerror="this.style.opacity='.2'">
        <span class="skin-dot"></span>
      </div>
      <div class="sc-card-label">Музика<span class="sc-card-sub" id="scSub_music">+ вибрати</span></div>
    </div>
    <?php endif; ?>
    <?php if (!$isViewer || !empty($userPins)): ?>
    <div class="sc-card" id="scCard_pin" data-wkey="pin" data-defindex="0" data-wname="Пін" data-cat="pins" data-img="<?= $PIN_PREVIEW ?>" data-img-ct="<?= $PIN_PREVIEW ?>" data-img-t="<?= $PIN_PREVIEW ?>" data-ct="0" data-t="0" onclick="scOpen(this)">
      <div class="sc-card-img">
        <img id="scImg_pin" src="<?= $PIN_PREVIEW ?>" alt="Пін" loading="lazy" onerror="this.style.opacity='.2'">
        <span class="skin-dot"></span>
      </div>
      <div class="sc-card-label">Пін<span class="sc-card-sub" id="scSub_pin">+ вибрати</span></div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- MODAL -->
<div class="sc-mbg" id="scMBg" onclick="if(event.target===this)scClose()">
  <div class="sc-m">
    <div class="sc-mh">
      <div class="sc-mh-img" id="scMHImg"><span style="font-size:16px">🔫</span></div>
      <div>
        <div class="sc-mt" id="scMTitle">Зброя</div>
        <div class="sc-ms" id="scMSub">Обери скін</div>
      </div>
      <button class="sc-mx" onclick="scClose()">✕</button>
    </div>
    <div class="sc-msw">
      <svg class="sc-msico" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input type="text" class="sc-msi" id="scMSearch" placeholder="Пошук скіна..." oninput="scMFilter(this.value)">
    </div>
    <div class="sc-mb">
      <div class="sc-mg" id="scMGrid">
        <div style="color:var(--text-3);padding:28px;text-align:center;grid-column:1/-1">⏳</div>
      </div>
      <div class="sc-ed">
        <div class="sc-ep" id="scEP"><div class="sc-ep-glow" id="scEPGlow"></div><div class="sc-ep-e">Обери скін</div></div>
        <div class="sc-ef" id="scEF">
          
        </div>
        <div class="sc-foot">
          <?php if ($me && $isOwn): ?>
          <button class="sc-sv" id="scSvBtn" onclick="scSave()">
            💾 Зберегти
          </button>
          <?php elseif ($isViewer): ?>
          <div style="text-align:center;padding:10px;font-size:12px;color:var(--text-3)">
            👁 Режим перегляду
          </div>
          <?php else: ?>
          <button class="sc-sv sc-sv-login" onclick="openLoginModal()" style="background:linear-gradient(135deg,#1b2838,#2a475e);color:#fff;border:1px solid rgba(66,165,245,.4);display:flex;align-items:center;justify-content:center;gap:10px">
            <img src="<?= SITE_URL ?>/assets/steam-logo.png" alt="Steam" style="width:18px;height:18px;object-fit:contain;filter:invert(1);opacity:.9">
            Авторизація
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<div id="sc-toast"></div>

<!-- RANDOM MODAL -->
<div class="sc-rand-bg" id="scRandBg" onclick="if(event.target===this)scCloseRandom()">
  <div class="sc-rand-m">
    <div class="sc-rand-icon">
      <img src="<?= SITE_URL ?>/assets/dice.png" alt="🎲">
    </div>
    <div class="sc-rand-title">🎲 Рандом всіх скінів</div>
    <div class="sc-rand-sub">Всі скіни для <strong style="color:#42a5f5">CT</strong> і <strong style="color:#c8a85a">T</strong> команд будуть випадково вибрані — зброя, ніж, рукавиці, піни та музика.</div>
    <div class="sc-rand-warn">⚠️ Поточні скіни обох команд будуть замінені. Ти завжди можеш змінити будь-який скін вручну або скинути команду.</div>
    <div class="sc-rand-btns">
      <button class="sc-rand-cancel" id="scRandCancel" onclick="scCloseRandom()">Скасувати</button>
      <button class="sc-rand-confirm" id="scRandConfirm" onclick="scDoRandomize()">Так, рандомити! 🎲</button>
    </div>
    <div class="sc-rand-progress" id="scRandProgress">
      <div class="sc-rand-progress-bar"><div class="sc-rand-progress-fill" id="scRandFill"></div></div>
      <div class="sc-rand-progress-txt" id="scRandTxt">Підбираємо скіни...</div>
    </div>
  </div>
</div>

<script>
const SC_IN   = <?= ($me && $isOwn) ? 'true' : 'false' ?>;
const SC_VIEW = <?= $isViewer ? 'true' : 'false' ?>; // переглядаємо чужий
const SC_SID  = <?= $me ? "'".$me['steam_id']."'" : 'null' ?>;
const _dSkins = <?= json_encode((object)$userSkins) ?>;
<?php
$rarityMapFile = __DIR__ . '/data/rarity_map.json';
$rarityMapData = file_exists($rarityMapFile) ? file_get_contents($rarityMapFile) : '{}';
?>
const RARITY_MAP = <?= $rarityMapData ?>;
// Helper: get rarity color for a skin
function scRarityColor(paint, weaponName) {
  paint = String(paint||0);
  const byWeapon = RARITY_MAP.by_weapon;
  const byPaint  = RARITY_MAP.by_paint;
  if(byWeapon && weaponName) {
    const key = weaponName + ':' + paint;
    if(byWeapon[key]) return byWeapon[key];
  }
  return (byPaint && byPaint[paint]) || '#4b69ff';
}
// Helper: get rarity color
function rc(paint) {
  const r = RARITY_MAP[String(paint)];
  return r ? (r.c || r) : '#4b69ff';
}
// Helper: get rarity order for sorting
function ro(paint) {
  const r = RARITY_MAP[String(paint)];
  return r ? (r.o || 3) : 3;
}
const _dKnife = <?= json_encode($userKnife) ?>;
const _dGlove = <?= json_encode($userGloves) ?>;
const _dMusic = <?= json_encode($userMusic) ?>;
const _dPins  = <?= json_encode($userPins)  ?>;
const _dAgent = <?= json_encode((object)($userAgent ?: [])) ?>;
const _musicCache = {};
const _pinsCache  = {};
const _agentCache = {};
// Pre-populate music/pins caches if user has selections
(async () => {
  if(Object.values(_dMusic).some(v=>v)) {
    const r = await fetch('/api/skins.php?type=music').catch(()=>null);
    if(r) { const d=await r.json().catch(()=>[]); d.forEach(m=>{ _musicCache[m.id]=m; }); scUpdateCards(); }
  }
  if(Object.values(_dPins).some(v=>v)) {
    const r = await fetch('/api/skins.php?type=pins').catch(()=>null);
    if(r) { const d=await r.json().catch(()=>[]); d.forEach(p=>{ _pinsCache[p.id]=p; }); scUpdateCards(); }
  }
})();

// Mutable state
const dS = Object.assign({}, _dSkins);
const dK = Object.assign({}, _dKnife);
const dG = Object.assign({}, _dGlove);
const dM = Object.assign({}, _dMusic);
const dP = Object.assign({}, _dPins);
const dA = Object.assign({}, _dAgent);

const CT_ONLY = [32,34,3,38,8,10,16,27,60,61];
const T_ONLY  = [4,39,7,11,13,17,29,30];

let team = 3;
let M = {wkey:null,defindex:null,wname:null,cat:null,all:[],sel:null};
let ES = {wear:0.01,seed:0,st:false};
let rendered = [];
const cache = {};

// ── TEAM ──
function scSetTeam(t) {
  team = t;
  const btnCT = document.getElementById('scBtnCT');
  const btnT  = document.getElementById('scBtnT');

  if (t === 3) {
    btnCT.classList.add('active');
    btnT.classList.remove('active');
  } else {
    btnT.classList.add('active');
    btnCT.classList.remove('active');
  }

  const rgb  = t === 3 ? '66,165,245' : '200,168,90';
  const cats = document.querySelectorAll('.sc-cat');

  // 1. Fade out
  cats.forEach(cat => {
    cat.style.transition = 'opacity .2s ease, transform .2s ease';
    cat.style.opacity    = '0';
    cat.style.transform  = 'translateY(6px)';
  });

  // 2. Collect all image URLs for the new team
  const newSrcs = [];
  document.querySelectorAll('.sc-card').forEach(card => {
    const teamImg = t === 3
      ? (card.dataset.imgCt || card.dataset.img)
      : (card.dataset.imgT  || card.dataset.img);
    if (teamImg) newSrcs.push(teamImg);
  });

  // 3. Start loading all images in parallel
  const imgPromises = newSrcs.map(src => {
    const img = new Image();
    return new Promise(resolve => {
      img.onload  = resolve;
      img.onerror = resolve; // don't block if image fails
      img.src = src;
    });
  });

  // 4. Wait for all images (or 3s timeout), plus minimum 200ms for fade-out
  const timeoutGuard = new Promise(r => setTimeout(r, 3000));
  Promise.all([
    Promise.race([Promise.all(imgPromises), timeoutGuard]),
    new Promise(r => setTimeout(r, 200))
  ]).then(() => {
    // Apply changes — images are now cached, src swap is instant
    document.querySelector('.sc').style.setProperty('--team-rgb', rgb);
    document.querySelectorAll('.sc-card').forEach(card => {
      const wkey    = card.dataset.wkey;
      const img     = document.getElementById('scImg_' + wkey);
      if (!img) return;
      const teamImg = t === 3
        ? (card.dataset.imgCt || card.dataset.img)
        : (card.dataset.imgT  || card.dataset.img);
      if (teamImg) img.src = teamImg;
    });
    scUpdateCards();

    // Staggered fade in per category
    cats.forEach((cat, i) => {
      setTimeout(() => {
        cat.style.opacity   = '1';
        cat.style.transform = 'translateY(0)';
      }, i * 40);
    });
  });
}

// ── UPDATE CARDS ──
function scUpdateCards() {
  document.querySelectorAll('.sc-card').forEach(c => {
    const ct = c.dataset.ct==='1', t = c.dataset.t==='1';
    c.classList.toggle('locked', (ct&&team===2)||(t&&team===3));
    const wkey = c.dataset.wkey, cat = c.dataset.cat;
    const def  = parseInt(c.dataset.defindex);
    const sub  = document.getElementById('scSub_'+wkey);
    const img  = document.getElementById('scImg_'+wkey);
    let has=false, lbl='+ скін', rarityColor=null;

    if(cat==='knives') {
      has = dK[team] === wkey;
      if(has) {
        const k=def+'_'+team, db=dS[k];
        if(db && db.weapon_paint_id>0) {
          lbl = scSN(db._paint_name || scKnifeName(wkey));
          rarityColor = scRarityColor(String(db.weapon_paint_id), wkey);
          img && (img.src = IMG_BASE + wkey + '-' + db.weapon_paint_id + '.png');
        } else {
          lbl = scKnifeName(wkey);
        }
      } else {
        lbl = '+ ніж';
        img && (img.src = c.dataset.img);
      }
    } else if(cat==='gloves') {
      has = !!dG[team] && dG[team]==def && def>0;
      if(has) {
        const k=def+'_'+team, db=dS[k];
        if(db && db.weapon_paint_id>0) {
          lbl = db._paint_name || '✓ встановлено';
          rarityColor = scRarityColor(String(db.weapon_paint_id), wkey);
          const gn = gloveImgName(def);
          img && (img.src = IMG_BASE + gn + '-' + db.weapon_paint_id + '.png');
        } else { lbl = '✓ встановлено'; }
      } else {
        lbl = '+ рукавиці';
        img && (img.src = c.dataset.img);
      }
    } else if(cat==='agents') {
      const agentTeam = wkey==='agent_ct' ? 3 : 2;
      // Agents card is always visible (no CT/T locking)
      c.classList.remove('locked');
      const model = dA[agentTeam];
      has = !!(model && model!=='null');
      if(has) {
        const entry = _agentCache[model];
        lbl = entry ? entry.name.split('|').pop().trim() : '✓ встановлено';
        if(entry?.image && img) img.src = entry.image;
      } else {
        lbl = '+ вибрати';
        img && (img.src = c.dataset.img);
      }
    } else if(cat==='music') {
      const mid = dM[team];
      has = !!mid;
      if(has) {
        const entry = _musicCache[mid];
        lbl = entry ? scSN(entry.name||'') : '✓ встановлено';
        if(entry?.image && img) img.src = entry.image;
      } else {
        lbl = '+ вибрати';
        img && (img.src = c.dataset.img);
      }
    } else if(cat==='pins') {
      const pid = dP[team];
      has = !!pid;
      if(has) {
        const entry = _pinsCache[pid];
        lbl = entry ? scSN(entry.name||'') : '✓ встановлено';
        if(entry?.image && img) img.src = entry.image;
      } else {
        lbl = '+ вибрати';
        img && (img.src = c.dataset.img);
      }
    } else {
      const k=def+'_'+team, db=dS[k];
      has=!!db;
      if(has && db.weapon_paint_id>0) {
        lbl = db._paint_name || '✓ скін';
        rarityColor = scRarityColor(String(db.weapon_paint_id), wkey);
        img && (img.src = IMG_BASE + wkey + '-' + db.weapon_paint_id + '.png');
      } else if(has) {
        lbl = 'Default';
        img && (img.src = c.dataset.img);
      } else {
        lbl = '+ скін';
        img && (img.src = c.dataset.img);
      }
    }

    c.classList.toggle('has-skin', has);
    if(sub) {
      sub.textContent = lbl;
      sub.style.color = rarityColor || (has ? 'var(--accent)' : '');
    }
  });
}

const IMG_BASE = 'https://raw.githubusercontent.com/Nereziel/cs2-WeaponPaints/main/website/img/skins/';

function gloveImgName(def) {
  const m = {4725:'leather_handwraps',5027:'studded_brawler_gloves',5030:'sporty_gloves',
    5031:'motorcycle_gloves',5032:'leather_handwraps',5033:'motorcycle_gloves',
    5034:'specialist_gloves',5035:'hydra_gloves'};
  return m[def] || 'sporty_gloves';
}

function scKnifeName(wkey) {
  if(!wkey) return '?';
  return wkey.replace('weapon_knife_','').replace('weapon_','').replace(/_/g,' ');
}

// ── SEARCH ──
function scFilter(q) {
  q = q.toLowerCase();
  document.querySelectorAll('.sc-card').forEach(c => {
    c.style.display = (!q||c.dataset.wname.toLowerCase().includes(q))?'':'none';
  });
  document.querySelectorAll('.sc-cat').forEach(cat => {
    cat.style.display = [...cat.querySelectorAll('.sc-card')].some(c=>c.style.display!=='none')?'':'none';
  });
}

// ── OPEN MODAL ──
async function scOpen(btn) {
  M = {wkey:btn.dataset.wkey, defindex:parseInt(btn.dataset.defindex),
       wname:btn.dataset.wname, cat:btn.dataset.cat, all:[], sel:null};
  ES = {wear:0.01, seed:0, st:false};

  document.getElementById('scMTitle').textContent = M.wname;
  document.getElementById('scMSub').textContent   = (team===3?'CT':'T')+' · Обери';
  document.getElementById('scMSearch').value      = '';
  document.getElementById('scMBg').classList.add('open');
  document.body.style.overflow = 'hidden';

  const hi = document.getElementById('scMHImg');
  const imgSrc = btn.dataset.img;
  hi.innerHTML = imgSrc
    ? `<img src="${esc(imgSrc)}">`
    : `<span style="font-size:16px">${btn.querySelector('.sc-card-img span')?.textContent||'🔫'}</span>`;

  const loaderEmoji = M.cat==='knives'?'🗡️':M.cat==='gloves'?'🧤':M.cat==='music'?'🎵':M.cat==='pins'?'📌':M.cat==='agents'?'🧑':'🔫';
  const loaderTexts = {
    'knives':'Точимо ножі...','gloves':'Надягаємо рукавички...',
    'music':'Підбираємо трек...','pins':'Шукаємо колекцію...',
  };
  const loaderText = loaderTexts[M.cat] || 'Завантаження скінів...';

  document.getElementById('scMGrid').innerHTML =
    `<div class="sc-loader">
      <span class="sc-loader-gun">${loaderEmoji}</span>
      <div class="sc-loader-text">${loaderText}</div>
      <div class="sc-dots"><span></span><span></span><span></span></div>
    </div>`;
  scResetEd();

  // Load with timeout protection
  const loadPromise = scLoad(M.wkey, M.defindex, M.cat).catch(()=>[]);
  const timeoutPromise = new Promise(r => setTimeout(()=>r([]), 8000)); // 8s timeout
  const delayPromise = new Promise(r => setTimeout(r, 600)); // min 600ms for loader

  M.all = await Promise.race([loadPromise, timeoutPromise]);
  await delayPromise; // ensure loader showed for at least 600ms

  scPreselect();
  scRender(M.all);
}

function scClose() {
  document.getElementById('scMBg').classList.remove('open');
  document.body.style.overflow = '';
}

// ── LOAD ──
async function scLoad(wkey, defindex, cat) {
  const k = cat+'_'+wkey+'_'+defindex;
  if(cache[k]) return cache[k];
  try {
    let url = cat==='gloves'  ? `/api/skins.php?type=gloves&defindex=${defindex}`
            : cat==='music'   ? `/api/skins.php?type=music`
            : cat==='pins'    ? `/api/skins.php?type=pins`
            : cat==='agents'  ? `/api/skins.php?type=agents&team=${wkey==='agent_ct'?3:2}`
            : `/api/skins.php?weapon=${wkey}&defindex=${defindex}`;
    const r = await fetch(url);
    const data = await r.json();
    // Populate agent cache
    if(cat==='agents') data.forEach(a => { if(a.model) _agentCache[a.model] = a; });
    cache[k] = data;
    return cache[k];
  } catch(e){ return []; }
}

// ── RENDER ──
const RARITY_PRIORITY = {'#e4ae39':7,'#eb4b4b':6,'#d32ce6':5,'#8847ff':4,'#4b69ff':3,'#5e98d9':2,'#b0c3d9':1};

function scRender(items) {
  rendered = items;
  const g = document.getElementById('scMGrid');
  if(!items.length){ g.innerHTML='<div style="color:var(--text-3);padding:28px;text-align:center;grid-column:1/-1">Нічого</div>'; return; }

  // Agents: no rarity, different card style
  if(M.cat==='agents') {
    g.innerHTML = items.map((s,i) => {
      const isSel = M.sel && M.sel._i===i;
      const name  = s.name||'';
      const img   = s.img||s.image||'';
      const isDefault = s.model==='null'||s.model==='';
      return `<div class="sc-si2 ${isSel?'sel':''}" data-i="${i}" style="animation-delay:${Math.min(i*15,400)}ms" onclick="scPick(${i})">
        <div class="sc-si2-inner" style="overflow:hidden">
          ${img
            ? `<img src="${esc(img)}" class="sc-img-loading" style="aspect-ratio:1/1;object-fit:cover;object-position:top center;width:100%" onload="this.classList.remove('sc-img-loading')" onerror="this.classList.remove('sc-img-loading');this.style.opacity='.15'">`
            : `<div style="aspect-ratio:1/1;background:var(--bg-3);display:flex;align-items:center;justify-content:center;font-size:32px">${isDefault?'👤':'🧑'}</div>`
          }
        </div>
        <div class="sc-si2-n" style="font-size:9px;line-height:1.3;padding:5px 6px;min-height:30px">${esc(name)}</div>
      </div>`;
    }).join('');
    return;
  }

  // Sort by rarity descending, Default first
  const sorted = [...items].sort((a,b) => {
    const pa = String(a.paint||a.id||0), pb = String(b.paint||b.id||0);
    if(pa==='0') return -1; if(pb==='0') return 1;
    const ca = scRarityColor(pa, M.wkey), cb = scRarityColor(pb, M.wkey);
    return (RARITY_PRIORITY[cb]||3) - (RARITY_PRIORITY[ca]||3);
  });
  const indexMap = new Map(sorted.map((s,i) => [s, items.indexOf(s)]));

  g.innerHTML = sorted.map((s, i) => {
    const origIdx = indexMap.get(s);
    const isSel = M.sel && M.sel._i===origIdx;
    const name  = scSN(s.name||s.paint_name||'');
    const img   = s.img||s.image||'';
    const paint = String(s.paint||s.id||0);
    const rc    = scRarityColor(paint, M.wkey);
    return `<div class="sc-si2 ${isSel?'sel':''}" data-i="${origIdx}" style="--rc:${rc};animation-delay:${Math.min(i*20,500)}ms" onclick="scPick(${origIdx})">
      <div class="sc-si2-inner">
        ${img
          ? `<img src="${esc(img)}" class="sc-img-loading" onload="this.classList.remove('sc-img-loading')" onerror="this.classList.remove('sc-img-loading');this.style.opacity='.15'">`
          : `<div style="aspect-ratio:16/9;background:var(--bg-3);display:flex;align-items:center;justify-content:center;font-size:20px">🎨</div>`
        }
        <div class="sc-si2-rarity" style="background:${rc}"></div>
      </div>
      <div class="sc-si2-n">${esc(name)}</div>
    </div>`;
  }).join('');
}

function scMFilter(q) {
  q = q.toLowerCase();
  const filtered = q ? M.all.filter(s=>(s.name||s.paint_name||'').toLowerCase().includes(q)) : M.all;
  scRender(filtered);
}

function scSN(full) {
  if(!full) return 'Default';
  return full.includes('|') ? full.split('|').slice(1).join('|').trim() : full;
}
function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── PRESELECT ──
function scPreselect() {
  const cat = M.cat;
  if(cat==='music'){ const id=dM[team]; if(id){ const f=M.all.find(s=>s.id==id); if(f){M.sel={...f,_i:M.all.indexOf(f)};} } return; }
  if(cat==='pins') { const id=dP[team]; if(id){ const f=M.all.find(s=>s.id==id); if(f){M.sel={...f,_i:M.all.indexOf(f)};} } return; }
  if(cat==='agents') {
    // agent_ct uses team 3, agent_t uses team 2
    const agentTeam = M.wkey==='agent_ct' ? 3 : 2;
    const model = dA[agentTeam];
    if(model && model!=='null') {
      const f = M.all.find(s=>s.model===model);
      if(f) {
        M.sel={...f,_i:M.all.indexOf(f)};
        const img=f.img||f.image||'';
        document.getElementById('scEP').innerHTML = img
          ? `<div class="sc-ep-glow" id="scEPGlow"></div><img src="${esc(img)}" style="width:85%;height:85%;object-fit:contain;filter:drop-shadow(0 8px 28px rgba(0,0,0,.7));animation:scEpIn .2s cubic-bezier(.34,.1,.68,.55)">`
          : '<div class="sc-ep-glow" id="scEPGlow"></div><div class="sc-ep-e">Немає зображення</div>';
        scRenderFields();
      }
    }
    return;
  }
  if(cat==='knives'){ const kn=dK[team]; if(kn&&kn===M.wkey){ /* same knife */ } }
  const key=M.defindex+'_'+team, db=dS[key];
  if(db){
    const f=M.all.find(s=>s.paint==db.weapon_paint_id);
    if(f){
      M.sel={...f,_i:M.all.indexOf(f)};
      ES={wear:parseFloat(db.weapon_wear)||0.01,seed:parseInt(db.weapon_seed)||0,st:db.weapon_stattrak==1};
      const img = f.img||f.image||'';
      const glowColor = scRarityColor(String(f.paint||0), M.wkey);
      document.getElementById('scEP').innerHTML = img
        ? `<div class="sc-ep-glow" id="scEPGlow" style="background:radial-gradient(ellipse at 50% 85%, ${glowColor}33 0%, transparent 65%)"></div><img src="${esc(img)}" style="width:85%;height:85%;object-fit:contain;filter:drop-shadow(0 8px 28px rgba(0,0,0,.7));animation:scEpIn .2s cubic-bezier(.34,.1,.68,.55)">`
        : '<div class="sc-ep-glow" id="scEPGlow"></div><div class="sc-ep-e">Немає зображення</div>';
      scRenderFields();
    }
  }
}

// ── PICK ──
function scPick(i) {
  const s = rendered[i];
  if(!s) return;
  M.sel = {...s, _i:i};
  document.querySelectorAll('.sc-si2').forEach(el=>el.classList.toggle('sel',parseInt(el.dataset.i)===i));
  const img = s.img||s.image||'';
  const paint = String(s.paint||s.id||0);
  const glowColor = scRarityColor(paint, M.wkey);
  const glow = document.getElementById('scEPGlow');
  if(glow) glow.style.background = `radial-gradient(ellipse at 50% 85%, ${glowColor}33 0%, transparent 65%)`;
  document.getElementById('scEP').innerHTML = img
    ? `<div class="sc-ep-glow" id="scEPGlow" style="background:radial-gradient(ellipse at 50% 85%, ${glowColor}33 0%, transparent 65%)"></div><img src="${esc(img)}" style="width:85%;height:85%;object-fit:contain;filter:drop-shadow(0 8px 28px rgba(0,0,0,.7));animation:scEpIn .2s cubic-bezier(.34,.1,.68,.55)">`
    : `<div class="sc-ep-glow" id="scEPGlow"></div><div class="sc-ep-e">Немає зображення</div>`;
  scRenderFields();

  // Auto-save for music, pins, agents (no editor needed)
  if(M.cat==='music' || M.cat==='pins' || M.cat==='agents') {
    scSave();
  }
}

// ── EDITOR FIELDS ──
function scRenderFields() {
  const simple = M.cat==='music'||M.cat==='pins'||M.cat==='agents';
  const noST   = simple || M.cat==='gloves'; // gloves have no stattrak
  const wp = ES.wear * 100;
  const wc = scWearCat(ES.wear);

  document.getElementById('scEF').innerHTML = !simple ? `
    <div class="sc-f">
      <label>Знос — <span style="color:${wc.c};font-weight:900">${wc.l}</span>
        <span style="color:var(--text-3);font-size:9px;margin-left:4px">${ES.wear.toFixed(4)}</span>
      </label>
      <div class="sc-wear-slider-wrap" id="scWearWrap">
        <div class="sc-wear-track" id="scWearTrack">
          <div class="sc-wear-thumb" id="scWearThumb" style="left:${wp}%"></div>
        </div>
      </div>
      <div style="display:flex;gap:6px;align-items:center;margin-top:6px">
        <input type="number" class="sc-fv" id="scWV" value="${ES.wear.toFixed(4)}" min="0.0001" max="1" step="0.0001" style="width:100px" onchange="scWT(this.value)">
        <span style="font-size:10px;color:var(--text-3)">float (0–1)</span>
      </div>
    </div>
    <div class="sc-f"><label>Патерн (Seed)</label>
      <div class="sc-fr"><input type="range" min="0" max="999" value="${ES.seed}" oninput="scSD(this.value)">
      <input type="text" class="sc-fv" id="scSV" value="${ES.seed}" onchange="scSDT(this.value)"></div>
    </div>
    <div class="sc-st ${ES.st?'on':''}" id="scST" onclick="scTST()" ${noST?'style="display:none"':''}>
      <span>StatTrak™</span><div class="sc-tog"></div>
    </div>`
  : '<div style="color:var(--text-3);font-size:11px;padding:10px;text-align:center">Просто обери і збережи</div>';

  // Attach drag events after render
  requestAnimationFrame(scInitWearDrag);
}

function scWearCat(w) {
  if(w<=0.07) return {l:'FN',i:0,c:'#4dd0e1'};
  if(w<=0.15) return {l:'MW',i:1,c:'#81c784'};
  if(w<=0.37) return {l:'FT',i:2,c:'#aed581'};
  if(w<=0.44) return {l:'WW',i:3,c:'#ffb74d'};
  return {l:'BS',i:4,c:'#ef5350'};
}

function scInitWearDrag() {
  const track = document.getElementById('scWearTrack');
  if(!track) return;
  function update(e) {
    const rect = track.getBoundingClientRect();
    const x = (e.touches ? e.touches[0].clientX : e.clientX) - rect.left;
    const pct = Math.max(0, Math.min(1, x / rect.width));
    ES.wear = Math.max(0.0001, Math.min(1, pct));
    const thumb = document.getElementById('scWearThumb');
    if(thumb) thumb.style.left = (pct*100)+'%';
    const wv = document.getElementById('scWV');
    if(wv) wv.value = ES.wear.toFixed(4);
    // Update label
    const wc = scWearCat(ES.wear);
    const lbl = track.closest('.sc-f').querySelector('label');
    if(lbl) lbl.innerHTML = `Знос — <span style="color:${wc.c};font-weight:900">${wc.l}</span><span style="color:var(--text-3);font-size:9px;margin-left:4px">${ES.wear.toFixed(4)}</span>`;
    track.closest('.sc-wear-slider-wrap').querySelectorAll('.sc-wear-label').forEach((el,i)=>el.classList.toggle('active',i===wc.i));
  }
  function stop() { document.removeEventListener('mousemove',update); document.removeEventListener('mouseup',stop); document.removeEventListener('touchmove',update); document.removeEventListener('touchend',stop); }
  track.addEventListener('mousedown', e=>{ update(e); document.addEventListener('mousemove',update); document.addEventListener('mouseup',stop); });
  track.addEventListener('touchstart', e=>{ update(e); document.addEventListener('touchmove',update); document.addEventListener('touchend',stop); }, {passive:true});
}

function scResetEd() {
  document.getElementById('scEF').innerHTML='';
  document.getElementById('scEP').innerHTML='<div class="sc-ep-e">Обери скін</div>';
}

function scW(v)  { ES.wear=v/100; const e=document.getElementById('scWV'); if(e)e.value=ES.wear.toFixed(4); }
function scWT(v) { let n=parseFloat(v); if(isNaN(n))n=0.01; ES.wear=Math.max(0.0001,Math.min(1,n)); }
function scSD(v) { ES.seed=parseInt(v); const e=document.getElementById('scSV'); if(e)e.value=ES.seed; }
function scSDT(v){ let n=parseInt(v); if(isNaN(n))n=0; ES.seed=Math.max(0,Math.min(999,n)); }
function scTST() { ES.st=!ES.st; const e=document.getElementById('scST'); if(e)e.classList.toggle('on',ES.st); }

// ── SAVE ──
async function scSave() {
  if(!SC_IN || SC_VIEW || !M.sel) return;
  const btn = document.getElementById('scSvBtn');
  if(btn){ btn.disabled=true; btn.textContent='⏳...'; }
  try {
    const r = await fetch('/api/save_skin.php',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body:JSON.stringify({
        weapon_defindex: M.defindex,
        weapon_name:     M.wkey,
        weapon_paint_id: M.sel.paint ?? 0,
        weapon_wear:     ES.wear,
        weapon_seed:     ES.seed,
        weapon_stattrak: ES.st?1:0,
        weapon_team:     M.cat==='agents' ? (M.wkey==='agent_ct'?3:2) : team,
        cat:             M.cat,
        music_id:        parseInt(M.sel.id ?? 0),
        pin_id:          parseInt(M.sel.id ?? 0),
        agent_model:     M.sel.model ?? '',
      })
    });
    if(!r.ok){ scToast('❌ HTTP '+r.status,'err'); return; }
    const text = await r.text();
    let d; try{ d=JSON.parse(text); }catch(e){ scToast('❌ Помилка сервера','err'); console.error(text); return; }
    if(d.success) {
      const skinName = scSN(M.sel.name||M.sel.paint_name||'');
      const paintId  = M.sel.paint ?? 0;
      if(M.cat==='knives') {
        dK[team] = M.wkey;
        dS[M.defindex+'_'+team] = {weapon_defindex:M.defindex,weapon_paint_id:paintId,weapon_wear:ES.wear,weapon_seed:ES.seed,weapon_stattrak:ES.st?1:0,weapon_team:team,_paint_name:skinName};
        scSetCardImg(M.wkey, M.sel.img||M.sel.image);
      } else if(M.cat==='gloves') {
        dG[team] = M.defindex;
        if(paintId>0) dS[M.defindex+'_'+team] = {weapon_defindex:M.defindex,weapon_paint_id:paintId,weapon_wear:ES.wear,weapon_seed:ES.seed,weapon_stattrak:0,weapon_team:team,_paint_name:skinName};
        scSetCardImg(M.wkey, M.sel.img||M.sel.image);
      } else if(M.cat==='music') {
        dM[team] = M.sel.id;
        _musicCache[M.sel.id] = M.sel;
      } else if(M.cat==='pins') {
        dP[team] = M.sel.id;
        _pinsCache[M.sel.id] = M.sel;
      } else if(M.cat==='agents') {
        const agentTeam = M.wkey==='agent_ct' ? 3 : 2;
        dA[agentTeam] = M.sel.model;
        _agentCache[M.sel.model] = M.sel;
        scSetCardImg(M.wkey, M.sel.img||M.sel.image);
      } else {
        dS[M.defindex+'_'+team]={weapon_defindex:M.defindex,weapon_paint_id:paintId,weapon_wear:ES.wear,weapon_seed:ES.seed,weapon_stattrak:ES.st?1:0,weapon_team:team,_paint_name:skinName};
        scSetCardImg(M.wkey, M.sel.img||M.sel.image);
      }
      scUpdateCards();
      scToast('✅ Збережено! Напиши !wp на сервері.','ok');
      setTimeout(scClose, 500);
    } else {
      scToast('❌ '+(d.error||'Помилка'),'err');
    }
  } catch(e){ scToast('❌ '+e.message,'err'); console.error(e); }
  finally{ if(btn){ btn.disabled=false; btn.textContent='💾 Зберегти'; } }
}

// Update card thumbnail
function scSetCardImg(wkey, newImg) {
  if(!newImg) return;
  const img  = document.getElementById('scImg_'+wkey);
  const card = document.getElementById('scCard_'+wkey);
  if(img) img.src = newImg;
  // Store per-team image so switching team updates correctly
  if(card) {
    if(team===3) card.dataset.imgCt = newImg;
    else         card.dataset.imgT  = newImg;
  }
}

// ── RESET GLOVES TO DEFAULT ──
async function scResetGloves(card) {
  if(!SC_IN){ openLoginModal && openLoginModal(); return; }
  try {
    const r = await fetch('/api/save_skin.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ cat:'gloves', weapon_defindex:0, weapon_paint_id:0,
        weapon_team:team, weapon_name:'glove_0', weapon_wear:0.01, weapon_seed:0, weapon_stattrak:0 })
    });
    const d = await r.json().catch(()=>({}));
    if(d.success) {
      dG[team] = 0;
      // Remove skin entry for all glove defindexes on this team
      Object.keys(dS).forEach(k => { if(k.endsWith('_'+team) && parseInt(k)>4000) delete dS[k]; });
      scUpdateCards();
      scToast('✅ Дефолтні рукавиці встановлено! Напиши !wp на сервері.', 'ok');
    } else {
      scToast('❌ '+(d.error||'Помилка'),'err');
    }
  } catch(e){ scToast('❌ '+e.message,'err'); }
}
async function scReset() {
  if(!SC_IN || SC_VIEW){ scToast('🔒 Увійди через Steam','err'); return; }
  if(!confirm('Скинути всі скіни для '+(team===3?'CT':'T')+'?')) return;
  try {
    const r = await fetch('/api/save_skin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'reset',weapon_team:team})});
    const d = await r.json();
    if(d.success){
      Object.keys(dS).forEach(k=>{if(k.endsWith('_'+team))delete dS[k];});
      delete dK[team]; delete dG[team]; delete dM[team]; delete dP[team];
      // Reset card images to default
      document.querySelectorAll('.sc-card').forEach(card=>{
        const wkey=card.dataset.wkey, defImg=card.dataset.img;
        const img=document.getElementById('scImg_'+wkey);
        if(img&&defImg) img.src=defImg;
        if(team===3) card.dataset.imgCt=defImg;
        else         card.dataset.imgT=defImg;
      });
      scUpdateCards();
      scToast('✅ Скіни для '+(team===3?'CT':'T')+' скинуто','ok');
    } else { scToast('❌ '+(d.error||'Помилка'),'err'); }
  } catch(e){ scToast('❌ Помилка','err'); }
}

// ── TOAST ──
let _tt;
function scToast(msg,type='ok'){
  const t=document.getElementById('sc-toast');
  t.textContent=msg; t.className=type+' show';
  clearTimeout(_tt); _tt=setTimeout(()=>{t.className='';},3500);
}

document.addEventListener('keydown', e=>{ if(e.key==='Escape'){ scClose(); scCloseRandom(); } });

// ── RANDOM ──
function scShowRandom() {
  if(!SC_IN || SC_VIEW){ scToast('🔒 Увійди через Steam','err'); return; }
  document.getElementById('scRandBg').classList.add('open');
  document.getElementById('scRandProgress').style.display='none';
  document.getElementById('scRandFill').style.width='0%';
  document.getElementById('scRandConfirm').disabled=false;
  document.getElementById('scRandCancel').disabled=false;
  document.getElementById('scRandConfirm').textContent='Так, рандомити! 🎲';
}
function scCloseRandom() {
  document.getElementById('scRandBg').classList.remove('open');
}

async function scSaveDirect(params) {
  try {
    const r = await fetch('/api/save_skin.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(params)});
    const d = await r.json();
    return d.success;
  } catch(e){ return false; }
}

async function scDoRandomize() {
  const confirmBtn = document.getElementById('scRandConfirm');
  const cancelBtn  = document.getElementById('scRandCancel');
  const progress   = document.getElementById('scRandProgress');
  const fill       = document.getElementById('scRandFill');
  const txt        = document.getElementById('scRandTxt');

  confirmBtn.disabled = true;
  cancelBtn.disabled  = true;
  confirmBtn.textContent = '⏳ Рандомимо...';
  progress.style.display = 'block';

  const allCards    = Array.from(document.querySelectorAll('.sc-card:not(.locked)'));
  const allWeapons  = allCards.filter(c => c.dataset.cat !== 'knives' && c.dataset.cat !== 'gloves' && c.dataset.cat !== 'music' && c.dataset.cat !== 'pins' && c.dataset.cat !== 'agents');
  const knifeCards  = allCards.filter(c => c.dataset.cat === 'knives');
  const gloveCards  = allCards.filter(c => c.dataset.cat === 'gloves' && c.dataset.defindex !== '0');
  const musicCard   = allCards.find(c => c.dataset.cat === 'music');
  const pinCard     = allCards.find(c => c.dataset.cat === 'pins');

  const totalSteps  = (allWeapons.length + (knifeCards.length?1:0) + (gloveCards.length?1:0) + (musicCard?1:0) + (pinCard?1:0)) * 2;
  let done = 0;

  function setProgress(label) {
    done++;
    fill.style.width = Math.round(done/totalSteps*100)+'%';
    txt.textContent = label;
  }

  // Рандомізуємо для обох команд
  for (const teamId of [3, 2]) { // 3=CT, 2=T
    const teamName = teamId === 3 ? 'CT' : 'T';
    const isCT = teamId === 3;
    
    // Зберемо тільки зброю доступну для цієї команди
    const availableWeapons = allWeapons.filter(c => {
      if (isCT) return c.dataset.t !== '1'; // Для CT беремо все крім T-only
      else return c.dataset.ct !== '1'; // Для T беремо все крім CT-only
    });
    
    // Weapon skins
    for (const card of availableWeapons) {
      const def  = parseInt(card.dataset.defindex);
      const wkey = card.dataset.wkey;
      const skins = await scLoad(wkey, def, 'weapons').catch(()=>[]);
      const valid = skins.filter(s=>(s.paint||0)>0);
      if (valid.length) {
        const s = valid[Math.floor(Math.random()*valid.length)];
        const wear = parseFloat((Math.random()*0.89+0.01).toFixed(4));
        await scSaveDirect({weapon_defindex:def,weapon_name:wkey,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:Math.floor(Math.random()*1000),weapon_stattrak:0,weapon_team:teamId,cat:'weapons'});
        dS[def+'_'+teamId]={weapon_defindex:def,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:0,weapon_stattrak:0,weapon_team:teamId,_paint_name:s.name||''};
      }
      setProgress('🔫 '+teamName+': '+card.dataset.wname+'...');
    }

    // Random knife
    if (knifeCards.length) {
      const kCard = knifeCards[Math.floor(Math.random()*knifeCards.length)];
      const def   = parseInt(kCard.dataset.defindex);
      const wkey  = kCard.dataset.wkey;
      const skins = await scLoad(wkey, def, 'knives').catch(()=>[]);
      const valid = skins.filter(s=>(s.paint||0)>0);
      if (valid.length) {
        const s    = valid[Math.floor(Math.random()*valid.length)];
        const wear = parseFloat((Math.random()*0.49+0.01).toFixed(4));
        await scSaveDirect({weapon_defindex:def,weapon_name:wkey,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:Math.floor(Math.random()*1000),weapon_stattrak:0,weapon_team:teamId,cat:'knives'});
        dK[teamId]=wkey;
        dS[def+'_'+teamId]={weapon_defindex:def,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:0,weapon_stattrak:0,weapon_team:teamId};
      }
      setProgress('🗡️ '+teamName+': ніж...');
    }

    // Random glove
    if (gloveCards.length) {
      const gCard = gloveCards[Math.floor(Math.random()*gloveCards.length)];
      const def   = parseInt(gCard.dataset.defindex);
      const wkey  = gCard.dataset.wkey;
      const skins = await scLoad(wkey, def, 'gloves').catch(()=>[]);
      const valid = skins.filter(s=>(s.paint||0)>0);
      if (valid.length) {
        const s    = valid[Math.floor(Math.random()*valid.length)];
        const wear = parseFloat((Math.random()*0.49+0.01).toFixed(4));
        await scSaveDirect({weapon_defindex:def,weapon_name:wkey,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:Math.floor(Math.random()*1000),weapon_stattrak:0,weapon_team:teamId,cat:'gloves'});
        dG[teamId]=def;
        dS[def+'_'+teamId]={weapon_defindex:def,weapon_paint_id:s.paint,weapon_wear:wear,weapon_seed:0,weapon_stattrak:0,weapon_team:teamId};
      }
      setProgress('🧤 '+teamName+': рукавиці...');
    }

    // Random music
    if (musicCard) {
      const music = await scLoad('music',0,'music').catch(()=>[]);
      if (music.length) {
        const m = music[Math.floor(Math.random()*music.length)];
        await scSaveDirect({weapon_defindex:0,weapon_name:'music',weapon_paint_id:0,weapon_wear:0.01,weapon_seed:0,weapon_stattrak:0,weapon_team:teamId,cat:'music',music_id:parseInt(m.id||0)});
        dM[teamId]=parseInt(m.id||0);
      }
      setProgress('🎵 '+teamName+': музика...');
    }

    // Random pin
    if (pinCard) {
      const pins = await scLoad('pin',0,'pins').catch(()=>[]);
      if (pins.length) {
        const p = pins[Math.floor(Math.random()*pins.length)];
        await scSaveDirect({weapon_defindex:0,weapon_name:'pin',weapon_paint_id:0,weapon_wear:0.01,weapon_seed:0,weapon_stattrak:0,weapon_team:teamId,cat:'pins',pin_id:parseInt(p.id||0)});
        dP[teamId]=parseInt(p.id||0);
      }
      setProgress('📍 '+teamName+': піни...');
    }
  }

  fill.style.width='100%';
  txt.textContent='✅ Готово!';
  scUpdateCards();
  setTimeout(()=>{
    scCloseRandom();
    scToast('🎲 Всі скіни CT і T рандомізовані! Напиши !wp на сервері.','ok');
  }, 800);
}

// Init: set data-img-ct and data-img-t on cards from DB data
(function initCardImages() {
  document.querySelectorAll('.sc-card').forEach(card => {
    const def  = parseInt(card.dataset.defindex);
    const wkey = card.dataset.wkey;
    const cat  = card.dataset.cat;
    const defImg = card.dataset.img;

    // Build skin image URL from DB
    function skinImg(t) {
      if(cat==='knives') {
        const k = def+'_'+t;
        const db = _dSkins[k];
        if(db && db.weapon_paint_id>0) return IMG_BASE+wkey+'-'+db.weapon_paint_id+'.png';
      } else if(cat==='gloves') {
        const k=def+'_'+t, db=_dSkins[k];
        if(db && db.weapon_paint_id>0) return IMG_BASE+gloveImgName(def)+'-'+db.weapon_paint_id+'.png';
      } else if(cat!=='music'&&cat!=='pins') {
        const k=def+'_'+t, db=_dSkins[k];
        if(db && db.weapon_paint_id>0) return IMG_BASE+wkey+'-'+db.weapon_paint_id+'.png';
      }
      return defImg;
    }

    card.dataset.imgCt = skinImg(3) || defImg;
    card.dataset.imgT  = skinImg(2) || defImg;
  });
})();

scUpdateCards();

// Initialize team state
scSetTeam(3);

// Staggered entrance animation
(function(){
  let i=0;
  document.querySelectorAll('.sc-card').forEach(card=>{
    if(card.classList.contains('has-skin')){
      card.style.cssText += ';animation-delay:'+(40 + i * 25)+'ms';
      card.classList.add('loaded');
    }else{
      card.style.cssText += ';opacity:0;transform:translateY(20px) scale(.97)';
      setTimeout(()=>{
        card.style.transition='opacity .45s ease, transform .45s cubic-bezier(.4,0,.2,1), border-color .35s, box-shadow .35s';
        card.style.opacity='1';
        card.style.transform='translateY(0) scale(1)';
      }, 40 + i * 25);
    }
    i++;
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
