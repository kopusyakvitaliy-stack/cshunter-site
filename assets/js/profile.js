/* ============================================================
   CSHunter — profile.js
   Всі PHP-дані передаються через window.__P (інжектується inline)
   ============================================================ */

(function () {
    'use strict';

    const P = window.__P || {};

    /* ── Helpers ──────────────────────────────────────────────────────────── */
    function esc(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Tab switcher ─────────────────────────────────────────────────────── */
    const TABS = ['profile', 'friends', 'skinchanger', 'items'];
    let _scLoaded = false;

    window.switchProfileTab = function (tab, btn, pushState) {
        if (!TABS.includes(tab)) tab = 'profile';
        document.querySelectorAll('.profile-tab').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.profile-tab-panel').forEach(p => p.classList.remove('active'));
        const activeBtn = btn || document.querySelector('.profile-tab[data-tab="' + tab + '"]');
        if (activeBtn) activeBtn.classList.add('active');
        const panel = document.getElementById('tab-panel-' + tab);
        if (panel) panel.classList.add('active');
        if (tab === 'skinchanger' && !_scLoaded) {
            _scLoaded = true;
            setTimeout(() => { if (typeof scSetTeam === 'function') scSetTeam(window._scCurrentTeam || 3); }, 50);
        }


        if (pushState !== false) {
            const newUrl = tab === 'profile'
                ? '/profile/' + P.steamId
                : '/profile/' + P.steamId + '/' + tab;
            history.pushState({ tab }, '', newUrl);
            document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-item').forEach(el => {
                const href = el.getAttribute('href') || '';
                if (tab === 'skinchanger' && href.includes('/skinchanger')) el.classList.add('active');
                else if (tab !== 'skinchanger' && href.includes(P.steamId) && !href.includes('/skinchanger')) el.classList.add('active');
            });
        }
    };

    // Restore tab from URL on load
    (function () {
        const parts = location.pathname.split('/');
        const last = parts[parts.length - 1];
        const initTab = TABS.includes(last) ? last : 'profile';
        switchProfileTab(initTab, null, false);




})();

    window.addEventListener('popstate', function (e) {
        switchProfileTab((e.state && e.state.tab) || 'profile', null, false);
    });

    /* ── Count-up animation ───────────────────────────────────────────────── */
    (function () {
        function run(el) {
            const t = parseFloat(el.dataset.target) || 0, dur = 900, s = performance.now();
            (function tick(n) {
                const p = Math.min((n - s) / dur, 1);
                el.textContent = Math.round(t * (1 - Math.pow(1 - p, 3)));
                if (p < 1) requestAnimationFrame(tick);
            })(performance.now());
        }
        const obs = new IntersectionObserver(es => es.forEach(e => {
            if (e.isIntersecting) { run(e.target); obs.unobserve(e.target); }
        }), { threshold: .3 });
        document.querySelectorAll('.count-up').forEach(el => obs.observe(el));
    })();

    /* ── Copy profile link ────────────────────────────────────────────────── */
    window.copyProfileLink = function () {
        const url = P.profileUrl;
        const btn = document.getElementById('copyLinkBtn');
        if (!btn) return;
        const orig = btn.innerHTML;
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject())
            .catch(() => {
                const t = document.createElement('textarea');
                t.value = url; document.body.appendChild(t); t.select();
                document.execCommand('copy'); t.remove();
            });
        btn.innerHTML = '✓ Скопійовано!';
        btn.style.color = 'var(--accent)';
        btn.style.borderColor = 'rgba(240,196,48,.3)';
        setTimeout(() => { btn.innerHTML = orig; btn.style.color = ''; btn.style.borderColor = ''; }, 2000);
    };

    /* ── Async friends loader ─────────────────────────────────────────────── */
    (function () {
        const LOGO_URL = P.siteUrl + '/assets/logo.png';

        function renderFriends(data) {
            const container = document.getElementById('friendsContainer');
            const filterWrap = document.getElementById('friendsFilterWrap');
            const counts = data.counts;
            if (!data.friends.length) {
                container.innerHTML = `<div class="notice-box">
                    <div style="font-size:36px;margin-bottom:10px">🔒</div>
                    <div style="font-weight:700;color:var(--text-2);margin-bottom:6px">Список друзів закритий або порожній</div>
                    <div>Профіль або список друзів закриті в Steam.</div>
                </div>`;
                return;
            }
            // Тогли — онлайн і зареєстровані
            let toggleOnlineActive = false;
            let toggleRegActive = false;

            function buildToggles() {
                let t = `<span class="friends-filter-count">${counts.all} друзів</span>`;
                t += `<button class="friends-switch ${toggleOnlineActive ? 'active' : ''}" id="toggleOnline" onclick="toggleFriendFilter('online')">
                    <span class="friends-switch-dot"></span>
                    Онлайн
                    <span class="friends-switch-count">${counts.online}</span>
                </button>`;
                t += `<button class="friends-switch ${toggleRegActive ? 'active' : ''}" id="toggleReg" onclick="toggleFriendFilter('registered')">
                    <img src="${P.siteUrl}/assets/logo.png" class="friends-switch-logo" alt="">
                    Зареєстровані
                    <span class="friends-switch-count">${counts.registered}</span>
                </button>`;
                document.getElementById('friendsFilter').innerHTML = t;
            }

            window.toggleFriendFilter = function(type) {
                if (type === 'online') {
                    toggleOnlineActive = !toggleOnlineActive;
                    if (toggleOnlineActive) toggleRegActive = false;
                } else if (type === 'registered') {
                    toggleRegActive = !toggleRegActive;
                    if (toggleRegActive) toggleOnlineActive = false;
                }
                buildToggles();
                applyFriendToggles();
            };

            function applyFriendToggles() {
                document.querySelectorAll('.friend-card').forEach(c => {
                    let show = true;
                    if (toggleOnlineActive && c.dataset.status !== 'online') show = false;
                    if (toggleRegActive && c.dataset.registered !== '1') show = false;
                    c.style.display = show ? '' : 'none';
                });
                // Hide "X more offline" label when any filter is active
                const hiddenLabel = document.querySelector('.friends-hidden-label');
                if (hiddenLabel) hiddenLabel.style.display = (toggleOnlineActive || toggleRegActive) ? 'none' : '';

                // Ховаємо групи і оновлюємо заголовки
                document.querySelectorAll('.friends-group').forEach(g => {
                    const visibleCards = [...g.querySelectorAll('.friend-card')].filter(c => c.style.display !== 'none');
                    const visible = visibleCards.length > 0;
                    g.style.display = visible ? '' : 'none';
                    const label = g.previousElementSibling;
                    if (label && label.classList.contains('friends-label')) {
                        label.style.display = visible ? '' : 'none';
                        // Оновлюємо лічильник в заголовку
                        const isOnlineGroup = label.textContent.includes('Онлайн');
                        const dash = label.querySelector('.friends-label-count');
                        if (dash) dash.textContent = visibleCards.length;
                    }
                });
            }

            buildToggles();
            filterWrap.style.display = '';

            let html = '';
            const online  = data.friends.filter(f => f.status !== 'offline');
            const offline = data.friends.filter(f => f.status === 'offline');

            if (online.length) {
                html += `<div class="friends-label"><span style="display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);box-shadow:0 0 5px var(--green);flex-shrink:0"></span> Онлайн — <span class="friends-label-count">${counts.online}</span></div>`;
                html += `<div class="friends-group friends-grid">`;
                online.forEach(f => { html += friendCardHtml(f); });
                html += `</div>`;
            }
            if (offline.length) {
                html += `<div class="friends-label" style="margin-top:4px">Офлайн — <span class="friends-label-count">${counts.offline}</span></div>`;
                html += `<div class="friends-group friends-grid">`;
                offline.forEach(f => { html += friendCardHtml(f); });
                if (counts.offline_hidden > 0)
                    html += `<div class="friends-hidden-label" style="grid-column:1/-1;text-align:center;color:var(--text-3);font-size:11px;padding:8px 0">+ ще ${counts.offline_hidden} офлайн</div>`;
                html += `</div>`;
            }
            const sideCount = document.getElementById('friendCountSide');
            if (sideCount) sideCount.textContent = counts.all || '—';
            const listDiv = document.createElement('div');
            listDiv.id = 'friendsList';
            listDiv.innerHTML = html;
            container.innerHTML = '';
            container.appendChild(listDiv);

            // Update tab badge
            const badge = document.getElementById('friendsTabBadge');
            if (badge && counts.all > 0) { badge.textContent = counts.all; badge.style.display = ''; }
        }

        function friendCardHtml(f) {
            const sc = f.status;
            const isOffline = sc === 'offline';
            const isIngame  = sc === 'ingame';
            const avatarStyle = isOffline ? 'filter:grayscale(50%);opacity:.6' : '';
            const shape = f.frame_shape || 'rounded';
            const avatarRadius = shape === 'square' ? '3px' : '10px';

            const frameSize = shape === 'rounded' ? 79 : 72;
            const frameHtml = (f.frame_img_sm || f.frame_img)
                ? `<img src="${esc(f.frame_img_sm || f.frame_img)}" class="friend-avatar-frame-img" style="width:${frameSize}px;height:${frameSize}px" alt="" draggable="false">`
                : '';

            // CSH логотип перед ніком
            const regLogo = f.registered
                ? `<img src="${LOGO_URL}" alt="" style="width:13px;height:13px;object-fit:contain;opacity:.85;flex-shrink:0;position:relative;top:-1px">`
                : '';

            // Статус текст — для офлайн просто "Офлайн", для ingame — назва гри
            const statusLabel = isIngame
                ? `<span class="friend-state ingame">${esc(f.status_label)}</span>`
                : isOffline
                ? `<span class="friend-state offline">Офлайн</span>`
                : `<span class="friend-state online">Онлайн</span>`;

            return `<div class="friend-card" data-status="${sc === 'ingame' ? 'online' : sc}" data-registered="${f.registered ? '1' : '0'}" onclick="location.href='${f.profile_url}'">
                <div class="friend-avatar-wrap">
                    <img src="${esc(f.avatar)}" class="friend-avatar" alt=""
                         style="${avatarStyle ? avatarStyle+';' : ''}border-radius:${avatarRadius}"
                         onerror="this.onerror=null">
                    <span class="friend-status-dot ${sc}"></span>
                    ${frameHtml}
                </div>
                <div class="friend-info">
                    <div class="friend-name-row">
                        ${regLogo}
                        <span class="friend-name-text ${isOffline ? 'offline' : ''}">${esc(f.name)}</span>
                    </div>
                    <div class="friend-status-row">${statusLabel}</div>
                </div>
            </div>`;
        }

        fetch('/api/friends.php?steam_id=' + P.steamId)
            .then(r => r.json())
            .then(data => {
                if (data.ok) renderFriends(data);
                else document.getElementById('friendsContainer').innerHTML =
                    '<div class="notice-box"><div style="font-size:32px;margin-bottom:10px">😕</div><div style="font-weight:700;color:var(--text-2)">Не вдалось завантажити список друзів</div></div>';
            })
            .catch(() => {
                document.getElementById('friendsContainer').innerHTML =
                    '<div class="notice-box"><div style="font-size:32px;margin-bottom:10px">😕</div><div style="font-weight:700;color:var(--text-2)">Помилка завантаження</div></div>';
            });
    })();

    window.filterFriends = function (f, btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.friend-card').forEach(c => {
            let show = false;
            if (f === 'all') show = true;
            else if (f === 'online' || f === 'offline') show = c.dataset.status === f;
            else if (f === 'registered') show = c.dataset.registered === '1';
            c.style.display = show ? '' : 'none';
        });
        document.querySelectorAll('.friends-label').forEach(l => {
            if (f === 'all') { l.style.display = ''; return; }
            if (f === 'registered') { l.style.display = 'none'; return; }
            l.style.display = ((f === 'online' && l.textContent.includes('Онлайн')) ||
                               (f === 'offline' && l.textContent.includes('Офлайн'))) ? '' : 'none';
        });
    };

    /* ── Follow / Unfollow ────────────────────────────────────────────────── */
    if (!P.isOwn && P.loggedIn) {
        let following = P.isFollowing;
        window.doFollow = function () {
            const btn = document.getElementById('followBtn');
            btn.disabled = true;
            fetch('/api/follow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'csrf_token=' + encodeURIComponent(P.csrf)
                    + '&action=' + (following ? 'unfollow' : 'follow')
                    + '&steam_id=' + P.steamId
            })
            .then(r => r.json())
            .then(d => {
                if (!d.ok) { btn.disabled = false; return; }
                following = d.following;
                btn.className = 'btn-follow ' + (following ? 'remove' : 'add');
                btn.innerHTML = following
                    ? '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Підписаний'
                    : '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Підписатись';
                ['cntFollowers', 'cntFollowersSide'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = d.followers;
                });
                btn.disabled = false;
            })
            .catch(() => { btn.disabled = false; });
        };
    }

    /* ── Team modal (own only) ────────────────────────────────────────────── */
    if (P.isOwn) {
        let selectedTeamId = P.favTeamId;
        let currentOffset = 0, currentQuery = '', isLoadingMore = false, teamSearchTimer = null, _teamReqId = 0;
        let _searchListenerAdded = false;

        window.openTeamModal = function () {
            document.getElementById('teamModalBg').classList.add('open');
            const inp = document.getElementById('teamSearchInput');
            inp.value = '';
            loadTeams('');
            setTimeout(() => inp.focus(), 100);
            if (!_searchListenerAdded) {
                _searchListenerAdded = true;
                inp.addEventListener('input', function () {
                    clearTimeout(teamSearchTimer);
                    const val = this.value.trim();
                    teamSearchTimer = setTimeout(() => loadTeams(val), 300);
                });
            }
        };
        window.closeTeamModal = function () { document.getElementById('teamModalBg').classList.remove('open'); };

        function loadTeams(q) {
            currentQuery = q; currentOffset = 0;
            const reqId = ++_teamReqId;
            const grid = document.getElementById('teamModalGrid');
            grid.innerHTML = '<div class="team-modal-loading">Завантаження...</div>';
            removeLoadMoreBtn();
            fetch('/api/teams.php?action=search&q=' + encodeURIComponent(q) + '&offset=0')
                .then(r => r.json())
                .then(data => {
                    if (reqId !== _teamReqId) return;
                    if (!data.teams?.length) { grid.innerHTML = '<div class="team-modal-loading">Нічого не знайдено</div>'; return; }
                    grid.innerHTML = data.teams.map(t => teamItemHtml(t)).join('');
                    currentOffset = data.teams.length;
                    if (data.has_more) injectLoadMoreBtn(grid);
                })
                .catch(() => { if (reqId === _teamReqId) grid.innerHTML = '<div class="team-modal-loading">Помилка</div>'; });
        }

        function injectLoadMoreBtn(grid) {
            removeLoadMoreBtn();
            grid.insertAdjacentHTML('beforeend', '<div class="team-load-more" id="teamLoadMoreBtn" onclick="loadMoreTeams()" style="grid-column:1/-1"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>');
        }
        function removeLoadMoreBtn() { document.getElementById('teamLoadMoreBtn')?.remove(); }

        window.loadMoreTeams = function () {
            if (isLoadingMore) return;
            isLoadingMore = true;
            const btn = document.getElementById('teamLoadMoreBtn');
            if (btn) { btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:spin .8s linear infinite"><path d="M12 2a10 10 0 0 1 10 10"/></svg>'; btn.style.pointerEvents = 'none'; }
            fetch('/api/teams.php?action=search&q=' + encodeURIComponent(currentQuery) + '&offset=' + currentOffset)
                .then(r => r.json())
                .then(data => {
                    removeLoadMoreBtn();
                    const grid = document.getElementById('teamModalGrid');
                    data.teams?.forEach((t, i) => {
                        const tmp = document.createElement('div');
                        tmp.innerHTML = teamItemHtml(t, 'fadeIn');
                        const el = tmp.firstElementChild;
                        el.style.animationDelay = (i * 25) + 'ms';
                        grid.appendChild(el);
                    });
                    currentOffset += data.teams?.length || 0;
                    if (data.has_more) injectLoadMoreBtn(grid);
                    isLoadingMore = false;
                })
                .catch(() => { isLoadingMore = false; });
        };

        function teamItemHtml(t, extraClass = '') {
            const sel = t.id === selectedTeamId ? 'selected' : '';
            return `<div class="team-item ${sel} ${extraClass}" onclick="selectTeam(${t.id},'${esc(t.name)}','${esc(t.logo || '')}',this)">${t.logo
                ? `<img src="${esc(t.logo)}" alt="" loading="lazy" onerror="this.style.display='none'">`
                : `<img src="${P.siteUrl}/assets/no-team-logo.png" alt="" style="width:48px;height:48px;object-fit:contain;opacity:.4">`
            }<div class="team-item-name">${esc(t.name)}</div></div>`;
        }

        window.selectTeam = function (id, name, logo, el) {
            document.querySelectorAll('.team-item').forEach(i => i.classList.remove('selected'));
            el.classList.add('selected');
            selectedTeamId = id;
            const fd = new FormData();
            fd.append('action', 'save'); fd.append('team_id', id);
            fetch('/api/teams.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        updateHeroBadge(name, logo);
                        setTimeout(() => { const badge = document.querySelector('.fav-team-badge'); if (badge) launchTeamParticles(badge, logo); }, 80);
                        const rmBtn = document.getElementById('removTeamBtn');
                        if (rmBtn) {
                            rmBtn.style.display = 'flex';
                            const img = rmBtn.querySelector('img');
                            if (img && logo) img.src = logo;
                            else if (!img && logo) rmBtn.insertAdjacentHTML('afterbegin', `<img src="${esc(logo)}" style="width:20px;height:20px;object-fit:contain;border-radius:3px" onerror="this.style.display='none'">`);
                        }
                        closeTeamModal();
                    }
                });
        };

        window.removeTeam = function () {
            const fd = new FormData();
            fd.append('action', 'save'); fd.append('team_id', 0);
            fetch('/api/teams.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        selectedTeamId = null;
                        const badge = document.querySelector('.fav-team-badge');
                        if (badge) badge.outerHTML = '<button class="fav-team-add" onclick="openTeamModal()"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Вибрати улюблену команду</button>';
                        const rmBtn = document.getElementById('removTeamBtn');
                        if (rmBtn) rmBtn.style.display = 'none';
                        closeTeamModal();
                    }
                });
        };

        function launchTeamParticles(sourceEl, logoSrc) {
            if (!logoSrc) return;
            const logoImg = sourceEl.querySelector('img'), ref = logoImg || sourceEl, rect = ref.getBoundingClientRect();
            const cx = rect.left + rect.width / 2, cy = rect.top + rect.height / 2;
            const angles = [-70, -45, -20, 20, 45, 70];
            for (let i = 0; i < 6; i++) {
                const el = document.createElement('img'); el.src = logoSrc;
                const size  = 16 + Math.random() * 8;
                const angle = (angles[i] + (Math.random() - .5) * 15) * Math.PI / 180;
                const speed = 90 + Math.random() * 60;
                const vx = Math.sin(angle) * speed, vy = -Math.cos(angle) * speed * .6;
                const rot = Math.random() * 40 - 20, rotV = (Math.random() - .5) * 180;
                el.style.cssText = `position:fixed;left:${cx - size / 2}px;top:${cy - size / 2}px;width:${size}px;height:${size}px;object-fit:contain;pointer-events:none;z-index:9999;`;
                document.body.appendChild(el);
                const sx = cx - size / 2, sy = cy - size / 2, dur = 900 + Math.random() * 300, start = performance.now();
                (function (el, vx, vy, rot, rotV, sx, sy, dur, start) {
                    function frame(now) {
                        const t = Math.min((now - start) / dur, 1);
                        if (t >= 1) { el.remove(); return; }
                        el.style.left = (sx + vx * t) + 'px';
                        el.style.top  = (sy + vy * t + 120 * t * t) + 'px';
                        el.style.transform = `rotate(${rot + rotV * t}deg)`;
                        el.style.opacity = t < .5 ? 1 : 1 - (t - .5) * 2;
                        requestAnimationFrame(frame);
                    }
                    requestAnimationFrame(frame);
                })(el, vx, vy, rot, rotV, sx, sy, dur, start);
            }
        }

        function updateHeroBadge(name, logo) {
            const container = document.querySelector('.fav-team-badge, .fav-team-add');
            if (!container) return;
            const logoHtml = logo ? `<img src="${esc(logo)}" alt="" class="fav-team-logo" onerror="this.style.display='none'">` : '';
            container.outerHTML = `<div class="fav-team-badge" onclick="openTeamModal()" title="Змінити команду">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="#f44336" stroke="none" style="flex-shrink:0"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                ${logoHtml}<span class="fav-team-name">${esc(name)}</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity:.4;flex-shrink:0"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </div>`;
        }
    }

    /* ── Follow modal ─────────────────────────────────────────────────────── */
    let _followTab = 'followers', _followOffset = 0, _followLoading = false;

    window.openFollowModal = function (tab) {
        _followTab = tab || 'followers';
        document.getElementById('followModalBg').classList.add('open');
        _switchFollowTab(_followTab);
    };
    window.closeFollowModal = function () { document.getElementById('followModalBg').classList.remove('open'); };
    window.switchFollowTab  = function (tab) { _switchFollowTab(tab); };

    function _switchFollowTab(tab) {
        _followTab = tab; _followOffset = 0;
        document.getElementById('followUserList').innerHTML = _loadingHtml();
        document.getElementById('followModalTitle').textContent = tab === 'followers' ? 'Фоловери' : 'Підписки';
        ['followers', 'following'].forEach(t => {
            document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1))?.classList.toggle('active', t === tab);
        });
        _loadFollowPage();
    }

    function _loadingHtml() {
        return '<div class="follow-loading"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:followSpin .8s linear infinite"><path d="M12 2a10 10 0 0 1 10 10"/></svg> Завантаження...</div>';
    }

    function _loadFollowPage() {
        if (_followLoading) return;
        _followLoading = true;
        fetch('/api/followers.php?type=' + _followTab + '&steam_id=' + P.steamId + '&offset=' + _followOffset)
            .then(r => r.json())
            .then(data => {
                _followLoading = false;
                if (!data.ok) return;
                const list = document.getElementById('followUserList');
                list.querySelector('.follow-load-more')?.remove();
                if (_followOffset === 0 && !data.users.length) {
                    list.innerHTML = '<div class="follow-empty"><div class="follow-empty-icon">👥</div>' + (_followTab === 'followers' ? 'Ще немає фоловерів' : 'Ще немає підписок') + '</div>';
                    return;
                }
                if (_followOffset === 0) list.innerHTML = '';
                data.users.forEach(u => { list.insertAdjacentHTML('beforeend', _userItemHtml(u)); });
                _followOffset += data.users.length;
                if (data.has_more)
                    list.insertAdjacentHTML('beforeend', '<button class="follow-load-more" onclick="_loadFollowPagePublic()">Завантажити ще</button>');
            })
            .catch(() => { _followLoading = false; });
    }

    // Expose for inline onclick
    window._loadFollowPagePublic = function () { _loadFollowPage(); };

    function _userItemHtml(u) {
        const showUnfollow = P.isOwn && _followTab === 'following';
        const unfollowBtn = showUnfollow
            ? `<button class="follow-unfollow-btn" onclick="unfollowUser('${u.steam_id}', this)" title="Відписатись"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>`
            : '';
        const avatar = u.avatar_url
            ? `<img src="${esc(u.avatar_url)}" class="follow-user-avatar" alt="" onerror="this.onerror=null">`
            : `<div class="follow-user-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:900;color:var(--accent);font-family:Unbounded">${esc(u.steam_name.charAt(0).toUpperCase())}</div>`;
        return `<div class="follow-user-item" id="fui-${u.steam_id}">
            <a href="${esc(u.profile_url)}">${avatar}</a>
            <div class="follow-user-name"><a href="${esc(u.profile_url)}">${esc(u.steam_name)}</a></div>
            ${unfollowBtn}
        </div>`;
    }

    window.unfollowUser = function (steamId, btn) {
        btn.disabled = true;
        btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="animation:followSpin .6s linear infinite"><path d="M12 2a10 10 0 0 1 10 10"/></svg>';
        fetch('/api/followers.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'csrf_token=' + encodeURIComponent(P.csrf) + '&action=unfollow&steam_id=' + steamId
        })
        .then(r => r.json())
        .then(d => {
            if (!d.ok) { btn.disabled = false; return; }
            const item = document.getElementById('fui-' + steamId);
            if (item) { item.style.transition = 'all .25s'; item.style.opacity = '0'; item.style.transform = 'translateX(20px)'; setTimeout(() => item.remove(), 250); }
            const el = document.getElementById('cntFollowing');
            if (el) el.textContent = d.following_count;
            const tab = document.getElementById('tabCntFollowing');
            if (tab) tab.textContent = d.following_count;
        })
        .catch(() => { btn.disabled = false; });
    };

})();