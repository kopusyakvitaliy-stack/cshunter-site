<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = 'Launch Options Generator';
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= SITE_URL ?>/">Головна</a>
  <span class="breadcrumb-sep">›</span>
  <span style="color:var(--text)">Launch Options</span>
</div>

<style>
/* ─── Scroll reveal (same as reaction page) ──────────────────────────────────*/
.reveal{opacity:0;transform:translateY(22px);transition:opacity .55s ease,transform .55s ease}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:.08s}
.reveal-delay-2{transition-delay:.16s}
.reveal-delay-3{transition-delay:.24s}
.reveal-delay-4{transition-delay:.32s}
.reveal-delay-5{transition-delay:.40s}

/* ─── Layout ─────────────────────────────────────────────────────────────────*/
.lo-wrap {
  max-width: 1000px;
  margin: 0 auto;
  padding: 0 0 72px;
}

/* ─── Hero ───────────────────────────────────────────────────────────────────*/
.lo-hero {
  text-align: center;
  margin-bottom: 36px;
}
.lo-hero-tag {
  display: inline-block;
  font-family: 'Manrope', sans-serif;
  font-size: 10px; font-weight: 800;
  letter-spacing: 3px; text-transform: uppercase;
  color: var(--accent);
  background: rgba(240,196,48,.1);
  border: 1px solid rgba(240,196,48,.2);
  padding: 5px 14px; border-radius: 20px;
  margin-bottom: 16px;
}
.lo-hero-title {
  font-family: 'Unbounded', sans-serif;
  font-size: 40px; font-weight: 900;
  text-transform: uppercase; letter-spacing: 1px;
  line-height: 1.05; margin-bottom: 10px;
}
.lo-hero-title span { color: var(--accent); font-family: 'Unbounded', sans-serif; }
.lo-hero-desc {
  font-family: 'Manrope', sans-serif;
  font-size: 14px; color: var(--text-3); line-height: 1.6;
}

/* ─── Output bar ─────────────────────────────────────────────────────────────*/
.lo-output-wrap {
  position: sticky;
  top: 0;
  z-index: 90;
  background: var(--bg);
  border-bottom: 1px solid rgba(255,255,255,.07);
  padding: 14px 0 16px;
  margin-bottom: 28px;
}
.lo-output-label {
  font-family: 'Manrope', sans-serif;
  font-size: 10px; font-weight: 700;
  letter-spacing: 2.5px; text-transform: uppercase;
  color: var(--text-3); margin-bottom: 8px;
  display: flex; align-items: center; gap: 8px;
}
.lo-output-label-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--green);
  box-shadow: 0 0 6px var(--green);
  animation: lo-pulse 2s infinite;
}
@keyframes lo-pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.lo-output-box {
  display: flex; gap: 10px; align-items: stretch;
}
.lo-output-code {
  flex: 1;
  font-family: 'Unbounded', monospace;
  font-size: 12px;
  background: #0a0b0f;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 10px;
  padding: 12px 16px;
  color: var(--accent);
  word-break: break-all;
  line-height: 1.6;
  min-height: 44px;
  transition: border-color .2s;
}
.lo-output-code:empty::after {
  content: 'Обери параметри нижче...';
  color: var(--text-3);
  font-family: 'Manrope', sans-serif;
  font-size: 13px;
  font-weight: 500;
}
.lo-copy-btn {
  display: flex; align-items: center; gap: 7px;
  padding: 0 20px;
  background: var(--accent); color: #000;
  font-family: 'Manrope', sans-serif;
  font-size: 13px; font-weight: 800;
  border: none; border-radius: 10px;
  cursor: pointer; white-space: nowrap;
  transition: opacity .18s, transform .1s;
  flex-shrink: 0;
}
.lo-copy-btn:hover { opacity: .85; }
.lo-copy-btn:active { transform: scale(.97); }
.lo-char-count {
  font-family: 'Manrope', sans-serif;
  font-size: 11px; color: var(--text-3);
  margin-top: 6px; text-align: right;
}

/* ─── Presets ────────────────────────────────────────────────────────────────*/
.lo-presets-section { margin-bottom: 28px; }
.lo-section-lbl {
  font-family: 'Manrope', sans-serif;
  font-size: 10px; font-weight: 700;
  letter-spacing: 3px; text-transform: uppercase;
  color: var(--text-3); margin-bottom: 14px;
  display: flex; align-items: center; gap: 10px;
}
.lo-section-lbl::after {
  content: ''; flex: 1; height: 1px;
  background: rgba(255,255,255,.06);
}
.lo-presets-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 10px;
}
.lo-preset-btn {
  display: flex; flex-direction: column; gap: 5px;
  padding: 14px 16px;
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 12px;
  cursor: pointer; text-align: left;
  transition: all .18s;
}
.lo-preset-btn:hover {
  border-color: rgba(240,196,48,.3);
  background: rgba(240,196,48,.04);
}
.lo-preset-btn.active {
  border-color: var(--accent);
  background: rgba(240,196,48,.08);
}
.lo-preset-icon { font-size: 20px; margin-bottom: 2px; }
.lo-preset-name {
  font-family: 'Manrope', sans-serif;
  font-size: 13px; font-weight: 800; color: var(--text);
}
.lo-preset-desc {
  font-family: 'Manrope', sans-serif;
  font-size: 11px; color: var(--text-3); line-height: 1.4;
}

