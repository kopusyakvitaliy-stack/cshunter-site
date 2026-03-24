
// ===== MOBILE SIDEBAR =====
function toggleSidebar() {
  const open = document.body.classList.toggle('sidebar-open');
  const btn = document.getElementById('burgerBtn');
  if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  document.body.style.overflow = open ? 'hidden' : '';
}

function closeSidebar() {
  document.body.classList.remove('sidebar-open');
  const btn = document.getElementById('burgerBtn');
  if (btn) btn.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

// Close on nav-item click (mobile UX)
document.addEventListener('DOMContentLoaded', function () {
  if (window.innerWidth <= 768) {
    document.querySelectorAll('.nav-item').forEach(function (item) {
      item.addEventListener('click', closeSidebar);
    });
  }
  // Close on resize to desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 768) closeSidebar();
  });
});

/* CSHunter main.js */

// ===== CSRF =====
// Автоматично додає X-CSRF-Token до всіх POST fetch запитів
(function() {
  // Беремо токен з JS змінної (надійніше) або fallback до meta тегу
  var csrfToken = window.__CSRF
    || (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content')
    || '';
  if (!csrfToken) return;

  var _fetch = window.fetch;
  window.fetch = function(url, opts) {
    opts = opts || {};
    if (opts.method && opts.method.toUpperCase() === 'POST') {
      if (opts.headers instanceof Headers) {
        opts.headers.set('X-CSRF-Token', csrfToken);
      } else {
        opts.headers = Object.assign({}, opts.headers || {}, { 'X-CSRF-Token': csrfToken });
      }
    }
    return _fetch.call(window, url, opts);
  };
})();

// ===== TOAST =====
function showToast(msg, duration) {
  duration = duration || 3000;
  var t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(function(){ t.classList.remove('show'); }, duration);
}

// ===== HELPERS =====
function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function faceitColor(lvl) {
  if (lvl >= 10) return '#FF0000';
  if (lvl >= 9)  return '#FF3D00';
  if (lvl >= 7)  return '#FF6D00';
  if (lvl >= 5)  return '#FF9100';
  if (lvl >= 3)  return '#FFD600';
  return '#8BC34A';
}

// ===== CONNECT MODAL =====
function openConnectModal(name, ip, port) {
  var existing = document.getElementById('connectModal');
  if (existing) existing.remove();
  var address = ip + ':' + port;

  var modal = document.createElement('div');
  modal.className = 'modal-bg';
  modal.id = 'connectModal';
  modal.innerHTML =
    '<div class="modal" style="width:420px;max-width:95vw">' +
      '<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:4px">' +
        '<div>' +
          '<div class="modal-title">Підключення</div>' +
          '<div style="font-size:13px;color:var(--text-3);margin-bottom:8px">' + escHtml(name) + '</div>' +
        '</div>' +
        '<button class="btn-secondary" style="padding:5px 10px;font-size:13px;flex-shrink:0" onclick="closeModal()">✕</button>' +
      '</div>' +

      '<div class="connect-address">' + escHtml(address) + '</div>' +

      '<div style="display:flex;gap:14px;margin-bottom:14px;font-size:13px;color:var(--text-2);font-weight:600">' +
        '<span id="modalMap">🗺 ...</span>' +
        '<span id="modalCount">👥 ...</span>' +
      '</div>' +

      '<div style="margin-bottom:16px">' +
        '<div style="font-size:11px;text-transform:uppercase;letter-spacing:1.5px;color:var(--text-3);font-weight:700;margin-bottom:8px">Гравці на сервері</div>' +
        '<div id="playerList" style="max-height:210px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;padding-right:3px">' +
          '<div style="text-align:center;padding:16px;color:var(--text-3);font-size:13px">⏳ Завантаження...</div>' +
        '</div>' +
      '</div>' +

      '<div class="modal-btns">' +
        '<a class="btn-primary" href="steam://connect/' + escHtml(address) + '">🚀 Підключитись</a>' +
        '<button class="btn-secondary" onclick="copyAddress(\'' + escHtml(address) + '\')">Копіювати IP</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(modal);
  requestAnimationFrame(function(){ modal.classList.add('open'); });
  modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });

  loadModalData(ip, port);
}

