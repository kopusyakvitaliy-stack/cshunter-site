<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Тест реакції';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-reaction-bg"></div>
<div class="breadcrumb">
  <a href="<?= SITE_URL ?>/">Головна</a>
  <span class="breadcrumb-sep">›</span>
  <span style="color:var(--text)">Тест реакції</span>
</div>

<style>
/* ── Page background ── */
.page-reaction-bg{
  position:fixed;
  inset:0;
  background: url('<?= SITE_URL ?>/assets/reaction-bg.png') center 0/cover no-repeat;
  filter:saturate(0) brightness(0.3);
  z-index:0;
  pointer-events:none;
  transform-origin:top center;
  will-change:opacity,transform;
}
.page-reaction-bg::after{
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(to bottom, rgba(9,9,11,0.4) 0%, rgba(9,9,11,0.75) 50%, rgba(9,9,11,1) 80%);
}

/* ── Reaction test layout ── */
.rt-wrap{max-width:860px;margin:0 auto;padding-bottom:64px;position:relative;z-index:1}

/* ── Info sections ── */
.rt-hero-text{text-align:center;margin-bottom:56px}
.rt-hero-tag{display:inline-block;font-family:'Manrope',sans-serif;font-size:10px;font-weight:800;letter-spacing:3px;text-transform:uppercase;color:var(--accent);background:rgba(240,196,48,.1);border:1px solid rgba(240,196,48,.2);padding:5px 14px;border-radius:20px;margin-bottom:20px}
.rt-hero-title{font-family:'Unbounded',sans-serif;font-size:52px;font-weight:900;text-transform:uppercase;letter-spacing:2px;line-height:1.05;margin-bottom:14px}
.rt-hero-title span{color:var(--accent);font-family:'Unbounded',sans-serif}
.rt-hero-desc{font-family:'Manrope',sans-serif;font-size:15px;color:var(--text-2);line-height:1.7;max-width:580px;margin:0 auto}

/* Info grid */
.rt-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.rt-info-card{background:rgba(26,29,39,.7);border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:24px;backdrop-filter:blur(10px)}
.rt-info-card-full{grid-column:1/-1}
.rt-info-card-icon{font-size:22px;margin-bottom:12px}
.rt-info-card-title{font-family:'Unbounded',sans-serif;font-size:20px;font-weight:900;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;color:var(--text)}
.rt-info-card-title span{color:var(--accent);font-family:'Unbounded',sans-serif}
.rt-info-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px}
.rt-info-list li{display:flex;gap:10px;font-family:'Manrope',sans-serif;font-size:13px;color:var(--text-2);line-height:1.5}
.rt-info-list li::before{content:'·';color:var(--accent);flex-shrink:0;font-weight:900;font-size:20px;line-height:1}

/* Facts grid */
.rt-facts-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:16px}
.rt-fact{background:rgba(26,29,39,.7);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:18px 20px;backdrop-filter:blur(10px)}
.rt-fact-num{font-family:'Unbounded',sans-serif;font-size:36px;font-weight:900;color:var(--accent);line-height:1;margin-bottom:4px}
.rt-fact-text{font-family:'Manrope',sans-serif;font-size:12px;color:var(--text-3);line-height:1.5}

/* Divider before test */
.rt-test-divider{display:flex;align-items:center;gap:16px;margin:48px 0 32px;opacity:.4}
.rt-test-divider::before,.rt-test-divider::after{content:'';flex:1;height:1px;background:var(--border)}
.rt-test-divider span{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--text-3);white-space:nowrap}

/* ── Leaderboard ── */
.rt-bottom-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:48px}
.rt-section-label{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;letter-spacing:3px;text-transform:uppercase;color:var(--text-3);margin-bottom:16px;display:flex;align-items:center;gap:10px}
.rt-section-label::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.06)}