/* ─── Pro players section ───────────────────────────────────────────────────*/
.lo-pros-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 10px;
  margin-bottom: 28px;
}
.lo-pro-card {
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.07);
  border-radius: 12px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all .18s;
}
.lo-pro-card:hover {
  border-color: rgba(240,196,48,.3);
  background: rgba(240,196,48,.04);
}
.lo-pro-name {
  font-family: 'Unbounded', sans-serif;
  font-size: 13px; font-weight: 900; color: var(--accent);
  margin-bottom: 3px;
}
.lo-pro-team {
  font-family: 'Manrope', sans-serif;
  font-size: 11px; color: var(--text-3); margin-bottom: 8px;
}
.lo-pro-opts {
  font-family: 'Manrope', monospace;
  font-size: 10px; color: var(--text-2);
  line-height: 1.6; word-break: break-all;
}

/* ─── Options grid ──────────────────────────────────────────────────────────*/
.lo-category { margin-bottom: 28px; }
.lo-options-grid {
  display: flex; flex-direction: column; gap: 8px;
}
.lo-option-row {
  background: var(--surface);
  border: 1px solid rgba(255,255,255,.06);
  border-radius: 12px;
  padding: 14px 18px;
  display: flex; align-items: flex-start; gap: 14px;
  transition: border-color .18s;
  cursor: pointer;
}
.lo-option-row:hover { border-color: rgba(255,255,255,.15); }
.lo-option-row.active {
  border-color: rgba(240,196,48,.4);
  background: rgba(240,196,48,.04);
}
.lo-option-row.warning {
  border-color: rgba(251,146,60,.3) !important;
}
.lo-option-row.warning.active {
  border-color: rgba(251,146,60,.6) !important;
  background: rgba(251,146,60,.05) !important;
}

/* Checkbox */
.lo-checkbox {
  width: 18px; height: 18px; flex-shrink: 0;
  border: 2px solid rgba(255,255,255,.2);
  border-radius: 5px; margin-top: 2px;
  display: flex; align-items: center; justify-content: center;
  transition: all .15s;
}
.lo-option-row.active .lo-checkbox {
  background: var(--accent); border-color: var(--accent);
}
.lo-option-row.warning.active .lo-checkbox {
  background: #FB923C; border-color: #FB923C;
}
.lo-checkbox-tick {
  width: 10px; height: 10px; display: none;
}
.lo-option-row.active .lo-checkbox-tick { display: block; }