function loadModalData(ip, port) {
  fetch('/api/server_players.php?ip=' + ip + '&port=' + port)
    .then(function(r){ return r.json(); })
    .then(function(d) {
      var mapEl   = document.getElementById('modalMap');
      var countEl = document.getElementById('modalCount');
      var listEl  = document.getElementById('playerList');
      if (!listEl) return;

      if (mapEl)   mapEl.textContent = '🗺 ' + (d.map || 'unknown');

      var humans = Math.max(0, (d.count || 0) - (d.bots || 0));
      var max    = d.max || 32;
      if (countEl) countEl.innerHTML =
        '<span style="color:var(--green)">⬤</span> ' + humans + ' / ' + max + ' гравців';

      listEl.innerHTML = '';

      if (!d.count || d.count === 0) {
        listEl.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-3);font-size:13px">Сервер порожній — приєднуйся першим!</div>';
        return;
      }

      // Real player data (available after plugin install)
      if (d.players && d.players.length > 0) {
        d.players.slice(0, 20).forEach(function(p){
          listEl.appendChild(buildPlayerRow(p));
        });
      } else {
        // Placeholder slots
        for (var i = 0; i < Math.min(humans, 20); i++) {
          listEl.appendChild(buildPlaceholder(i + 1));
        }
        if (humans > 0) {
          var note = document.createElement('div');
          note.style.cssText = 'font-size:11px;color:var(--text-3);text-align:center;padding:8px 0 2px;font-weight:600';
          note.textContent = 'Імена гравців — після встановлення плагіна';
          listEl.appendChild(note);
        }
      }

      if (d.bots > 0) {
        var botRow = document.createElement('div');
        botRow.style.cssText = 'display:flex;align-items:center;gap:10px;padding:7px 10px;background:rgba(255,255,255,.03);border-radius:7px;color:var(--text-3);font-size:12px;font-weight:600';
        botRow.innerHTML = '<span style="font-size:16px">🤖</span> ' + d.bots + ' бот' + (d.bots > 1 ? 'и' : '');
        listEl.appendChild(botRow);
      }
    })
    .catch(function(){
      var listEl = document.getElementById('playerList');
      if (listEl) listEl.innerHTML = '<div style="text-align:center;padding:16px;color:var(--red);font-size:13px">Не вдалось завантажити дані сервера</div>';
    });
}

function buildPlayerRow(p) {
  var row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:7px 10px;background:var(--surface);border:1px solid var(--border);border-radius:7px;flex-shrink:0';
  row.innerHTML =
    '<img src="' + escHtml(p.avatar||'') + '" alt="" style="width:30px;height:30px;border-radius:6px;background:var(--surface-2);object-fit:cover;flex-shrink:0" onerror="this.style.display=\'none\'">' +
    '<span style="flex:1;font-size:13px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(p.name||'Гравець') + '</span>' +
    (p.faceit_level ? '<span style="background:' + faceitColor(p.faceit_level) + ';color:#000;font-size:10px;font-weight:900;padding:2px 7px;border-radius:4px;flex-shrink:0">LVL ' + p.faceit_level + '</span>' : '');
  return row;
}

function buildPlaceholder(n) {
  var row = document.createElement('div');
  row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:7px 10px;background:rgba(255,255,255,.03);border-radius:7px;flex-shrink:0';
  row.innerHTML =
    '<div style="width:30px;height:30px;border-radius:6px;background:var(--surface-2);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:11px;color:var(--text-3);font-weight:700">' + n + '</div>' +
    '<div style="flex:1;height:10px;background:var(--surface-2);border-radius:4px;"></div>';
  return row;
}

function closeModal() {
  var m = document.getElementById('connectModal');
  if (m) { m.classList.remove('open'); setTimeout(function(){ m.remove(); }, 300); }
}

function copyAddress(addr) {
  navigator.clipboard.writeText(addr).then(function(){
    showToast('✅ IP скопійовано: ' + addr);
    closeModal();
  });
}

// ===== SERVERS ALL — єдиний fetch для всього сайту =====
// Замість N запитів (по одному на сервер) — 1 запит кожні 60с.
// Дані беруться з JSON-кешу на диску (без SQL), миттєво.

var _serversCache  = {};   // { "ip:port": { online, players, max_players, map, ping } }
var _pollInterval  = null;
var _pollCallbacks = [];   // функції які треба викликати після кожного оновлення

function registerPollCallback(fn) {
  _pollCallbacks.push(fn);
}

function fetchAllServers(onDone) {
  fetch('/api/servers_all.php', { cache: 'no-store' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (data && data.servers) _serversCache = data.servers;
      _pollCallbacks.forEach(function(fn) { try { fn(_serversCache); } catch(e){} });
      if (onDone) onDone(_serversCache);
    })
    .catch(function() {
      if (onDone) onDone(_serversCache);
    });
}

