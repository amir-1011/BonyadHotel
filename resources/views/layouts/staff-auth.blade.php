<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(config('test_site.enabled'))
    <meta name="bnb-test-site-mode" content="1">
    @endif
    <title>ورود پنل مدیریت | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        html, body.staff-auth-page {
            height: 100%;
            overflow: hidden;
            overflow: clip;
        }
        body.staff-auth-page {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100dvh;
        }
        .staff-auth-shell {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
            overflow: hidden;
            overscroll-behavior: none;
            box-sizing: border-box;
        }
        @media (max-height: 640px), (max-width: 768px) and (orientation: portrait) {
            .staff-auth-shell {
                align-items: flex-start;
                overflow: auto;
                padding-top: max(24px, env(safe-area-inset-top));
                padding-bottom: max(24px, env(safe-area-inset-bottom));
                -webkit-overflow-scrolling: touch;
            }
        }
        .staff-auth-password-wrap {
            position: relative;
        }
        .staff-auth-password-wrap .staff-auth-input {
            padding-left: 44px;
        }
        .staff-auth-password-toggle {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #64748b;
            padding: 4px;
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }
        .staff-auth-password-toggle:hover { color: #334155; }
        .staff-auth-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 32px;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            position: relative;
            box-sizing: border-box;
        }
        .staff-auth-logo {
            height: 72px;
            width: auto;
            max-width: 56px;
            object-fit: contain;
            display: block;
            flex-shrink: 0;
        }
        .staff-auth-heading {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .staff-auth-lead {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 24px;
        }
        .staff-auth-lead--tight { margin-bottom: 4px; }
        .staff-auth-mobile {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            direction: ltr;
            margin-bottom: 24px;
        }
        .staff-auth-mobile--otp { margin-bottom: 20px; }
        .staff-auth-field { margin-bottom: 16px; }
        .staff-auth-field--secondary { margin-bottom: 16px; }
        .staff-auth-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 6px;
        }
        .staff-auth-error {
            color: #dc2626;
            font-size: 12px;
            margin-top: 4px;
        }
        .staff-auth-footer {
            text-align: center;
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .staff-auth-test-hint { font-size: 12px; }
        .staff-auth-input--otp {
            text-align: center;
            font-size: 24px;
            letter-spacing: 8px;
            font-weight: 700;
        }
        [data-staff-flip] {
            will-change: transform;
        }
        .staff-auth-card.is-morphing {
            overflow: hidden;
        }
        .staff-auth-card.is-morphing [data-staff-flip] {
            backface-visibility: hidden;
        }
        .staff-auth-card.is-login-fading > *,
        .staff-auth-shell.is-login-fading .staff-auth-brand-copy {
            opacity: 0;
            pointer-events: none;
            transition: opacity .32s ease;
        }
        .staff-auth-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color .2s;
        }
        .staff-auth-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,.15);
        }
        .staff-auth-btn {
            width: 100%;
            padding: 14px;
            background: #1e40af;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s;
        }
        .staff-auth-btn:hover { background: #1e3a8a; }
        .staff-auth-btn:disabled { opacity: .6; cursor: not-allowed; }
        .staff-auth-link {
            color: #64748b;
            font-size: 13px;
            text-decoration: underline;
            background: none;
            border: none;
            cursor: pointer;
        }
        .staff-auth-link:hover { color: #334155; }
        .staff-alert {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .staff-alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .staff-alert-danger  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ── Post-login "app opening" transition ─────────────────────── */
        html.staff-login-transitioning,
        body.staff-login-transitioning {
            overflow: hidden;
            background: #ffffff;
        }
        .staff-login-shell-fade {
            transition: opacity .45s ease, filter .45s ease;
        }
        .staff-login-shell-fade.is-fading {
            opacity: 0;
            filter: blur(4px);
        }
        .staff-login-preload-frame {
            position: fixed;
            inset: 0;
            width: 100%;
            height: 100%;
            border: 0;
            z-index: 10069;
            opacity: 0;
            pointer-events: none;
            background: #ffffff;
        }
        .staff-login-preload-frame.is-warming {
            opacity: 1;
        }
        .staff-login-preload-frame.is-visible {
            opacity: 1;
            pointer-events: auto;
        }
        .staff-login-flying-logo {
            position: fixed;
            top: 0;
            left: 0;
            width: 0;
            height: 0;
            z-index: 10072;
            display: block;
            pointer-events: none;
            opacity: 0;
            margin: 0;
            padding: 0;
            overflow: hidden;
            will-change: top, left, width, height, opacity;
        }
        .staff-login-flying-logo.is-active { opacity: 1; }
        .staff-login-flying-logo.is-moving {
            transition:
                top .68s cubic-bezier(0.32, 0.72, 0, 1),
                left .68s cubic-bezier(0.32, 0.72, 0, 1),
                width .68s cubic-bezier(0.32, 0.72, 0, 1),
                height .68s cubic-bezier(0.32, 0.72, 0, 1);
        }
        .staff-login-flying-logo.is-to-corner {
            transition:
                top .92s cubic-bezier(0.22, 1, 0.36, 1),
                left .92s cubic-bezier(0.22, 1, 0.36, 1),
                width .92s cubic-bezier(0.22, 1, 0.36, 1),
                height .92s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .staff-login-flying-logo.is-done {
            opacity: 0;
            transition: opacity .18s ease;
        }
        .staff-login-logo-motion {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .staff-login-logo-motion svg {
            display: block;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        .staff-login-flying-logo .logo-piece {
            opacity: 0;
            stroke: none;
            stroke-width: 0;
            transform-box: fill-box;
        }
        .staff-login-flying-logo.is-story .logo-hand-base {
            transform-origin: 70% 100%;
            animation: isar-hand-rise .78s cubic-bezier(0.22, 0.82, 0.2, 1) both;
        }
        .staff-login-flying-logo.is-story .logo-hand-palm {
            transform-origin: 55% 95%;
            animation: isar-hand-rise .84s cubic-bezier(0.22, 0.82, 0.2, 1) .12s both;
        }
        .staff-login-flying-logo.is-story .logo-bird-from-hand {
            transform-origin: 55% 100%;
            animation: isar-emerge .82s cubic-bezier(0.18, 0.84, 0.24, 1) .5s both;
        }
        .staff-login-flying-logo.is-story .logo-bird-curve {
            transform-origin: 50% 50%;
            animation: isar-form .62s cubic-bezier(0.22, 0.82, 0.2, 1) .82s both;
        }
        .staff-login-flying-logo.is-story .logo-bird-wing {
            transform-origin: 42% 100%;
            animation: isar-unfurl .9s cubic-bezier(0.16, 0.86, 0.28, 1) 1.02s both;
        }
        .staff-login-flying-logo.is-story .logo-script-left {
            animation: isar-ink-left .52s ease both;
            animation-delay: calc(1.55s + (var(--isar-i, 0) * 55ms));
        }
        .staff-login-flying-logo.is-story .logo-script-right {
            animation: isar-ink-right .52s ease both;
            animation-delay: calc(1.72s + (var(--isar-i, 0) * 22ms));
        }
        @keyframes isar-hand-rise {
            0%   { opacity: 0; transform: translateY(34%) scale(0.86); }
            62%  { opacity: 1; }
            100% { opacity: 1; transform: none; }
        }
        @keyframes isar-emerge {
            0%   { opacity: 0; transform: translateY(26%) scale(0.68); }
            100% { opacity: 1; transform: none; }
        }
        @keyframes isar-form {
            0%   { opacity: 0; transform: scale(0.42); }
            100% { opacity: 1; transform: none; }
        }
        @keyframes isar-unfurl {
            0%   { opacity: 0; transform: translateY(22%) scale(0.42); }
            64%  { opacity: 1; transform: translateY(-4%) scale(1.05); }
            100% { opacity: 1; transform: none; }
        }
        @keyframes isar-ink-left {
            0%   { opacity: 0; transform: translate(-10px, 8px); }
            100% { opacity: 1; transform: none; }
        }
        @keyframes isar-ink-right {
            0%   { opacity: 0; transform: translate(10px, -6px); }
            100% { opacity: 1; transform: none; }
        }
        .staff-login-flying-logo.is-built .logo-piece {
            opacity: 1;
            transform: none;
            animation: none;
        }
        .staff-login-flying-logo.is-built #logo-mark {
            transform-origin: 50% 50%;
            transform-box: fill-box;
            animation: staff-login-logo-lock .48s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes staff-login-logo-lock {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.035); }
            100% { transform: scale(1); }
        }
        .staff-login-transition-overlay {
            position: fixed;
            top: 0; left: 0; width: 0; height: 0;
            z-index: 10070;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
            overflow: hidden;
            will-change: top, left, width, height, border-radius, box-shadow;
        }
        .staff-login-transition-overlay.is-active {
            visibility: visible;
            opacity: 1;
            pointer-events: auto;
        }
        .staff-login-transition-overlay.is-expanding {
            border-color: transparent;
            border-radius: 0;
            box-shadow: none;
            transition:
                top .92s cubic-bezier(0.22, 1, 0.36, 1),
                left .92s cubic-bezier(0.22, 1, 0.36, 1),
                width .92s cubic-bezier(0.22, 1, 0.36, 1),
                height .92s cubic-bezier(0.22, 1, 0.36, 1),
                border-radius .92s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow .65s ease,
                border-color .5s ease;
        }
        .staff-login-transition-overlay.is-revealing {
            opacity: 0;
            pointer-events: none;
            transition: opacity .45s ease;
        }
        .staff-login-transition-text {
            position: fixed;
            inset: 0;
            z-index: 10071;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-top: 148px;
            pointer-events: none;
            direction: rtl;
            color: #1e40af;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 19px;
            font-weight: 600;
            letter-spacing: .2px;
            min-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: opacity .35s ease;
        }
        .staff-login-transition-text.is-welcome {
            opacity: 1;
        }
        .staff-login-transition-text.is-done {
            opacity: 0;
        }
        .staff-login-transition-cursor {
            display: inline-block;
            width: 2px;
            height: 20px;
            margin-inline-start: 2px;
            background: #1e40af;
            animation: staff-login-caret-blink 0.9s steps(1) infinite;
        }
        @keyframes staff-login-caret-blink { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) {
            .staff-login-transition-overlay,
            .staff-login-transition-overlay *,
            .staff-login-shell-fade,
            .staff-login-flying-logo,
            .staff-login-flying-logo * {
                transition-duration: .01ms !important;
                animation: none !important;
            }
            .staff-login-transition-cursor { animation: none; }
        }
    </style>
    @include('partials._persian-digits-script')
    @livewireStyles
</head>
<body class="staff-auth-page">
    {{ $slot }}

    <iframe id="staff-login-preload-frame" class="staff-login-preload-frame" title="پنل" aria-hidden="true" loading="eager" fetchpriority="high"></iframe>
    <div id="staff-login-transition-overlay" class="staff-login-transition-overlay" aria-hidden="true"></div>
    <div id="staff-login-transition-text" class="staff-login-transition-text"></div>
    <div id="staff-login-flying-logo" class="staff-login-flying-logo" aria-hidden="true">
        <div class="staff-login-logo-motion">
            @if(is_file(public_path('logo/site-logo.svg')))
                {!! file_get_contents(public_path('logo/site-logo.svg')) !!}
            @endif
        </div>
    </div>

    @livewireScripts
    @include('partials._swal')
    @include('partials._test_site_notice_dialog')
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('staffAuthMorph', () => ({
                _snapshot: null,
                _stepAtCapture: null,
                _animating: false,
                _pendingRun: false,
                _endHeight: 0,
                _ease: 'cubic-bezier(0.22, 1, 0.36, 1)',
                _duration: 520,

                init() {
                    const card = this.$el;

                    this.$nextTick(() => this.resetCardHeight(card));

                    card.addEventListener('click', (e) => {
                        if (e.target.closest('[wire\\:click], button[type="submit"]')) {
                            this.capture(card);
                        }
                    }, true);

                    card.addEventListener('submit', () => this.capture(card), true);

                    const self = this;
                    const clearStaleSnapshot = () => {
                        if (self._snapshot && self.$wire.step === self._stepAtCapture) {
                            self._snapshot = null;
                            self._pendingRun = false;
                            self.resetCardHeight(card);
                        }
                    };

                    const lockCard = () => {
                        if (!self._snapshot) return;
                        card.classList.add('is-morphing');
                        card.style.transition = 'none';
                        card.style.overflow = 'hidden';
                        card.style.height = self._snapshot.height + 'px';
                    };

                    const runAfterMorph = () => {
                        if (!self._pendingRun || !self._snapshot || self._animating) return;
                        self._pendingRun = false;
                        lockCard();
                        requestAnimationFrame(() => self.run(card));
                    };

                    document.addEventListener('livewire:initialized', () => {
                        Livewire.hook('commit', ({ succeed }) => {
                            succeed(() => queueMicrotask(clearStaleSnapshot));
                        });

                        Livewire.hook('morph.updating', ({ el }) => {
                            if (!card.contains(el) && el !== card) return;
                            lockCard();
                        });

                        Livewire.hook('morph.updated', ({ el }) => {
                            if (!card.contains(el) && el !== card) return;
                            runAfterMorph();
                        });
                    }, { once: true });

                    this.$wire.$watch('step', (step) => {
                        if (!this._snapshot) return;
                        if (step === this._stepAtCapture) {
                            this._snapshot = null;
                            this._pendingRun = false;
                            this.resetCardHeight(card);
                            return;
                        }
                        this._pendingRun = true;
                        setTimeout(() => runAfterMorph(), 120);
                    });
                },

                resetCardHeight(card) {
                    card.classList.remove('is-morphing');
                    card.style.transition = '';
                    card.style.height = '';
                    card.style.overflow = '';
                },

                lockCardHeight(card, height) {
                    card.classList.add('is-morphing');
                    card.style.transition = 'none';
                    card.style.overflow = 'hidden';
                    card.style.height = height + 'px';
                },

                capture(card) {
                    if (this._animating) return;

                    const rects = {};
                    card.querySelectorAll('[data-staff-flip]').forEach((el) => {
                        rects[el.dataset.staffFlip] = el.getBoundingClientRect();
                    });

                    const height = card.offsetHeight;

                    this._snapshot = { rects, height };
                    this._stepAtCapture = this.$wire.step;
                    this.lockCardHeight(card, height);
                },

                measureNaturalHeight(card) {
                    const locked = card.style.height;

                    card.style.transition = 'none';
                    card.style.height = 'auto';
                    const natural = card.offsetHeight;
                    card.style.height = locked;
                    card.offsetHeight;

                    return natural;
                },

                run(card) {
                    const snapshot = this._snapshot;
                    if (!snapshot || this._animating) {
                        this._snapshot = null;
                        this._pendingRun = false;
                        return;
                    }

                    this._animating = true;
                    this._pendingRun = false;
                    const { rects, height: startHeight } = snapshot;
                    this._snapshot = null;

                    this.lockCardHeight(card, startHeight);

                    const endHeight = this.measureNaturalHeight(card);
                    this._endHeight = endHeight;

                    const flipTargets = [];
                    card.querySelectorAll('[data-staff-flip]').forEach((el) => {
                        const oldRect = rects[el.dataset.staffFlip];
                        if (!oldRect) return;

                        const rect = el.getBoundingClientRect();
                        const dx = Math.round(oldRect.left - rect.left);
                        const dy = Math.round(oldRect.top - rect.top);

                        if (dx === 0 && dy === 0) return;

                        el.style.zIndex = '2';
                        el.style.transform = `translate3d(${dx}px, ${dy}px, 0)`;
                        el.style.transition = 'none';
                        flipTargets.push(el);
                    });

                    const fadeTargets = [];
                    card.querySelectorAll('[data-staff-fade]').forEach((el, i) => {
                        el.style.opacity = '0';
                        el.style.transform = 'translateY(8px)';
                        el.style.transition = 'none';
                        fadeTargets.push({ el, delay: 60 + (i * 30) });
                    });

                    card.offsetHeight;

                    const transformDone = flipTargets.map((el) => new Promise((resolve) => {
                        const cleanup = () => {
                            el.style.transition = '';
                            el.style.transform = '';
                            el.style.zIndex = '';
                            resolve();
                        };

                        el.addEventListener('transitionend', (e) => {
                            if (e.propertyName === 'transform') cleanup();
                        }, { once: true });

                        setTimeout(cleanup, this._duration + 80);
                    }));

                    const heightDone = new Promise((resolve) => {
                        const cleanup = () => {
                            card.removeEventListener('transitionend', onEnd);
                            resolve();
                        };
                        const onEnd = (e) => {
                            if (e.propertyName === 'height') cleanup();
                        };
                        card.addEventListener('transitionend', onEnd);
                        setTimeout(cleanup, this._duration + 80);
                    });

                    requestAnimationFrame(() => {
                        card.style.transition = `height ${this._duration}ms ${this._ease}`;
                        card.style.height = endHeight + 'px';

                        flipTargets.forEach((el) => {
                            el.style.transition = `transform ${this._duration}ms ${this._ease}`;
                            el.style.transform = 'translate3d(0, 0, 0)';
                        });

                        fadeTargets.forEach(({ el, delay }) => {
                            el.style.transition = [
                                `opacity ${this._duration}ms ${this._ease}`,
                                `transform ${this._duration}ms ${this._ease}`,
                            ].join(', ');
                            el.style.transitionDelay = `${delay}ms`;
                            el.style.opacity = '1';
                            el.style.transform = 'translateY(0)';

                            const cleanup = () => {
                                el.style.transition = '';
                                el.style.transitionDelay = '';
                                el.style.opacity = '';
                                el.style.transform = '';
                            };

                            el.addEventListener('transitionend', cleanup, { once: true });
                            setTimeout(cleanup, this._duration + 100 + delay);
                        });
                    });

                    const finish = () => {
                        card.style.transition = 'none';
                        card.style.height = this._endHeight + 'px';
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                this.resetCardHeight(card);
                                this._animating = false;
                                if (this.$wire.step === 'password') {
                                    card.querySelector('.staff-auth-password-wrap input')?.focus({ preventScroll: true });
                                }
                            });
                        });
                    };

                    Promise.all([heightDone, ...transformDone]).then(finish).catch(finish);
                },
            }));
        });
    </script>

    <script>
        (function () {
            var WELCOME_TEXT = 'به بنیادیار خوش آمدید';
            var LOGIN_URL = '{{ route('admin.login') }}';
            var PANEL_ASSETS = [
                '{{ vasset('vendor/tailadmin/tailadmin.css') }}',
                '{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}',
                '{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}',
                '{{ vasset('vendor/bootstrap-icons/bootstrap-icons.css') }}',
                '{{ vasset('vendor/jquery/jquery.min.js') }}',
                '{{ vasset('vendor/bootstrap/bootstrap.bundle.min.js') }}',
                '{{ vasset('vendor/persian-datepicker/persian-datepicker.min.css') }}',
                '{{ vasset('vendor/persian-date/persian-date.min.js') }}',
                '{{ vasset('vendor/persian-datepicker/persian-datepicker.min.js') }}'
            ];
            var panelReady = null;

            /* ── Checkmark confirmation chime (Web Audio, no toast needed) ── */
            var _checkAudioCtx = null;
            function getCheckAudioCtx() {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return null;
                if (!_checkAudioCtx) _checkAudioCtx = new AC();
                return _checkAudioCtx;
            }

            function playCheckmarkSound() {
                try {
                    var ctx = getCheckAudioCtx();
                    if (!ctx) return;

                    function run() {
                        var now = ctx.currentTime;
                        var master = ctx.createGain();
                        master.gain.setValueAtTime(0.0001, now);
                        master.gain.exponentialRampToValueAtTime(0.22, now + 0.02);
                        master.gain.exponentialRampToValueAtTime(0.0001, now + 0.55);
                        master.connect(ctx.destination);

                        function tone(freq, start, dur, peak) {
                            var osc = ctx.createOscillator();
                            var gain = ctx.createGain();
                            osc.type = 'sine';
                            osc.frequency.setValueAtTime(freq, start);
                            gain.gain.setValueAtTime(0.0001, start);
                            gain.gain.exponentialRampToValueAtTime(peak, start + 0.012);
                            gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                            osc.connect(gain);
                            gain.connect(master);
                            osc.start(start);
                            osc.stop(start + dur + 0.02);
                        }

                        /* Bright rising "pop → ding" — playful checkmark confirmation */
                        tone(660, now, 0.09, 0.22);
                        tone(990, now + 0.07, 0.18, 0.24);
                        tone(1320, now + 0.16, 0.34, 0.22);
                    }

                    if (ctx.state === 'suspended') {
                        ctx.resume().then(run).catch(function () {});
                    } else {
                        run();
                    }
                } catch (e) {
                    /* autoplay / AudioContext blocked — ignore */
                }
            }

            function typeText(el, text, speed, onDone) {
                el.innerHTML = '<span class="staff-login-transition-typed"></span><span class="staff-login-transition-cursor"></span>';
                var typedEl = el.querySelector('.staff-login-transition-typed');
                var i = 0;

                function step() {
                    if (i <= text.length) {
                        typedEl.textContent = text.slice(0, i);
                        i++;
                        setTimeout(step, speed);
                    } else if (onDone) {
                        onDone();
                    }
                }
                step();
            }

            function prefersReducedMotion() {
                return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            }

            function copyRect(el) {
                var r = el.getBoundingClientRect();
                return { top: r.top, left: r.left, width: r.width, height: r.height };
            }

            function applyRect(el, rect) {
                el.style.top = rect.top + 'px';
                el.style.left = rect.left + 'px';
                el.style.width = Math.max(1, rect.width) + 'px';
                el.style.height = Math.max(1, rect.height) + 'px';
            }

            function wait(ms) {
                return new Promise(function (resolve) { setTimeout(resolve, ms); });
            }

            function waitTransition(el, ms) {
                return new Promise(function (resolve) {
                    var done = false;
                    var finish = function () {
                        if (done) return;
                        done = true;
                        el.removeEventListener('transitionend', onEnd);
                        resolve();
                    };
                    var onEnd = function (e) {
                        if (e.target === el) finish();
                    };
                    el.addEventListener('transitionend', onEnd);
                    setTimeout(finish, (ms || 700) + 80);
                });
            }

            function centerLogoRect(source) {
                var h = 128;
                var w = source && source.height
                    ? source.width * (h / source.height)
                    : 98;
                var textH = 28;
                var gap = 18;
                var group = h + gap + textH;
                return {
                    top: Math.max(24, (window.innerHeight - group) / 2),
                    left: (window.innerWidth - w) / 2,
                    width: w,
                    height: h
                };
            }

            function fallbackCornerRect(srcW, srcH) {
                var isRtl = document.documentElement.getAttribute('dir') !== 'ltr';
                var vw = window.innerWidth;
                var desktop = vw >= 992;
                var collapsed = false;
                try { collapsed = localStorage.getItem('ta-sidebar-collapsed') === '1'; } catch (e) {}
                var w = srcW || 40;
                var h = srcH || 40;
                if (desktop) {
                    w = Math.min(w, 40);
                    h = srcH && srcW ? w * (srcH / srcW) : 40;
                }
                var pad = 16;
                var left = isRtl ? vw - pad - w : pad;
                if (desktop && collapsed) {
                    var rail = 90;
                    left = isRtl ? vw - ((rail - w) / 2) - w : (rail - w) / 2;
                }
                return { top: desktop ? 18 : 14, left: left, width: w, height: h };
            }

            function measureDashboardLogo(iframe) {
                try {
                    var doc = iframe.contentDocument;
                    var img = doc && (doc.querySelector('.ta-sidebar__logo img') || doc.querySelector('.ta-sidebar__logo'));
                    if (!img) return null;
                    var r = img.getBoundingClientRect();
                    if (r.width < 8 || r.height < 8) return null;
                    if (r.left > window.innerWidth - 4 || r.right < 4) return null;
                    return { top: r.top, left: r.left, width: r.width, height: r.height };
                } catch (e) {
                    return null;
                }
            }

            function hideIframeLogo(iframe) {
                try {
                    var doc = iframe.contentDocument;
                    if (!doc) return null;
                    var style = doc.getElementById('staff-login-handoff-style');
                    if (!style) {
                        style = doc.createElement('style');
                        style.id = 'staff-login-handoff-style';
                        style.textContent = '.ta-sidebar__logo, .ta-sidebar__logo img { visibility: hidden !important; }';
                        (doc.head || doc.documentElement).appendChild(style);
                    }
                    return style;
                } catch (e) {
                    return null;
                }
            }

            function showIframeLogo(style) {
                if (style && style.parentNode) style.parentNode.removeChild(style);
            }

            function isStaffAuthHref(href) {
                try {
                    return new URL(href, window.location.origin).pathname
                        === new URL(LOGIN_URL, window.location.origin).pathname;
                } catch (e) {
                    return false;
                }
            }

            function syncShellUrl(iframe) {
                try {
                    var href = iframe.contentWindow.location.href;
                    if (!href || href === 'about:blank') return;
                    if (isStaffAuthHref(href)) {
                        window.location.replace(href);
                        return;
                    }
                    if (window.location.href !== href) {
                        history.replaceState(null, '', href);
                    }
                    if (iframe.contentDocument && iframe.contentDocument.title) {
                        document.title = iframe.contentDocument.title;
                    }
                } catch (e) {}
            }

            function bindIframeShell(iframe) {
                var attach = function () {
                    syncShellUrl(iframe);
                    try {
                        iframe.contentDocument.addEventListener('livewire:navigated', function () {
                            syncShellUrl(iframe);
                        });
                    } catch (e) {}
                };
                attach();
                iframe.addEventListener('load', attach);
            }

            function startPanelPreload(iframe, url) {
                if (panelReady) return panelReady;
                panelReady = new Promise(function (resolve) {
                    var settled = false;
                    var done = function () {
                        if (settled) return;
                        settled = true;
                        hideIframeLogo(iframe);
                        resolve(iframe);
                    };
                    iframe.addEventListener('load', done, { once: true });
                    iframe.setAttribute('fetchpriority', 'high');
                    iframe.src = url;
                    setTimeout(done, 10000);
                });
                return panelReady;
            }

            function warmPanelAssets() {
                if (warmPanelAssets.done) return;
                warmPanelAssets.done = true;
                PANEL_ASSETS.forEach(function (href) {
                    if (!href) return;
                    var link = document.createElement('link');
                    var isJs = /\.js(\?|$)/.test(href);
                    link.rel = 'preload';
                    link.as = isJs ? 'script' : 'style';
                    link.href = href;
                    document.head.appendChild(link);
                });
            }

            function playIsarMotion(root) {
                var left = 0;
                var right = 0;
                root.querySelectorAll('.logo-script-left').forEach(function (el) {
                    el.style.setProperty('--isar-i', String(left++));
                });
                root.querySelectorAll('.logo-script-right').forEach(function (el) {
                    el.style.setProperty('--isar-i', String(right++));
                });
                root.classList.add('is-story');
                return wait(2920);
            }

            function showTestSiteNoticeIfNeeded(flag, iframe) {
                if (!flag) return;

                function deliver(retry) {
                    retry = retry || 0;

                    if (iframe && iframe.contentWindow) {
                        try {
                            var panelWin = iframe.contentWindow;
                            if (typeof panelWin.showBnbTestSiteNotice === 'function') {
                                panelWin.showBnbTestSiteNotice();
                                return;
                            }
                            panelWin.postMessage(
                                { type: 'bnb-show-test-site-notice' },
                                window.location.origin
                            );
                            return;
                        } catch (e) {}
                    }

                    if (retry < 10) {
                        setTimeout(function () { deliver(retry + 1); }, 350);
                        return;
                    }

                    if (typeof window.showBnbTestSiteNotice === 'function') {
                        window.showBnbTestSiteNotice();
                    }
                }

                setTimeout(function () { deliver(0); }, 600);
            }

            function resolveLoginSuccessPayload(raw) {
                var d = raw;
                if (Array.isArray(d) && d.length) d = d[0];
                if (typeof d === 'object' && d !== null && d.detail) d = d.detail;
                if (Array.isArray(d) && d.length) d = d[0];
                return (d && typeof d === 'object') ? d : {};
            }

            function shouldShowTestSiteNotice(payload) {
                if (payload && payload.showTestSiteNotice) return true;
                return !!document.querySelector('meta[name="bnb-test-site-mode"]');
            }

            function runStaffLoginTransition(url, showTestSiteNotice) {
                var fallbackUrl = url || LOGIN_URL;

                if (!url) {
                    window.location.href = fallbackUrl;
                    return;
                }

                if (prefersReducedMotion()) {
                    window.location.href = url;
                    return;
                }

                try {
                    var card = document.querySelector('.staff-auth-card');
                    var shell = document.querySelector('.staff-auth-shell');
                    var overlay = document.getElementById('staff-login-transition-overlay');
                    var textEl = document.getElementById('staff-login-transition-text');
                    var flying = document.getElementById('staff-login-flying-logo');
                    var iframe = document.getElementById('staff-login-preload-frame');
                    var sourceLogo = document.querySelector('.staff-auth-logo');

                    if (!card || !overlay || !flying || !iframe) {
                        window.location.href = fallbackUrl;
                        return;
                    }

                    document.documentElement.classList.add('staff-login-transitioning');
                    document.body.classList.add('staff-login-transitioning');

                    var completed = false;
                    var hardNavigate = function (dest) {
                        if (completed) return;
                        completed = true;
                        window.location.href = dest || url;
                    };

                    var panelReady = startPanelPreload(iframe, url);
                    var logoStyle = null;
                    panelReady.then(function () {
                        logoStyle = hideIframeLogo(iframe);
                    });

                    var sourceRect = sourceLogo ? copyRect(sourceLogo) : null;
                    if (sourceLogo) sourceLogo.style.visibility = 'hidden';
                    applyRect(flying, sourceRect || centerLogoRect(null));
                    flying.classList.add('is-active');

                    card.classList.add('is-login-fading');
                    if (shell) shell.classList.add('is-login-fading');

                    var rect = card.getBoundingClientRect();
                    overlay.style.top = rect.top + 'px';
                    overlay.style.left = rect.left + 'px';
                    overlay.style.width = rect.width + 'px';
                    overlay.style.height = rect.height + 'px';
                    overlay.style.borderRadius = '12px';

                    var centerRect = centerLogoRect(sourceRect);

                    setTimeout(function () {
                        overlay.classList.add('is-active');
                        overlay.offsetHeight;
                        card.style.visibility = 'hidden';

                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                overlay.classList.add('is-expanding');
                                overlay.style.top = '0px';
                                overlay.style.left = '0px';
                                overlay.style.width = window.innerWidth + 'px';
                                overlay.style.height = window.innerHeight + 'px';
                                overlay.style.borderRadius = '0px';
                            });
                        });

                        flying.classList.add('is-moving');
                        applyRect(flying, centerRect);
                        setTimeout(function () {
                            iframe.classList.add('is-warming');
                        }, 860);
                    }, 320);

                    var finish = function () {
                        wait(320).then(function () {
                            return waitTransition(overlay, 980);
                        }).then(function () {
                            return playIsarMotion(flying);
                        }).then(function () {
                            flying.classList.add('is-built');
                            playCheckmarkSound();
                            return wait(380);
                        }).then(function () {
                            if (!textEl) return wait(300);
                            textEl.classList.add('is-welcome');
                            return new Promise(function (resolve) {
                                typeText(textEl, WELCOME_TEXT, 45, resolve);
                            }).then(function () { return wait(400); });
                        }).then(function () {
                            textEl && textEl.classList.add('is-done');
                            var dest = measureDashboardLogo(iframe)
                                || fallbackCornerRect(sourceRect ? sourceRect.width : 56, sourceRect ? sourceRect.height : 72);

                            flying.classList.remove('is-moving');
                            flying.offsetHeight;
                            flying.classList.add('is-to-corner');
                            applyRect(flying, dest);
                            return waitTransition(flying, 920);
                        }).then(function () {
                            return panelReady;
                        }).then(function () {
                            logoStyle = hideIframeLogo(iframe) || logoStyle;
                            var real = measureDashboardLogo(iframe);
                            if (real) applyRect(flying, real);

                            var href = '';
                            try { href = iframe.contentWindow.location.href; } catch (e) { href = ''; }
                            if (!href || href === 'about:blank') {
                                hardNavigate(url);
                                return;
                            }

                            iframe.classList.add('is-visible');
                            iframe.removeAttribute('aria-hidden');
                            overlay.classList.add('is-revealing');
                            return wait(420).then(function () {
                                showIframeLogo(logoStyle);
                                flying.classList.add('is-done');
                                completed = true;
                                bindIframeShell(iframe);
                                showTestSiteNoticeIfNeeded(showTestSiteNotice, iframe);
                            });
                        }).catch(function () {
                            hardNavigate(url);
                        });
                    };

                    finish();
                    setTimeout(function () { hardNavigate(url); }, 14000);
                } catch (e) {
                    window.location.href = fallbackUrl;
                }
            }

            function handleLoginSuccessPayload(d) {
                var payload = resolveLoginSuccessPayload(d);
                var url = payload.url || null;
                var showTestSiteNotice = shouldShowTestSiteNotice(payload);
                var iframe = document.getElementById('staff-login-preload-frame');
                if (url && iframe) startPanelPreload(iframe, url);
                runStaffLoginTransition(url, showTestSiteNotice);
            }

            document.addEventListener('input', function (e) {
                if (e.target && e.target.closest && e.target.closest('.staff-auth-card')) {
                    warmPanelAssets();
                }
            }, true);

            document.addEventListener('submit', function (e) {
                if (e.target && e.target.closest && e.target.closest('.staff-auth-card')) {
                    warmPanelAssets();
                }
            }, true);

            document.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest && e.target.closest('.staff-auth-card button[type="submit"]');
                if (btn) warmPanelAssets();
            }, true);

            window.addEventListener('staff-login-success', function (e) {
                handleLoginSuccessPayload(e.detail);
            });

            function bindLivewireLoginListener() {
                if (typeof Livewire === 'undefined' || bindLivewireLoginListener.bound) return;
                bindLivewireLoginListener.bound = true;
                Livewire.on('staff-login-success', function (payload) {
                    handleLoginSuccessPayload(payload);
                });
            }
            bindLivewireLoginListener.bound = false;

            document.addEventListener('livewire:init', bindLivewireLoginListener);
            document.addEventListener('livewire:initialized', bindLivewireLoginListener);
        })();
    </script>
</body>
</html>