/* Content */
.lo-option-body { flex: 1; min-width: 0; }
.lo-option-top {
  display: flex; align-items: center;
  gap: 10px; margin-bottom: 4px; flex-wrap: wrap;
}
.lo-option-cmd {
  font-family: 'Unbounded', monospace;
  font-size: 12px; font-weight: 700;
  color: var(--accent);
}
.lo-option-row.warning .lo-option-cmd { color: #FB923C; }
.lo-option-badge {
  font-family: 'Manrope', sans-serif;
  font-size: 9px; font-weight: 800;
  letter-spacing: 1.5px; text-transform: uppercase;
  padding: 2px 7px; border-radius: 4px;
}
.badge-recommended { background: rgba(74,222,128,.12); color: #4ADE80; }
.badge-fps         { background: rgba(240,196,48,.12);  color: var(--accent); }
.badge-network     { background: rgba(66,165,245,.12);  color: #42a5f5; }
.badge-caution     { background: rgba(251,146,60,.12);  color: #FB923C; }
.badge-pro         { background: rgba(206,147,216,.12); color: #CE93D8; }
.lo-option-name {
  font-family: 'Manrope', sans-serif;
  font-size: 13px; font-weight: 700; color: var(--text);
}
.lo-option-desc {
  font-family: 'Manrope', sans-serif;
  font-size: 12px; color: var(--text-3); line-height: 1.55;
}
.lo-option-impact {
  display: flex; align-items: center; gap: 5px;
  margin-top: 6px;
}
.lo-impact-dot {
  width: 7px; height: 7px; border-radius: 50%;
}
.lo-impact-text {
  font-family: 'Manrope', sans-serif;
  font-size: 11px; font-weight: 600; color: var(--text-3);
}

/* Input row (for options with values) */
.lo-option-input-wrap {
  display: flex; align-items: center; gap: 8px; margin-top: 8px;
}
.lo-option-input-wrap input,
.lo-option-input-wrap select {
  background: var(--bg);
  border: 1px solid rgba(255,255,255,.12);
  border-radius: 7px;
  color: var(--text);
  font-family: 'Unbounded', monospace;
  font-size: 11px;
  padding: 5px 10px;
  width: 110px;
  transition: border-color .18s;
}
.lo-option-input-wrap input:focus,
.lo-option-input-wrap select:focus {
  outline: none; border-color: rgba(240,196,48,.5);
}
.lo-option-input-wrap select option { background: #1a1b23; }
.lo-input-hint {
  font-family: 'Manrope', sans-serif;
  font-size: 11px; color: var(--text-3);
}

/* ─── Reset button ────────────────────────────────────────────────────────── */
.lo-reset-btn {
  display: flex; align-items: center; gap: 7px;
  padding: 10px 18px;
  background: transparent;
  border: 1px solid rgba(255,255,255,.1);
  border-radius: 9px;
  color: var(--text-3);
  font-family: 'Manrope', sans-serif;
  font-size: 12px; font-weight: 700;
  cursor: pointer;
  transition: all .18s;
  margin-top: 20px;
}
.lo-reset-btn:hover { border-color: rgba(248,113,113,.4); color: #F87171; }

/* ─── Info box ────────────────────────────────────────────────────────────── */
.lo-info-box {
  background: rgba(66,165,245,.06);
  border: 1px solid rgba(66,165,245,.2);
  border-radius: 12px;
  padding: 16px 20px;
  font-family: 'Manrope', sans-serif;
  font-size: 13px; color: var(--text-2);
  line-height: 1.6;
  margin-bottom: 28px;
  display: flex; gap: 12px; align-items: flex-start;
}
.lo-info-icon { font-size: 18px; flex-shrink: 0; margin-top: 1px; }
</style>

<div class="lo-wrap">

  <!-- Hero -->
  <div class="lo-hero reveal">
    <div class="lo-hero-tag">Інше · Параметри запуску</div>
    <div class="lo-hero-title">Launch Options<br><span>Generator</span></div>
    <p class="lo-hero-desc">Обирай параметри — рядок генерується миттєво.</p>
  </div>

  <!-- Sticky output -->
  <div class="lo-output-wrap">
    <div class="lo-output-label">
      <div class="lo-output-label-dot"></div>
      Твій рядок Launch Options
    </div>
    <div class="lo-output-box">
      <div class="lo-output-code" id="loOutput"></div>
      <button class="lo-copy-btn" id="loCopyBtn" onclick="copyOutput()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        Копіювати
      </button>
    </div>
    <div class="lo-char-count" id="loCharCount">0 символів</div>
  </div>

  <!-- How to apply -->
  <div class="lo-info-box reveal reveal-delay-1">
    <div class="lo-info-icon">ℹ️</div>
    <div>
      <strong style="color:var(--text)">Як застосувати:</strong> Steam → Бібліотека → ПКМ на CS2 → Властивості → Параметри запуску → вставити рядок. Перезапусти гру.
    </div>
  </div>

  <!-- Presets -->
  <div class="lo-presets-section reveal reveal-delay-2">
    <div class="lo-section-lbl">Готові пресети</div>
    <div class="lo-presets-grid">
      <button class="lo-preset-btn" onclick="applyPreset('minimal')">
        <div class="lo-preset-icon">✅</div>
        <div class="lo-preset-name">Мінімальний</div>
        <div class="lo-preset-desc">Тільки безпечне й перевірене. Підходить усім.</div>
      </button>
      <button class="lo-preset-btn" onclick="applyPreset('competitive')">
        <div class="lo-preset-icon">🏆</div>
        <div class="lo-preset-name">Змагальний</div>
        <div class="lo-preset-desc">Максимум FPS + мережа. Для серйозної гри.</div>
      </button>
      <button class="lo-preset-btn" onclick="applyPreset('lowend')">
        <div class="lo-preset-icon">💻</div>
        <div class="lo-preset-name">Слабкий ПК</div>
        <div class="lo-preset-desc">Зменшення навантаження, стабільний FPS.</div>
      </button>
      <button class="lo-preset-btn" onclick="applyPreset('highend')">
        <div class="lo-preset-icon">🚀</div>
        <div class="lo-preset-name">Топовий ПК</div>
        <div class="lo-preset-desc">RTX / Ryzen. Без обмежень FPS, мінімум латенсі.</div>
      </button>
      <button class="lo-preset-btn" onclick="applyPreset('streamer')">
        <div class="lo-preset-icon">🎙️</div>
        <div class="lo-preset-name">Стрімер</div>
        <div class="lo-preset-desc">Сумісність з OBS + третім ПЗ.</div>
      </button>
      <button class="lo-preset-btn" id="preset-custom-indicator" style="display:none" onclick="void(0)">
        <div class="lo-preset-icon">✏️</div>
        <div class="lo-preset-name">Кастомний</div>
        <div class="lo-preset-desc">Ти змінив пресет — це твій унікальний набір.</div>
      </button>
      <button class="lo-preset-btn" onclick="applyPreset('reset')">
        <div class="lo-preset-icon">🔄</div>
        <div class="lo-preset-name">Скинути все</div>
        <div class="lo-preset-desc">Повернути до заводських налаштувань CS2.</div>
      </button>
    </div>
  </div>

  <!-- Pro Players -->
  <div class="lo-section-lbl reveal reveal-delay-1">Конфіги про-гравців & стрімерів</div>
  <div class="lo-pros-grid reveal reveal-delay-2" style="margin-bottom:28px">
    <div class="lo-pro-card" onclick="applyProConfig('zywoo')">
      <div class="lo-pro-name">ZywOo</div>
      <div class="lo-pro-team">Team Vitality</div>
      <div class="lo-pro-opts">-novid -tickrate 128 -allow_third_party_software +fps_max 400</div>
    </div>
    <div class="lo-pro-card" onclick="applyProConfig('niko')">
      <div class="lo-pro-name">NiKo</div>
      <div class="lo-pro-team">Team Falcons</div>
      <div class="lo-pro-opts">-novid -console -tickrate 128 -freq 240</div>
    </div>
    <div class="lo-pro-card" onclick="applyProConfig('frozen')">
      <div class="lo-pro-name">frozen</div>
      <div class="lo-pro-team">FaZe Clan</div>
      <div class="lo-pro-opts">-freq 360 +rate 786432 +fps_max 500 -tickrate 128</div>
    </div>
    <div class="lo-pro-card" onclick="applyProConfig('f0rest')">
      <div class="lo-pro-name">f0rest</div>
      <div class="lo-pro-team">Легенда CS · Стрімер</div>
      <div class="lo-pro-opts">-novid -high -freq 240 +fps_max 0 -allow_third_party_software</div>
    </div>
  </div>

  <!-- ── CATEGORY: Performance ── -->
  <div class="lo-category reveal">
    <div class="lo-section-lbl">⚡ Продуктивність & FPS</div>
    <div class="lo-options-grid">

      <div class="lo-option-row" id="opt-novid" onclick="toggleOption('novid')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-novid</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
          </div>
          <div class="lo-option-name">Пропустити вступне відео</div>
          <div class="lo-option-desc">Вимикає Valve intro при кожному запуску. Економить 5–8 секунд. Використовують 95%+ гравців.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Швидший старт · Без ризику</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-nojoy" onclick="toggleOption('nojoy')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-nojoy</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
          </div>
          <div class="lo-option-name">Вимкнути підтримку джойстика</div>
          <div class="lo-option-desc">Вивільняє RAM та ресурси CPU, якщо ти граєш мишею та клавіатурою. Практично нульовий ризик.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Мінімальний приріст FPS · Всі ПК</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-high" onclick="toggleOption('high')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-high</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Високий пріоритет процесу</div>
          <div class="lo-option-desc">Надає CS2 пріоритет над іншими процесами. Корисно на слабких та середніх ПК. На деяких системах може спричинити нестабільність — протестуй.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">+FPS на слабких ПК · Тестуй індивідуально</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-fullscreen" onclick="toggleOption('fullscreen')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-fullscreen</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Примусовий повноекранний режим</div>
          <div class="lo-option-desc">Змушує CS2 запускатись у повноекранному режимі — Windows не витрачає ресурси на рендер UI. Краща продуктивність порівняно з Windowed/Borderless.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Рекомендовано всім · Менша латенсі</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-forcenovsync" onclick="toggleOption('forcenovsync')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-forcenovsync</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
          </div>
          <div class="lo-option-name">Вимкнути VSync</div>
          <div class="lo-option-desc">VSync додає 15+ мс вхідної затримки. Вимикати обов'язково для змагальної гри. Якщо маєш G-Sync/FreeSync — вони замінять VSync без затримки.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">-15мс input lag · Критично для ranked</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-softparticles" onclick="toggleOption('softparticles')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-softparticlesdefaultoff</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Вимкнути згладжування частинок</div>
          <div class="lo-option-desc">Прибирає depth-blending для вибухів, диму, вогню. Зменшує навантаження на GPU особливо під час гранат. Мінімальна різниця у вигляді.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">+FPS під час гранат · Слабкий/середній ПК</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-mat_queue" onclick="toggleOption('mat_queue')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+mat_queue_mode 2</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Мультиядерна обробка матеріалів</div>
          <div class="lo-option-desc">Вмикає асинхронну чергу рендерингу. Зазвичай дає +5–20% FPS на багатоядерних CPU. На деяких конфігах може викликати фризи — тестуй.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">+FPS на багатоядерних CPU · Тестуй</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-cl_forcepreload" onclick="toggleOption('cl_forcepreload')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+cl_forcepreload 1</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Передзавантаження ресурсів карти</div>
          <div class="lo-option-desc">Завантажує всі текстури та моделі перед входом на сервер. Зменшує фрізи під час гри, але збільшує час завантаження. Рекомендовано якщо є SSD.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">Менше фрізів у грі · Довше завантаження</span>
          </div>
        </div>
      </div>

      <!-- FPS Max with input -->
      <div class="lo-option-row" id="opt-fpsmax" onclick="toggleOption('fpsmax')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd" id="fpsmax-cmd-preview">+fps_max 0</span>
            <span class="lo-option-badge badge-fps">FPS</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
          </div>
          <div class="lo-option-name">Ліміт FPS</div>
          <div class="lo-option-desc">0 = без ліміту (максимальна продуктивність). Для NVIDIA Reflex + G-Sync рекомендують ставити трохи нижче герців монітора (напр. 237 для 240Hz).</div>
          <div class="lo-option-input-wrap" onclick="event.stopPropagation()">
            <select id="fpsmax-val" onchange="updateFpsMax()">
              <option value="0">0 (Без ліміту)</option>
              <option value="60">60</option>
              <option value="120">120</option>
              <option value="144">144</option>
              <option value="165">165</option>
              <option value="237">237 (для 240Hz)</option>
              <option value="240">240</option>
              <option value="360">360</option>
              <option value="400">400</option>
              <option value="500">500</option>
            </select>
            <span class="lo-input-hint">FPS</span>
          </div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Контроль FPS · Працює на всіх ПК</span>
          </div>
        </div>
      </div>

      <!-- Refresh rate -->
      <div class="lo-option-row" id="opt-freq" onclick="toggleOption('freq')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd" id="freq-cmd-preview">-freq 144</span>
          </div>
          <div class="lo-option-name">Частота оновлення монітора</div>
          <div class="lo-option-desc">Примусово встановлює refresh rate. Якщо CS2 не підхоплює правильну частоту автоматично — пропис вручну. Встанови рівно те що підтримує твій монітор.</div>
          <div class="lo-option-input-wrap" onclick="event.stopPropagation()">
            <select id="freq-val" onchange="updateFreq()">
              <option value="60">60 Hz</option>
              <option value="75">75 Hz</option>
              <option value="120">120 Hz</option>
              <option value="144" selected>144 Hz</option>
              <option value="165">165 Hz</option>
              <option value="240">240 Hz</option>
              <option value="280">280 Hz</option>
              <option value="360">360 Hz</option>
            </select>
          </div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">Тільки якщо CS2 не бере авто</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── CATEGORY: Display ── -->
  <div class="lo-category reveal">
    <div class="lo-section-lbl">🖥️ Графіка & Дисплей</div>
    <div class="lo-options-grid">

      <div class="lo-option-row" id="opt-vulkan" onclick="toggleOption('vulkan')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-vulkan</span>
            <span class="lo-option-badge badge-caution">Тест</span>
          </div>
          <div class="lo-option-name">Vulkan замість DirectX 11</div>
          <div class="lo-option-desc">Перемикає CS2 з DX11 на Vulkan. Для деяких GPU дає значний приріст FPS, для інших — навпаки. Рекомендовано протестувати, особливо на AMD.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FB923C"></div>
            <span class="lo-impact-text">Залежить від GPU · Обов'язково тестуй</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-r_dynamic" onclick="toggleOption('r_dynamic')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+r_dynamic 0</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Вимкнути динамічне освітлення</div>
          <div class="lo-option-desc">Прибирає динамічні джерела світла (спалахи, вибухи). Зменшує навантаження на GPU. Для слабкого ПК суттєвий приріст.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">+FPS на слабкому ПК · Менш красиво</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-limitvsconst" onclick="toggleOption('limitvsconst')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-limitvsconst</span>
            <span class="lo-option-badge badge-fps">FPS</span>
          </div>
          <div class="lo-option-name">Обмежити vertex shaders до 256</div>
          <div class="lo-option-desc">Зменшує кількість vertex shader constants. Може знизити навантаження на старіших GPU. Ефект дуже індивідуальний.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FACC15"></div>
            <span class="lo-impact-text">Для старих GPU · Тестуй</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── CATEGORY: Network ── -->
  <div class="lo-category reveal">
    <div class="lo-section-lbl">🌐 Мережа & Сервер</div>
    <div class="lo-options-grid">

      <div class="lo-option-row" id="opt-tickrate" onclick="toggleOption('tickrate')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-tickrate 128</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
            <span class="lo-option-badge badge-pro">Про-гравці</span>
          </div>
          <div class="lo-option-name">Tickrate 128 для локальних серверів</div>
          <div class="lo-option-desc">Встановлює 128-tick для offline/bot серверів та Workshop карт. На онлайн серверах (Valve MM, FACEIT) tickrate визначає сам сервер — ця команда не впливає. Корисно для тренування aim в офлайні.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Кращий aim training · Без ризику</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-cl_interp" onclick="toggleOption('cl_interp')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+cl_interp 0.031 +cl_interp_ratio 1</span>
            <span class="lo-option-badge badge-network">Мережа</span>
            <span class="lo-option-badge badge-pro">Про-гравці</span>
          </div>
          <div class="lo-option-name">Оптимізація інтерполяції</div>
          <div class="lo-option-desc">cl_interp 0.031 + ratio 1 — стандарт серед про-гравців для плавної реєстрації пострілів. Не використовуй interp 0 онлайн — буде тремтіння прицілу.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#42a5f5"></div>
            <span class="lo-impact-text">Плавніша реєстрація · Онлайн гра</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-rate" onclick="toggleOption('rate')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+rate 786432</span>
            <span class="lo-option-badge badge-network">Мережа</span>
          </div>
          <div class="lo-option-name">Максимальна швидкість з'єднання</div>
          <div class="lo-option-desc">786432 — максимальне значення rate в CS2. Дозволяє грі використовувати повну пропускну здатність. Рекомендовано якщо інтернет стабільний 10+ Mbps.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#42a5f5"></div>
            <span class="lo-impact-text">Стабільніший netcode · Швидкий інтернет</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-cmdrate" onclick="toggleOption('cmdrate')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">+cl_cmdrate 128 +cl_updaterate 128</span>
            <span class="lo-option-badge badge-network">Мережа</span>
          </div>
          <div class="lo-option-name">Частота команд та оновлень 128</div>
          <div class="lo-option-desc">Збільшує кількість пакетів до/від сервера до 128 на секунду. Корисно на 128-tick серверах (FACEIT, власні). На офіційному Valve MM — мінімальний ефект.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#42a5f5"></div>
            <span class="lo-impact-text">FACEIT / 128-tick сервери</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── CATEGORY: Utility ── -->
  <div class="lo-category reveal">
    <div class="lo-section-lbl">🛠️ Зручність</div>
    <div class="lo-options-grid">

      <div class="lo-option-row" id="opt-console" onclick="toggleOption('console')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-console</span>
            <span class="lo-option-badge badge-recommended">Рекомендовано</span>
          </div>
          <div class="lo-option-name">Відкривати консоль при старті</div>
          <div class="lo-option-desc">Автоматично відкриває developer console при запуску. Зручно для перевірки autoexec, debug та налаштувань. Можна закрити клавішею ~.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#4ADE80"></div>
            <span class="lo-impact-text">Зручність · Без впливу на FPS</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-third_party" onclick="toggleOption('third_party')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-allow_third_party_software</span>
            <span class="lo-option-badge badge-pro">Стрімери · Про-гравці</span>
          </div>
          <div class="lo-option-name">Дозволити стороннє ПЗ</div>
          <div class="lo-option-desc">Потрібно для роботи OBS Game Capture, MSI Afterburner overlay, NVIDIA Shadowplay та інших overlay-утиліт. ZywOo використовує для запису скримів.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#CE93D8"></div>
            <span class="lo-impact-text">Стрімінг / Запис / Overlay</span>
          </div>
        </div>
      </div>

      <div class="lo-option-row" id="opt-autoconfig" onclick="toggleOption('autoconfig')">
        <div class="lo-checkbox">
          <svg class="lo-checkbox-tick" viewBox="0 0 10 10" fill="none"><polyline points="1.5 5 4 7.5 8.5 2" stroke="#000" stroke-width="1.8" stroke-linecap="round"/></svg>
        </div>
        <div class="lo-option-body">
          <div class="lo-option-top">
            <span class="lo-option-cmd">-autoconfig</span>
            <span class="lo-option-badge badge-caution">Відновлення</span>
          </div>
          <div class="lo-option-name">Скинути налаштування CS2</div>
          <div class="lo-option-desc">Відновлює заводські налаштування CS2. Використовуй тільки якщо гра крашиться або є конфліктні параметри. Після застосування — видали цей параметр.</div>
          <div class="lo-option-impact">
            <div class="lo-impact-dot" style="background:#FB923C"></div>
            <span class="lo-impact-text">⚠️ Скидає всі налаштування · Тимчасово</span>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Reset -->
  <button class="lo-reset-btn reveal" onclick="resetAll()">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
    Скинути всі вибори
  </button>

</div>

<script>
// ── Option definitions ────────────────────────────────────────────────────────
const OPTIONS = {
  novid:          { cmd: '-novid' },
  nojoy:          { cmd: '-nojoy' },
  high:           { cmd: '-high' },
  fullscreen:     { cmd: '-fullscreen' },
  forcenovsync:   { cmd: '-forcenovsync' },
  softparticles:  { cmd: '-softparticlesdefaultoff' },
  mat_queue:      { cmd: '+mat_queue_mode 2' },
  cl_forcepreload:{ cmd: '+cl_forcepreload 1' },
  fpsmax:         { cmd: () => `+fps_max ${document.getElementById('fpsmax-val').value}` },
  freq:           { cmd: () => `-freq ${document.getElementById('freq-val').value}` },
  vulkan:         { cmd: '-vulkan' },
  r_dynamic:      { cmd: '+r_dynamic 0' },
  limitvsconst:   { cmd: '-limitvsconst' },
  tickrate:       { cmd: '-tickrate 128' },
  cl_interp:      { cmd: '+cl_interp 0.031 +cl_interp_ratio 1' },
  rate:           { cmd: '+rate 786432' },
  cmdrate:        { cmd: '+cl_cmdrate 128 +cl_updaterate 128' },
  console:        { cmd: '-console' },
  third_party:    { cmd: '-allow_third_party_software' },
  autoconfig:     { cmd: '-autoconfig' },
};

const state = {};
let activePreset = null; // track which preset is active

function toggleOption(key) {
  state[key] = !state[key];
  const row = document.getElementById('opt-' + key);
  if (row) row.classList.toggle('active', !!state[key]);
  // If a preset was active, switching any option makes it custom
  markCustomIfNeeded();
  updateOutput();
}

function markCustomIfNeeded() {
  if (activePreset !== null && !activePreset.startsWith('pro_')) {
    // Deactivate all preset buttons and show custom indicator
    document.querySelectorAll('.lo-preset-btn').forEach(b => b.classList.remove('active'));
    const customBtn = document.getElementById('preset-custom-indicator');
    if (customBtn) { customBtn.style.display = ''; customBtn.classList.add('active'); }
    activePreset = null;
  }
}

function setOption(key, val) {
  state[key] = val;
  const row = document.getElementById('opt-' + key);
  if (row) row.classList.toggle('active', !!val);
}

function updateFpsMax() {
  const v = document.getElementById('fpsmax-val').value;
  document.getElementById('fpsmax-cmd-preview').textContent = `+fps_max ${v}`;
  markCustomIfNeeded();
  updateOutput();
}

function updateFreq() {
  const v = document.getElementById('freq-val').value;
  document.getElementById('freq-cmd-preview').textContent = `-freq ${v}`;
  markCustomIfNeeded();
  updateOutput();
}

function updateOutput() {
  const parts = [];
  for (const [key, on] of Object.entries(state)) {
    if (!on) continue;
    const def = OPTIONS[key];
    if (!def) continue;
    const cmd = typeof def.cmd === 'function' ? def.cmd() : def.cmd;
    parts.push(cmd);
  }
  const result = parts.join(' ');
  document.getElementById('loOutput').textContent = result;
  document.getElementById('loCharCount').textContent = result.length + ' символів';
}

// ── Presets ───────────────────────────────────────────────────────────────────
const PRESETS = {
  minimal: {
    novid: true, nojoy: true, console: true, forcenovsync: true,
    tickrate: true, fpsmax: true,
  },
  competitive: {
    novid: true, nojoy: true, forcenovsync: true, fullscreen: true,
    fpsmax: true, tickrate: true, cl_interp: true, rate: true,
    cl_forcepreload: true, mat_queue: true, console: true,
  },
  lowend: {
    novid: true, nojoy: true, high: true, forcenovsync: true,
    softparticles: true, r_dynamic: true, limitvsconst: true,
    cl_forcepreload: true, fpsmax: true,
  },
  highend: {
    novid: true, nojoy: true, forcenovsync: true, fullscreen: true,
    fpsmax: true, tickrate: true, mat_queue: true, cl_interp: true,
    rate: true, cmdrate: true, cl_forcepreload: true,
  },
  streamer: {
    novid: true, nojoy: true, forcenovsync: true, fullscreen: true,
    fpsmax: true, tickrate: true, third_party: true, console: true,
  },
  reset: {},
};

function applyPreset(name) {
  // Clear active state on all preset buttons
  document.querySelectorAll('.lo-preset-btn').forEach(b => b.classList.remove('active'));
  const customBtn = document.getElementById('preset-custom-indicator');
  if (customBtn) { customBtn.style.display = 'none'; customBtn.classList.remove('active'); }
  event.currentTarget.classList.add('active');
  activePreset = name;

  // Reset all
  Object.keys(OPTIONS).forEach(k => { state[k] = false; });
  document.querySelectorAll('.lo-option-row').forEach(r => r.classList.remove('active'));

  // Apply preset
  const preset = PRESETS[name] || {};
  Object.entries(preset).forEach(([k, v]) => setOption(k, v));

  updateOutput();
}

// ── Pro configs ───────────────────────────────────────────────────────────────
const PRO_CONFIGS = {
  zywoo:  { novid:true, tickrate:true, third_party:true, fpsmax:true },
  niko:   { novid:true, tickrate:true, console:true, freq:true },
  frozen: { freq:true, rate:true, fpsmax:true, tickrate:true },
  f0rest: { novid:true, high:true, freq:true, fpsmax:true, third_party:true },
};

const PRO_FREQ = { zywoo: '240', niko: '240', frozen: '360', f0rest: '240' };
const PRO_FPS  = { zywoo: '400', niko: '0',   frozen: '500', f0rest: '0'  };

function applyProConfig(name) {
  Object.keys(OPTIONS).forEach(k => { state[k] = false; });
  document.querySelectorAll('.lo-option-row').forEach(r => r.classList.remove('active'));
  document.querySelectorAll('.lo-preset-btn').forEach(b => b.classList.remove('active'));
  activePreset = 'pro_' + name;

  const cfg = PRO_CONFIGS[name] || {};
  if (PRO_FREQ[name]) { document.getElementById('freq-val').value = PRO_FREQ[name]; document.getElementById('freq-cmd-preview').textContent = `-freq ${PRO_FREQ[name]}`; }
  if (PRO_FPS[name] !== undefined) { document.getElementById('fpsmax-val').value = PRO_FPS[name]; document.getElementById('fpsmax-cmd-preview').textContent = `+fps_max ${PRO_FPS[name]}`; }

  Object.entries(cfg).forEach(([k, v]) => setOption(k, v));
  updateOutput();
  showToast(`Конфіг ${name} застосовано ✓`);
}

// ── Copy ──────────────────────────────────────────────────────────────────────
function copyOutput() {
  const text = document.getElementById('loOutput').textContent;
  if (!text) { showToast('Нічого копіювати — обери параметри'); return; }
  const btn = document.getElementById('loCopyBtn');
  const orig = btn.innerHTML;
  navigator.clipboard ? navigator.clipboard.writeText(text).then(ok).catch(fallback) : fallback();
  function fallback() {
    const ta = document.createElement('textarea');
    ta.value = text; ta.style.cssText='position:fixed;opacity:0'; document.body.appendChild(ta);
    ta.select(); try{document.execCommand('copy')}catch(e){} document.body.removeChild(ta); ok();
  }
  function ok() {
    btn.innerHTML = '✓ Скопійовано';
    showToast('Launch Options скопійовано в буфер!');
    setTimeout(() => btn.innerHTML = orig, 2500);
  }
}

function resetAll() {
  Object.keys(OPTIONS).forEach(k => { state[k] = false; });
  document.querySelectorAll('.lo-option-row').forEach(r => r.classList.remove('active'));
  document.querySelectorAll('.lo-preset-btn').forEach(b => b.classList.remove('active'));
  const customBtn = document.getElementById('preset-custom-indicator');
  if (customBtn) { customBtn.style.display = 'none'; customBtn.classList.remove('active'); }
  activePreset = null;
  updateOutput();
}

// ── Toast ─────────────────────────────────────────────────────────────────────
function showToast(msg) {
  let t = document.getElementById('lo-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'lo-toast';
    t.style.cssText = `
      position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px);
      background:#1a1b23; border:1px solid rgba(240,196,48,.3);
      color:var(--accent); font-family:'Manrope',sans-serif; font-size:13px; font-weight:700;
      padding:10px 20px; border-radius:10px; z-index:9999;
      opacity:0; transition:opacity .2s, transform .2s; pointer-events:none;
      white-space:nowrap;
    `;
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(10px)';
  }, 2200);
}

// ── Scroll reveal ─────────────────────────────────────────────────────────────
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

// Init — apply minimal preset by default
(function() {
  // Find the minimal button and simulate click properly
  const minBtn = document.querySelector('.lo-preset-btn[onclick*="minimal"]');
  if (minBtn) {
    // Apply without triggering event-based active preset button logic
    Object.keys(OPTIONS).forEach(k => { state[k] = false; });
    document.querySelectorAll('.lo-option-row').forEach(r => r.classList.remove('active'));
    const preset = PRESETS['minimal'] || {};
    Object.entries(preset).forEach(([k, v]) => setOption(k, v));
    activePreset = 'minimal';
    minBtn.classList.add('active');
    updateOutput();
  }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