.rt-lb{background:rgba(15,17,23,.75);border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:20px;backdrop-filter:blur(12px)}
.rt-lb-row{display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04);transition:background .2s}
.rt-lb-row:last-child{border-bottom:none}
.rt-lb-rank{font-family:'Unbounded',sans-serif;font-size:18px;font-weight:900;width:24px;text-align:center;flex-shrink:0;color:var(--text-3)}
.rt-lb-rank.gold{color:#F0C430}
.rt-lb-rank.silver{color:#A8ABBE}
.rt-lb-rank.bronze{color:#CD7F32}
.rt-lb-avatar{width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0;background:var(--surface-2)}
.rt-lb-info{flex:1;min-width:0}
.rt-lb-name{font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rt-lb-date{font-size:11px;color:var(--text-3);margin-top:2px;font-family:'Manrope',sans-serif}
.rt-lb-faceit{width:20px;height:20px;object-fit:contain;flex-shrink:0}
.rt-lb-ms{font-family:'Unbounded',sans-serif;font-size:22px;font-weight:900;flex-shrink:0;text-align:right}
.rt-lb-ms span{font-size:12px;opacity:.5;margin-left:2px}
.rt-lb-empty{text-align:center;padding:32px;color:var(--text-3);font-size:13px;font-family:'Manrope',sans-serif}

/* My history */
.rt-history-row{display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.rt-history-row:last-child{border-bottom:none}
.rt-history-num{font-family:'Manrope',sans-serif;font-size:11px;color:var(--text-3);width:16px;flex-shrink:0}
.rt-history-ms{font-family:'Unbounded',sans-serif;font-size:20px;font-weight:900}
.rt-history-ms span{font-size:11px;opacity:.4;margin-left:2px}
.rt-history-best{font-size:11px;color:var(--text-3);font-family:'Manrope',sans-serif}
.rt-history-date{font-size:11px;color:var(--text-3);font-family:'Manrope',sans-serif;margin-left:auto}
.rt-history-pb{font-size:9px;font-weight:800;letter-spacing:1px;text-transform:uppercase;background:rgba(240,196,48,.15);color:var(--accent);border:1px solid rgba(240,196,48,.3);padding:2px 6px;border-radius:4px;margin-left:6px;flex-shrink:0}

/* ── Limit banner ── */
.rt-limit-banner{background:rgba(244,67,54,.1);border:1px solid rgba(244,67,54,.25);border-radius:14px;padding:20px 24px;margin-bottom:20px;display:none;text-align:center}
.rt-limit-banner.show{display:block}
.rt-limit-title{font-family:'Unbounded',sans-serif;font-size:22px;font-weight:900;color:#FF5252;margin-bottom:6px}
.rt-limit-sub{font-family:'Manrope',sans-serif;font-size:13px;color:var(--text-3);margin-bottom:12px}
.rt-limit-timer{font-family:'Unbounded',sans-serif;font-size:32px;font-weight:900;color:var(--accent)}
.rt-attempts{display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:16px}
.rt-attempt-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.15);transition:all .3s}
.rt-attempt-dot.used{background:var(--accent)}
.rt-attempt-dot.left{background:rgba(255,255,255,.3);border:1px solid rgba(255,255,255,.2)}

/* ── PB row in history ── */
.rt-history-pb-row{background:rgba(240,196,48,.06);border:1px solid rgba(240,196,48,.2);border-radius:10px;padding:10px 14px;margin-bottom:10px;display:flex;align-items:center;gap:10px}
.rt-history-pb-crown{font-size:18px;flex-shrink:0}
.rt-history-pb-label{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:var(--accent);margin-bottom:2px}

/* Scroll animations */
.reveal{opacity:0;transform:translateY(24px);transition:opacity .6s ease, transform .6s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:.1s}
.reveal-delay-2{transition-delay:.2s}
.reveal-delay-3{transition-delay:.3s}
.reveal-delay-4{transition-delay:.4s}
.rt-title{font-family:'Unbounded',sans-serif;font-size:42px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin-bottom:4px}
.rt-sub{font-size:14px;color:var(--text-3);font-family:'Manrope',sans-serif;margin-bottom:32px}

/* ── Main click zone ── */
.rt-zone{
  position:relative;width:100%;aspect-ratio:16/7;border-radius:24px;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  cursor:pointer;user-select:none;overflow:hidden;
  transition:transform .15s ease, box-shadow .3s ease;
  border:1px solid rgba(255,255,255,.07);
  box-shadow:0 0 0 0 rgba(240,196,48,0);
}
.rt-zone:hover{box-shadow:none}
.rt-zone:active{transform:scale(.99)}
.rt-zone-bg{position:absolute;inset:0;transition:background .25s ease}

/* States */
.rt-zone.state-idle    .rt-zone-bg{background:linear-gradient(135deg,#0f1117,#1a1d27)}
.rt-zone.state-waiting .rt-zone-bg{background:linear-gradient(135deg,#1a0505,#3d0a0a)}
.rt-zone.state-go      .rt-zone-bg{background:linear-gradient(135deg,#051a08,#0a3d12)}
.rt-zone.state-result  .rt-zone-bg{background:linear-gradient(135deg,#0f1117,#1a1d27)}
.rt-zone.state-early   .rt-zone-bg{background:linear-gradient(135deg,#1a0d00,#3d2000)}

/* Ripple on click */
.rt-ripple{
  position:absolute;border-radius:50%;z-index:4;
  background:rgba(255,255,255,.15);
  transform:scale(0);pointer-events:none;
  animation:rtRipple .5s ease-out forwards;
}
@keyframes rtRipple{to{transform:scale(4);opacity:0}}

/* Text inside zone */
.rt-label{
  position:relative;z-index:3;text-align:center;
  transition:all .2s ease;
}
.rt-label-main{
  font-family:'Unbounded',sans-serif;
  font-size:36px;font-weight:900;text-transform:uppercase;letter-spacing:2px;
  line-height:1;margin-bottom:8px;
}
.rt-label-sub{
  font-size:13px;font-family:'Manrope',sans-serif;font-weight:500;
  letter-spacing:.5px;opacity:.45;text-transform:none;
}

/* Idle state — pulse button */
.rt-pulse-wrap{
  position:relative;
  display:flex;align-items:center;justify-content:center;
  width:100px;height:100px;
  margin:0 auto 28px;
}
.rt-pulse-ring{
  position:absolute;
  width:100%;height:100%;
  border-radius:50%;
  border:1.5px solid rgba(200,200,200,.15);
  animation:rtPulseRing 6s cubic-bezier(0.1,0,0.6,1) infinite;
  transform:scale(0.05);
  opacity:0;
}
.rt-pulse-ring:nth-child(2){animation-delay:2s}
.rt-pulse-ring:nth-child(3){animation-delay:4s}
@keyframes rtPulseRing{
  0%  {transform:scale(0.05);opacity:0}
  5%  {opacity:0}
  15% {opacity:.28}
  100%{transform:scale(1.8); opacity:0}
}
.rt-pulse-btn{
  display:flex;align-items:center;justify-content:center;
  position:relative;z-index:2;
  transition:transform .25s ease, opacity .25s ease;
  opacity:.7;
}
.rt-zone:hover .rt-pulse-btn{transform:scale(1.1);opacity:1}
.rt-pulse-btn svg{color:rgba(255,255,255,.9)}

/* Big time display */
.rt-time-big{
  font-family:'Unbounded',sans-serif;
  font-size:96px;font-weight:900;line-height:1;
  color:var(--accent);
  text-shadow:0 0 40px rgba(240,196,48,.4);
  animation:rtPop .3s cubic-bezier(.34,1.56,.64,1) both;
}
.rt-time-unit{font-size:28px;opacity:.6;margin-left:4px}
@keyframes rtPop{from{transform:scale(.7);opacity:0}to{transform:scale(1);opacity:1}}

/* Progress dots */
.rt-dots{display:flex;gap:10px;justify-content:center;margin-top:24px}
.rt-dot{
  width:12px;height:12px;border-radius:50%;
  border:2px solid var(--border-2);
  transition:all .3s ease;
}
.rt-dot.done{background:var(--accent);border-color:var(--accent);box-shadow:0 0 8px rgba(240,196,48,.5)}
.rt-dot.current{border-color:rgba(255,255,255,.4);animation:rtDotPulse 1s ease infinite}
@keyframes rtDotPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}

/* Results card */
.rt-results{
  background:var(--surface);border:1px solid var(--border-2);
  border-radius:20px;padding:32px;margin-top:28px;
  animation:fadeInUp .4s ease both;
  display:none;
}
.rt-results.show{display:block}
.rt-results-title{
  font-family:'Unbounded',sans-serif;font-size:22px;font-weight:900;
  text-transform:uppercase;letter-spacing:2px;color:var(--accent);margin-bottom:20px;
  display:flex;align-items:center;gap:10px;
}
.rt-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
.rt-stat{background:var(--bg-2);border-radius:12px;padding:16px;text-align:center}
.rt-stat-num{font-family:'Unbounded',sans-serif;font-size:36px;font-weight:900;color:var(--accent)}
.rt-stat-lbl{font-family:'Manrope',sans-serif;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--text-3);margin-top:4px}

/* Splits list */
.rt-splits{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.rt-split{
  display:flex;align-items:center;gap:12px;
  background:var(--bg-2);border-radius:10px;padding:10px 16px;
}
.rt-split-num{font-family:'Manrope',sans-serif;font-size:11px;font-weight:700;color:var(--text-3);width:20px}
.rt-split-bar-wrap{flex:1;height:6px;background:rgba(255,255,255,.06);border-radius:3px;overflow:hidden}
.rt-split-bar{height:100%;border-radius:3px;background:var(--accent);transition:width .6s cubic-bezier(.4,0,.2,1)}
.rt-split-val{font-family:'Unbounded',sans-serif;font-size:18px;font-weight:900;width:70px;text-align:right}
.rt-split-val.best{color:#4ADE80}
.rt-split-val.worst{color:#f44336}

/* Compare section */
.rt-compare{margin-top:8px}
.rt-compare-title{font-family:'Manrope',sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:2px;color:var(--text-3);margin-bottom:12px}
.rt-compare-item{display:flex;align-items:center;gap:12px;margin-bottom:8px}
.rt-compare-name{font-family:'Manrope',sans-serif;font-size:13px;font-weight:600;width:130px;flex-shrink:0}
.rt-compare-bar-wrap{flex:1;height:8px;background:rgba(255,255,255,.06);border-radius:4px;overflow:hidden;position:relative}
.rt-compare-bar{height:100%;border-radius:4px;transition:width .8s cubic-bezier(.4,0,.2,1)}
.rt-compare-val{font-family:'Unbounded',sans-serif;font-size:16px;font-weight:900;width:55px;text-align:right;color:var(--text-2)}

/* Retry button */
.rt-retry{
  width:100%;padding:14px;border-radius:12px;
  background:var(--accent);color:#000;
  font-family:'Unbounded',sans-serif;font-size:18px;font-weight:900;
  letter-spacing:1px;text-transform:uppercase;
  border:none;cursor:pointer;
  transition:all .2s ease;
  margin-top:4px;
}
.rt-retry:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(240,196,48,.3)}
.rt-retry:active{transform:scale(.97)}
</style>

<div class="rt-wrap">

  <!-- ── Hero ── -->
  <div class="rt-hero-text reveal">
    <div class="rt-hero-tag">Інше · Тест реакції</div>
    <div class="rt-hero-title"><span>Реакція</span> вирішує<br>дуелі в CS2</div>
    <div class="rt-hero-desc">Побачив ворога, натиснув першим. Живий. Не встиг. Мертвий. Перевір наскільки швидко ти реагуєш і порівняй себе з про-гравцями.</div>
  </div>

  <!-- ── Info cards ── -->
  <div class="rt-info-grid">
    <div class="rt-info-card reveal reveal-delay-1">
      <div class="rt-info-card-title">Чому реакція <span>критична</span></div>
      <ul class="rt-info-list">
        <li>Peeker's advantage дає тобі фрагмент секунди переваги — швидка реакція його не змарнує</li>
        <li>Flick, counter-strafe, wide swing — кожен з цих рухів вимагає миттєвої відповіді</li>
        <li>З ідеальним кросхейром але на 50 мс повільніше — ти програєш дуель</li>
      </ul>
    </div>
    <div class="rt-info-card reveal reveal-delay-2">
      <div class="rt-info-card-title">Як <span>покращити</span> реакцію</div>
      <ul class="rt-info-list">
        <li>Регулярні тренування на aim-картах і reflex-тренерах (KovaaK, Fast Reflex)</li>
        <li>Монітор 240+ Гц і мінімальний input lag реально відчутні в дуелях</li>
        <li>Повноцінний сон і активний спосіб життя прямо впливають на швидкість реакції</li>
        <li>10-15 хвилин розминки перед сесією можуть скоротити час реакції на 15-20 мс</li>
      </ul>
    </div>
  </div>

  <!-- ── Facts ── -->
  <div class="rt-facts-grid">
    <div class="rt-fact reveal reveal-delay-1">
      <div class="rt-fact-num">273 мс</div>
      <div class="rt-fact-text">Середній результат серед гравців на Human Benchmark. Це твоя точка відліку.</div>
    </div>
    <div class="rt-fact reveal reveal-delay-2">
      <div class="rt-fact-num">180-230 мс</div>
      <div class="rt-fact-text">Типовий показник s1mple, ZywOo і donk у тестах. В грі вони реагують ще краще завдяки читанню ситуацій.</div>
    </div>
    <div class="rt-fact reveal reveal-delay-3">
      <div class="rt-fact-num">3-6 мс/рік</div>
      <div class="rt-fact-text">На стільки сповільнюється реакція щороку після 25 років. Тренуватись варто зараз.</div>
    </div>
    <div class="rt-fact reveal reveal-delay-4">
      <div class="rt-fact-num">&lt;150 мс</div>
      <div class="rt-fact-text">Деякі про-гравці фіксували реакцію нижче 150 мс під час фліків — це практично межа людських можливостей.</div>
    </div>
  </div>

  <div class="rt-test-divider reveal"><span>Починай тест</span></div>

  <!-- Attempts tracker -->
  <div class="rt-attempts" id="rtAttempts">
    <?php if (isLoggedIn()): ?>
    <div class="rt-attempt-dot left" id="rtDotA0"></div>
    <div class="rt-attempt-dot left" id="rtDotA1"></div>
    <div class="rt-attempt-dot left" id="rtDotA2"></div>
    <div class="rt-attempt-dot left" id="rtDotA3"></div>
    <div class="rt-attempt-dot left" id="rtDotA4"></div>
    <?php endif; ?>
  </div>

  <!-- Limit banner -->
  <div class="rt-limit-banner" id="rtLimitBanner">
    <div class="rt-limit-title">Ліміт вичерпано на сьогодні</div>
    <div class="rt-limit-sub">5 спроб на день — щоб результати були чесними. Наступна спроба через:</div>
    <div class="rt-limit-timer" id="rtLimitTimer">—</div>
  </div>

  <!-- Click zone -->
  <div class="rt-zone state-idle" id="rtZone" onclick="rtHandleClick(event)">
    <div class="rt-zone-bg"></div>
    <div class="rt-label" id="rtLabel">
      <div id="rtPulseWrap" class="rt-pulse-wrap">
        <div class="rt-pulse-ring"></div>
        <div class="rt-pulse-ring"></div>
        <div class="rt-pulse-ring"></div>
        <div class="rt-pulse-btn">
          <svg width="56" height="56" viewBox="0 0 24 24" fill="currentColor" style="margin-left:6px"><polygon points="6 3 20 12 6 21 6 3"/></svg>
        </div>
      </div>
      <div class="rt-label-main" id="rtLabelMain" style="margin-bottom:6px">Натисни щоб почати</div>
      <div class="rt-label-sub" id="rtLabelSub">Клікни будь-де</div>
    </div>
  </div>

  <!-- Progress dots -->
  <div class="rt-dots" id="rtDots">
    <?php for($i=0;$i<5;$i++): ?>
    <div class="rt-dot" id="rtDot<?=$i?>"></div>
    <?php endfor; ?>
  </div>

  <!-- Results -->
  <div class="rt-results" id="rtResults">
    <div class="rt-results-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/></svg>
      Результати
    </div>

    <div class="rt-grid">
      <div class="rt-stat">
        <div class="rt-stat-num" id="resAvg">—</div>
        <div class="rt-stat-lbl">Середня мс</div>
      </div>
      <div class="rt-stat">
        <div class="rt-stat-num" id="resBest">—</div>
        <div class="rt-stat-lbl">Найкращий мс</div>
      </div>
    </div>

    <div class="rt-splits" id="resSplits"></div>

    <div class="rt-compare">
      <div class="rt-compare-title">Порівняння з кіберспортсменами</div>
      <div id="resCompare"></div>
    </div>

    <button class="rt-retry" onclick="rtReset()">Спробувати ще раз</button>
  </div>
</div>

<script>
// ── Parallax background ────────────────────────────────────────────────────────
const bgEl = document.querySelector('.page-reaction-bg');
function updateParallax() {
  if (!bgEl) return;
  const scrollY   = window.scrollY;
  const maxScroll = document.body.scrollHeight - window.innerHeight;
  const progress  = Math.min(scrollY / Math.max(maxScroll * 0.6, 400), 1);
  // Move bg up slightly and fade out as user scrolls
  bgEl.style.transform = `translateY(${scrollY * 0.3}px)`;
  bgEl.style.opacity   = Math.max(0, 1 - progress * 1.4);
}
window.addEventListener('scroll', updateParallax, { passive: true });
updateParallax();

// ── Scroll reveal ──────────────────────────────────────────────────────────────
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// ── Reaction Test ────────────────────────────────────────────────────────────
const ROUNDS       = 5;
const MAX_EARLY    = 3;   // false starts before restart
const MAX_ATTEMPTS = 5;   // per day
let earlyCount     = 0;
let attemptsLeft   = 5;
let limitTimerInterval = null;
const MIN_DELAY = 1500; // ms
const MAX_DELAY = 5000;

// Pro player benchmarks (average reaction time in ms)
const PROS = [
  { name: 's1mple',   ms: 185, color: '#FF9800' },
  { name: 'ZywOo',    ms: 182, color: '#FF9800' },
  { name: 'NiKo',     ms: 190, color: '#FF9800' },
  { name: 'Звичайний гравець', ms: 250, color: '#64B5F6' },
  { name: 'Новачок',  ms: 320, color: '#90A4AE' },
];

let state     = 'idle';   // idle | waiting | go | result | early
let times     = [];
let startTime = 0;
let timer     = null;

// Maps reaction time to a color: green (fast) → yellow → red (slow)
function rtGetColor(ms) {
  // Range: 100ms (elite) → 400ms (slow)
  const min = 100, max = 400;
  const t = Math.max(0, Math.min(1, (ms - min) / (max - min)));
  // Interpolate: green(120,220,80) → yellow(240,196,48) → red(244,67,54)
  let r, g, b;
  if (t < 0.5) {
    // bright green → yellow
    const p = t * 2;
    r = Math.round(50  + (240 - 50)  * p);
    g = Math.round(235 + (196 - 235) * p);
    b = Math.round(50  + (48  - 50)  * p);
  } else {
    // yellow → bright red
    const p = (t - 0.5) * 2;
    r = Math.round(240 + (255 - 240) * p);
    g = Math.round(196 + (40  - 196) * p);
    b = Math.round(48  + (40  - 48)  * p);
  }
  return `rgb(${r},${g},${b})`;
}

function rtHandleClick(e) {
  // Block if limit reached
  if (attemptsLeft <= 0 && state === 'idle') return;
  // Ripple effect
  const zone = document.getElementById('rtZone');
  const rect  = zone.getBoundingClientRect();
  const rip   = document.createElement('div');
  const size  = Math.max(rect.width, rect.height);
  rip.className = 'rt-ripple';
  rip.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
  zone.appendChild(rip);
  setTimeout(() => rip.remove(), 500);

  if (state === 'idle')    return rtStartRound();
  if (state === 'waiting') return rtEarly();
  if (state === 'go')      return rtRecord();
  if (state === 'result')  return rtNextRound();
  if (state === 'early')   return rtStartRound();
}

function rtSetState(s, labelMain, labelSub, timeDisplay, timeColor) {
  // Show/hide pulse wrap
  const pw = document.getElementById('rtPulseWrap');
  if (pw) pw.style.display = (s === 'idle') ? 'flex' : 'none';
  state = s;
  const zone = document.getElementById('rtZone');
  zone.className = 'rt-zone state-' + s;

  const lm = document.getElementById('rtLabelMain');
  const ls = document.getElementById('rtLabelSub');

  if (timeDisplay) {
    lm.innerHTML = `<div class="rt-time-big" style="color:${timeColor||'var(--accent)'};text-shadow:0 0 40px ${timeColor||'rgba(240,196,48,.4)'}40">${timeDisplay}<span class="rt-time-unit">мс</span></div>`;
    ls.textContent = labelSub;
  } else {
    lm.textContent = labelMain;
    ls.textContent  = labelSub;
  }
}

function rtStartRound() {
  clearTimeout(timer);
  rtSetState('waiting', 'Чекай...', 'Не натискай передчасно');
  updateDots();

  const delay = MIN_DELAY + Math.random() * (MAX_DELAY - MIN_DELAY);
  timer = setTimeout(() => {
    if (state !== 'waiting') return;
    startTime = performance.now();
    rtSetState('go', 'НАТИСКАЙ!', 'Вже!');
  }, delay);
}

function rtEarly() {
  clearTimeout(timer);
  earlyCount++;
  if (earlyCount >= MAX_EARLY) {
    times      = [];
    earlyCount = 0;
    updateDots();
    rtSetState('idle', 'Забагато фальстартів', 'Будь уважнішим — натискай тільки коли екран зеленіє.');
    state = 'idle';
    return;
  }
  rtSetState('early', 'Зарано!', 'Натисни щоб спробувати знову');
}

function rtRecord() {
  const ms = Math.round(performance.now() - startTime);
  times.push(ms);
  rtSetState('result', '', `Клік ${times.length} / ${ROUNDS} — натисни щоб продовжити`, ms);
  updateDots();

  if (times.length >= ROUNDS) {
    setTimeout(rtShowResults, 900);
  }
}

function rtNextRound() {
  if (times.length >= ROUNDS) return rtShowResults();
  rtStartRound();
}

function updateDots() {
  for (let i = 0; i < ROUNDS; i++) {
    const dot = document.getElementById('rtDot' + i);
    dot.className = 'rt-dot';
    if (i < times.length)          dot.classList.add('done');
    else if (i === times.length)   dot.classList.add('current');
  }
}

function rtShowResults() {
  const avg  = Math.round(times.reduce((a, b) => a + b, 0) / times.length);
  const best = Math.min(...times);
  const worst = Math.max(...times);

  document.getElementById('resAvg').textContent  = avg;
  document.getElementById('resBest').textContent = best;

  // Splits
  const splitsEl = document.getElementById('resSplits');
  const maxTime  = worst;
  splitsEl.innerHTML = times.map((t, i) => {
    const pct   = Math.round((t / maxTime) * 100);
    const col   = rtGetColor(t);
    return `<div class="rt-split">
      <span class="rt-split-num">${i + 1}</span>
      <div class="rt-split-bar-wrap">
        <div class="rt-split-bar" style="width:0%;background:${col}" data-pct="${pct}"></div>
      </div>
      <span class="rt-split-val" style="color:${col}">${t} мс</span>
    </div>`;
  }).join('');

  // Animate bars
  setTimeout(() => {
    document.querySelectorAll('.rt-split-bar').forEach(b => {
      b.style.width = b.dataset.pct + '%';
    });
  }, 100);

  // Compare
  const allMs  = [...PROS.map(p => p.ms), avg].sort((a, b) => a - b);
  const maxMs  = allMs[allMs.length - 1];
  const cmpEl  = document.getElementById('resCompare');

  const youColor = rtGetColor(avg);

  cmpEl.innerHTML = [
    ...PROS.map(p => ({...p, color: rtGetColor(p.ms)})),
    { name: 'Ти', ms: avg, color: youColor, isYou: true },
  ]
  .sort((a, b) => a.ms - b.ms)
  .map(p => {
    const pct = Math.round((p.ms / maxMs) * 85) + 15;
    const bold = p.isYou ? 'font-weight:800;color:var(--text)' : '';
    return `<div class="rt-compare-item">
      <span class="rt-compare-name" style="${bold}">${p.isYou ? '⚡ ' : ''}${p.name}</span>
      <div class="rt-compare-bar-wrap">
        <div class="rt-compare-bar" style="width:0%;background:${p.color}" data-pct="${pct}"></div>
      </div>
      <span class="rt-compare-val">${p.ms}</span>
    </div>`;
  }).join('');

  setTimeout(() => {
    document.querySelectorAll('.rt-compare-bar').forEach(b => {
      b.style.width = b.dataset.pct + '%';
    });
  }, 200);

  // Save score if logged in
  <?php if (isLoggedIn()): ?>
  (function() {
    const fd = new FormData();
    fd.append('action',       'save');
    fd.append('avg_ms',       avg);
    fd.append('best_ms',      best);
    fd.append('splits',       JSON.stringify(times));
    fd.append('early_clicks', earlyCount);
    fetch('/api/reaction_score.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          // Update attempts dots
          updateAttemptDots(MAX_ATTEMPTS - data.attempts_left, MAX_ATTEMPTS);
          attemptsLeft = data.attempts_left;
          if (attemptsLeft === 0) {
            // Fetch status to get next_at timestamp
            fetch('/api/reaction_score.php?action=my_status')
              .then(r => r.json())
              .then(s => showLimitBanner(s.next_at));
          }
          // Show PB badge
          if (data.is_pb) {
            const avg_el = document.getElementById('resAvg');
            if (avg_el) avg_el.insertAdjacentHTML('afterend',
              '<div style="font-family:Manrope,sans-serif;font-size:10px;font-weight:800;letter-spacing:1.5px;color:var(--accent);text-transform:uppercase;margin-top:4px">👑 Особистий рекорд!</div>'
            );
          }
          loadMyHistory();
          loadLeaderboard();
        } else if (data.error === 'limit_reached') {
          showLimitBanner(data.next_at);
        }
      });
  })();
  <?php endif; ?>

  // Show results card
  document.getElementById('rtResults').classList.add('show');

  // Reset zone
  rtSetState('idle', 'Готово', 'Дивись результати нижче');
  state = 'done';
}

function rtReset() {
  clearTimeout(timer);
  times      = [];
  earlyCount = 0;
  state      = 'idle';
  document.getElementById('rtResults').classList.remove('show');
  rtSetState('idle', 'Натисни щоб почати', 'Клікні будь-де');
  updateDots();
}

// Init dots
updateDots();
</script>

<!-- ── Bottom: leaderboard + my history ── -->
<div class="rt-wrap" style="margin-top:0;padding-top:0">
  <div class="rt-bottom-grid">

    <!-- Leaderboard -->
    <div>
      <div class="rt-section-label">Топ гравців сайту</div>
      <div class="rt-lb" id="rtLeaderboard">
        <div class="rt-lb-empty">Завантаження...</div>
      </div>
    </div>

    <!-- My results -->
    <div>
      <div class="rt-section-label">Мої результати</div>
      <div class="rt-lb" id="rtMyHistory">
        <?php if (isLoggedIn()): ?>
        <div class="rt-lb-empty">Завантаження...</div>
        <?php else: ?>
        <div class="rt-lb-empty">
          Увійди через Steam щоб зберігати результати
        </div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
</div>

<script>
// ── Attempts tracker ─────────────────────────────────────────────────────────
function updateAttemptDots(used, total) {
  for (let i = 0; i < total; i++) {
    const d = document.getElementById('rtDotA' + i);
    if (!d) continue;
    d.className = 'rt-attempt-dot ' + (i < used ? 'used' : 'left');
  }
}

function showLimitBanner(nextAt) {
  const banner = document.getElementById('rtLimitBanner');
  const zone   = document.getElementById('rtZone');
  if (banner) banner.classList.add('show');
  if (zone) {
    zone.style.opacity      = '0.3';
    zone.style.pointerEvents = 'none';
  }
  if (nextAt) startLimitTimer(nextAt);
}

function startLimitTimer(nextAtTimestamp) {
  if (limitTimerInterval) clearInterval(limitTimerInterval);
  const el = document.getElementById('rtLimitTimer');
  function tick() {
    const diff = nextAtTimestamp * 1000 - Date.now();
    if (diff <= 0) {
      clearInterval(limitTimerInterval);
      if (el) el.textContent = 'Доступно зараз!';
      const banner = document.getElementById('rtLimitBanner');
      const zone   = document.getElementById('rtZone');
      if (banner) banner.classList.remove('show');
      if (zone) { zone.style.opacity = '1'; zone.style.pointerEvents = ''; }
      updateAttemptDots(0, MAX_ATTEMPTS);
      return;
    }
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    if (el) el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
  }
  tick();
  limitTimerInterval = setInterval(tick, 1000);
}

// ── Load initial status (logged in users) ─────────────────────────────────────
<?php if (isLoggedIn()): ?>
fetch('/api/reaction_score.php?action=my_status')
  .then(r => r.json())
  .then(data => {
    const used = MAX_ATTEMPTS - data.attempts_left;
    updateAttemptDots(used, MAX_ATTEMPTS);
    attemptsLeft = data.attempts_left;
    if (data.attempts_left === 0 && data.next_at) {
      showLimitBanner(data.next_at);
    }
  });
<?php endif; ?>

// ── Leaderboard + History ─────────────────────────────────────────────────────
function loadLeaderboard() {
  fetch('/api/reaction_score.php?action=leaderboard')
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('rtLeaderboard');
      if (!data.leaderboard || data.leaderboard.length === 0) {
        el.innerHTML = '<div class="rt-lb-empty">Ще немає результатів. Будь першим!</div>';
        return;
      }
      el.innerHTML = data.leaderboard.map(r => {
        const rankClass = r.rank===1 ? 'gold' : r.rank===2 ? 'silver' : r.rank===3 ? 'bronze' : '';
        const color     = rtGetColor(r.best_avg);
        const date      = new Date(r.achieved_at).toLocaleDateString('uk-UA',{day:'numeric',month:'short',year:'numeric'});
        return `<div class="rt-lb-row">
          <div class="rt-lb-rank ${rankClass}">${r.rank}</div>
          <img class="rt-lb-avatar" src="${escHtml(r.avatar_url)}" alt="" onerror="this.style.display='none'">
          <div class="rt-lb-info">
            <div class="rt-lb-name">${escHtml(r.steam_name)}</div>
            <div class="rt-lb-date">${date}</div>
          </div>
          <div class="rt-lb-ms" style="color:${color}">${r.best_avg}<span>мс</span></div>
        </div>`;
      }).join('');
    })
    .catch(() => {
      document.getElementById('rtLeaderboard').innerHTML = '<div class="rt-lb-empty">Помилка завантаження</div>';
    });
}

<?php if (isLoggedIn()): ?>
function loadMyHistory() {
  fetch('/api/reaction_score.php?action=my_history')
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('rtMyHistory');
      if (!data.history || data.history.length === 0) {
        el.innerHTML = '<div class="rt-lb-empty">Ще немає результатів. Пройди тест!</div>';
        return;
      }
      const pb = data.personal_best;
      // PB row on top
      let html = '';
      if (pb) {
        const pbColor = rtGetColor(pb.avg_ms);
        const pbDate  = new Date(pb.created_at).toLocaleDateString('uk-UA',{day:'numeric',month:'short',year:'numeric'});
        html += `<div class="rt-history-pb-row">
          <div class="rt-history-pb-crown">👑</div>
          <div style="flex:1">
            <div class="rt-history-pb-label">Особистий рекорд</div>
            <div style="font-family:'Unbounded',sans-serif;font-size:24px;font-weight:900;color:${pbColor}">${pb.avg_ms}<span style="font-size:13px;opacity:.5;margin-left:3px">мс</span></div>
          </div>
          <div class="rt-history-date">${pbDate}</div>
        </div>`;
      }
      // Last 10
      html += data.history.map((r, i) => {
        const color = rtGetColor(r.avg_ms);
        const date  = new Date(r.created_at).toLocaleDateString('uk-UA',{day:'numeric',month:'short'});
        const isBest = pb && r.avg_ms === pb.avg_ms && r.created_at === pb.created_at;
        return `<div class="rt-history-row">
          <div class="rt-history-num">${i+1}</div>
          <div class="rt-history-ms" style="color:${color}">${r.avg_ms}<span>мс</span></div>
          <div class="rt-history-best">кращий ${r.best_ms} мс</div>
          ${isBest ? '<div class="rt-history-pb">👑</div>' : ''}
          <div class="rt-history-date">${date}</div>
        </div>`;
      }).join('');
      el.innerHTML = html;
    });
}
loadMyHistory();
<?php endif; ?>

loadLeaderboard();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