function getServerData(ip, port) {
  return _serversCache[ip + ':' + port] || null;
}

function startServerPolling(intervalMs) {
  intervalMs = intervalMs || 60000; // раз на хвилину — крон теж раз на хвилину
  if (_pollInterval) clearInterval(_pollInterval);
  fetchAllServers(); // одразу при старті
  _pollInterval = setInterval(function() { fetchAllServers(); }, intervalMs);
}

// ===== LIVE SERVER CARDS (mode.php) =====
function initLiveServers() {
  var cards = document.querySelectorAll('[data-server-ip]');
  if (!cards.length) return;

  function applyToCards(cache) {
    var grandTotal = 0;
    cards.forEach(function(card) {
      var ip   = card.dataset.serverIp;
      var port = card.dataset.serverPort;
      var d    = cache[ip + ':' + port];
      if (!d) return;

      var dot = card.querySelector('.server-status-dot');
      if (dot) dot.className = 'server-status-dot' + (d.online ? '' : ' offline');

      var pe = card.querySelector('.server-players');
      if (pe) pe.textContent = d.online ? (d.players || 0) + ' / ' + (d.max_players || '?') : 'Офлайн';

      var bar = card.querySelector('.players-bar-fill');
      if (bar && d.max_players > 0)
        bar.style.width = Math.round(((d.players || 0) / d.max_players) * 100) + '%';

      var me = card.querySelector('.server-map');
      if (me && d.map) me.textContent = '🗺 ' + d.map;

      if (d.online) grandTotal += (d.players || 0);
    });
    document.querySelectorAll('.hero-online-live, .hero-online-text').forEach(function(el) {
      el.textContent = grandTotal;
    });
  }

  registerPollCallback(applyToCards);
  startServerPolling(60000);
}

// ===== INDEX PAGE LIVE (mode tiles) =====
function initIndexLive() {
  var tiles  = document.querySelectorAll('.mode-tile[data-servers]');
  if (!tiles.length) return;

  var statEl = document.getElementById('stat-online');

  function applyToTiles(cache) {
    var grandTotal = 0;
    tiles.forEach(function(tile) {
      var servers  = JSON.parse(tile.dataset.servers || '[]');
      var slug     = tile.dataset.mode;
      var modeTotal = 0;
      servers.forEach(function(srv) {
        var d = cache[srv.ip + ':' + srv.port];
        if (d && d.online) modeTotal += (d.players || 0);
      });
      grandTotal += modeTotal;
      var el = document.querySelector('.mode-online[data-mode="' + slug + '"]');
      if (el) el.textContent = modeTotal;
    });
    if (statEl) statEl.textContent = grandTotal;
  }

  registerPollCallback(applyToTiles);
  startServerPolling(60000);
}

// ===== PLAYER BARS =====
function animatePlayerBars() {
  document.querySelectorAll('.players-bar-fill').forEach(function(bar) {
    var pct = bar.dataset.pct || '0';
    setTimeout(function(){ bar.style.width = pct + '%'; }, 200);
  });
}

// ===== SEARCH =====
function initSearch() {
  var input = document.getElementById('serverSearch');
  if (!input) return;
  input.addEventListener('input', function() {
    var q = input.value.toLowerCase().trim();
    document.querySelectorAll('.server-card, .mode-card').forEach(function(card) {
      card.style.display = (!q || card.textContent.toLowerCase().includes(q)) ? '' : 'none';
    });
  });
}

// ===== COUNTERS =====
function animateCounter(el, target) {
  var cur = 0;
  var step = Math.max(1, Math.ceil(target / 40));
  var timer = setInterval(function() {
    cur = Math.min(cur + step, target);
    el.textContent = cur.toLocaleString();
    if (cur >= target) clearInterval(timer);
  }, 30);
}

function initCounters() {
  document.querySelectorAll('[data-count]').forEach(function(el) {
    animateCounter(el, parseInt(el.dataset.count, 10));
  });
}

// ===== SKIN TABS =====
function initSkinTabs() {
  document.querySelectorAll('.skin-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.skin-tab').forEach(function(t){ t.classList.remove('active'); });
      tab.classList.add('active');
      var cat = tab.dataset.cat;
      document.querySelectorAll('.skin-card').forEach(function(card) {
        card.style.display = (!cat || cat === 'all' || card.dataset.cat === cat) ? '' : 'none';
      });
    });
  });
}

// ===== ESC =====
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeModal();
});

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
  animatePlayerBars();
  initSearch();
  initCounters();
  initSkinTabs();
  initLiveServers();
  initIndexLive();
});
