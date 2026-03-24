/**
 * CSHunter Profile Particles — Canvas-based, GPU-friendly
 * 
 * - Нуль DOM-елементів — все на <canvas>
 * - requestAnimationFrame з автопаузою (Page Visibility API)
 * - Адаптивна кількість частинок (менше на мобільних)
 * - Respects prefers-reduced-motion
 * - Не вантажить сервер — 100% клієнтський
 * 
 * Використання: ProfileParticles.init('#profileHero')
 * Знищення:    ProfileParticles.destroy()
 */
;(function(window) {
    'use strict';

    var _canvas   = null;
    var _ctx      = null;
    var _raf      = null;
    var _particles = [];
    var _running   = false;
    var _paused    = false;
    var _container = null;
    var _resizeTimer = null;
    var _dpr      = 1;

    // ── Конфіг ────────────────────────────────────────────────────────────────
    var CONFIG = {
        // Кількість частинок на 100,000 px² площі (адаптивно)
        densityPer100k: 3,
        // Ліміти
        minCount: 8,
        maxCount: 45,       // навіть на 4K — не більше 45
        mobileMax: 18,      // на мобільних ≤ 18
        // Розміри частинок
        sizeMin: 1.2,
        sizeMax: 3.5,
        // Швидкість (px/frame при 60fps)
        speedMin: 0.15,
        speedMax: 0.6,
        // Час життя (в кадрах, ~60fps)
        lifespanMin: 180,   // ~3 сек
        lifespanMax: 480,   // ~8 сек
        // Кольори — золотистий акцент CSHunter
        colors: [
            { r: 240, g: 196, b: 48 },   // --accent gold
            { r: 255, g: 215, b: 80 },   // light gold
            { r: 200, g: 160, b: 40 },   // dark gold
            { r: 255, g: 255, b: 255 },  // white sparkle
        ],
        // Максимальна прозорість
        maxAlpha: 0.55,
        // Fade in/out (% від lifespan)
        fadePercent: 0.25,
    };

    // ── Утиліти ───────────────────────────────────────────────────────────────
    function rand(min, max) {
        return Math.random() * (max - min) + min;
    }

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function getCount(w, h) {
        var area = w * h;
        var count = Math.round(area / 100000 * CONFIG.densityPer100k);
        var max = isMobile() ? CONFIG.mobileMax : CONFIG.maxCount;
        return Math.max(CONFIG.minCount, Math.min(count, max));
    }

    // ── Частинка ──────────────────────────────────────────────────────────────
    function createParticle(w, h, forceBottom) {
        var color = CONFIG.colors[Math.floor(Math.random() * CONFIG.colors.length)];
        var lifespan = Math.round(rand(CONFIG.lifespanMin, CONFIG.lifespanMax));
        return {
            x: rand(0, w),
            y: forceBottom ? h + 5 : rand(0, h),
            size: rand(CONFIG.sizeMin, CONFIG.sizeMax),
            speedX: rand(-0.2, 0.2),
            speedY: -rand(CONFIG.speedMin, CONFIG.speedMax),
            life: forceBottom ? 0 : Math.floor(rand(0, lifespan)), // stagger initial
            lifespan: lifespan,
            r: color.r,
            g: color.g,
            b: color.b,
            shimmer: rand(0.02, 0.05), // швидкість мерехтіння
            shimmerOffset: rand(0, Math.PI * 2),
        };
    }

    // ── Resize ────────────────────────────────────────────────────────────────
    function resize() {
        if (!_container || !_canvas) return;

        var rect = _container.getBoundingClientRect();
        var w = Math.round(rect.width);
        var h = Math.round(rect.height);

        _dpr = Math.min(window.devicePixelRatio || 1, 2); // cap at 2x

        _canvas.width  = w * _dpr;
        _canvas.height = h * _dpr;
        _canvas.style.width  = w + 'px';
        _canvas.style.height = h + 'px';

        _ctx.setTransform(_dpr, 0, 0, _dpr, 0, 0);

        // Перегенерувати якщо різко змінилась кількість
        var target = getCount(w, h);
        while (_particles.length < target) {
            _particles.push(createParticle(w, h, false));
        }
        while (_particles.length > target) {
            _particles.pop();
        }
    }

    function debouncedResize() {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(resize, 150);
    }

    // ── Основний цикл ────────────────────────────────────────────────────────
    function tick() {
        if (!_running) return;

        if (_paused) {
            _raf = requestAnimationFrame(tick);
            return;
        }

        var rect = _container.getBoundingClientRect();
        var w = Math.round(rect.width);
        var h = Math.round(rect.height);

        _ctx.clearRect(0, 0, w, h);

        for (var i = 0; i < _particles.length; i++) {
            var p = _particles[i];

            p.life++;

            // Вмер — переродити
            if (p.life >= p.lifespan) {
                _particles[i] = createParticle(w, h, true);
                continue;
            }

            // Рух
            p.x += p.speedX;
            p.y += p.speedY;

            // Вийшов за межі — переродити
            if (p.y < -10 || p.x < -10 || p.x > w + 10) {
                _particles[i] = createParticle(w, h, true);
                continue;
            }

            // Fade in / fade out
            var progress = p.life / p.lifespan;
            var alpha;
            if (progress < CONFIG.fadePercent) {
                alpha = (progress / CONFIG.fadePercent) * CONFIG.maxAlpha;
            } else if (progress > 1 - CONFIG.fadePercent) {
                alpha = ((1 - progress) / CONFIG.fadePercent) * CONFIG.maxAlpha;
            } else {
                alpha = CONFIG.maxAlpha;
            }

            // Мерехтіння (shimmer)
            var shimmer = Math.sin(p.life * p.shimmer + p.shimmerOffset) * 0.15 + 0.85;
            alpha *= shimmer;

            if (alpha <= 0.01) continue;

            // Малювання — кругла частинка з glow
            _ctx.beginPath();
            _ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
            _ctx.fillStyle = 'rgba(' + p.r + ',' + p.g + ',' + p.b + ',' + alpha.toFixed(3) + ')';
            _ctx.fill();

            // Невеликий glow для більших частинок
            if (p.size > 2) {
                _ctx.beginPath();
                _ctx.arc(p.x, p.y, p.size * 2.5, 0, Math.PI * 2);
                _ctx.fillStyle = 'rgba(' + p.r + ',' + p.g + ',' + p.b + ',' + (alpha * 0.15).toFixed(3) + ')';
                _ctx.fill();
            }
        }

        _raf = requestAnimationFrame(tick);
    }

    // ── Visibility API — пауза коли вкладка не видима ─────────────────────────
    function onVisibility() {
        _paused = document.hidden;
    }

    // ── Публічний API ─────────────────────────────────────────────────────────
    var ProfileParticles = {

        /**
         * Ініціалізація партіклів
         * @param {string|Element} containerSel — CSS-селектор або DOM-елемент
         * @param {object} [opts] — перезаписати CONFIG
         */
        init: function(containerSel, opts) {
            // Prefers reduced motion — не запускаємо
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            // Cleanup якщо вже був
            this.destroy();

            _container = typeof containerSel === 'string'
                ? document.querySelector(containerSel)
                : containerSel;

            if (!_container) return;

            // Merge opts
            if (opts) {
                for (var key in opts) {
                    if (opts.hasOwnProperty(key) && CONFIG.hasOwnProperty(key)) {
                        CONFIG[key] = opts[key];
                    }
                }
            }

            // Створюємо canvas
            _canvas = document.createElement('canvas');
            _canvas.className = 'profile-particles-canvas';
            _canvas.setAttribute('aria-hidden', 'true');
            _container.style.position = _container.style.position || 'relative';
            _container.appendChild(_canvas);

            _ctx = _canvas.getContext('2d');

            // Початковий розмір
            resize();

            // Генеруємо частинки
            var rect = _container.getBoundingClientRect();
            var count = getCount(Math.round(rect.width), Math.round(rect.height));
            _particles = [];
            for (var i = 0; i < count; i++) {
                _particles.push(createParticle(
                    Math.round(rect.width),
                    Math.round(rect.height),
                    false
                ));
            }

            // Слухачі
            window.addEventListener('resize', debouncedResize);
            document.addEventListener('visibilitychange', onVisibility);

            // Старт
            _running = true;
            _paused = false;
            _raf = requestAnimationFrame(tick);
        },

        /**
         * Повне знищення — чистить пам'ять
         */
        destroy: function() {
            _running = false;
            if (_raf) {
                cancelAnimationFrame(_raf);
                _raf = null;
            }
            if (_canvas && _canvas.parentNode) {
                _canvas.parentNode.removeChild(_canvas);
            }
            _canvas = null;
            _ctx = null;
            _particles = [];
            window.removeEventListener('resize', debouncedResize);
            document.removeEventListener('visibilitychange', onVisibility);
            clearTimeout(_resizeTimer);
        },

        /**
         * Пауза / продовження
         */
        pause: function() { _paused = true; },
        resume: function() { _paused = false; },

        /**
         * Перевірка стану
         */
        isRunning: function() { return _running && !_paused; },
    };

    window.ProfileParticles = ProfileParticles;

})(window);
