<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ورود پنل مدیریت | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ asset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
        }
        .staff-auth-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        @media (max-width: 768px) and (orientation: portrait) {
            body.staff-auth-page {
                overflow: hidden;
                position: fixed;
                width: 100%;
                height: 100%;
            }
            .staff-auth-shell {
                position: fixed;
                inset: 0;
                align-items: flex-start;
                padding-top: max(24px, env(safe-area-inset-top));
                padding-bottom: max(24px, env(safe-area-inset-bottom));
                overflow-y: auto;
                overscroll-behavior: none;
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
        .staff-login-shell-fade {
            transition: opacity .45s ease, filter .45s ease;
        }
        .staff-login-shell-fade.is-fading {
            opacity: 0;
            filter: blur(4px);
        }
        .staff-login-transition-overlay {
            position: fixed;
            top: 0; left: 0; width: 0; height: 0;
            z-index: 10070;
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            border-radius: 12px;
            box-shadow: 0 25px 60px rgba(15, 23, 42, .35);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 18px;
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
        }
        .staff-login-transition-overlay.is-active { visibility: visible; opacity: 1; }
        .staff-login-transition-overlay.is-expanding {
            transition:
                top .68s cubic-bezier(0.32, 0.72, 0, 1),
                left .68s cubic-bezier(0.32, 0.72, 0, 1),
                width .68s cubic-bezier(0.32, 0.72, 0, 1),
                height .68s cubic-bezier(0.32, 0.72, 0, 1),
                border-radius .68s cubic-bezier(0.32, 0.72, 0, 1);
        }
        .staff-login-transition-check {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(.5);
            transition: opacity .35s ease .12s, transform .45s cubic-bezier(0.34, 1.56, 0.64, 1) .12s;
        }
        .staff-login-transition-overlay.is-expanding .staff-login-transition-check {
            opacity: 1;
            transform: scale(1);
        }
        .staff-login-transition-check-svg { width: 84px; height: 84px; }
        .staff-login-transition-check-circle {
            stroke: #fff;
            stroke-width: 2.5;
            fill: none;
            stroke-dasharray: 151;
            stroke-dashoffset: 151;
            transition: stroke-dashoffset .5s ease-out .15s;
        }
        .staff-login-transition-check-mark {
            stroke: #fff;
            stroke-width: 3.5;
            fill: none;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 36;
            stroke-dashoffset: 36;
            transition: stroke-dashoffset .35s ease-out .45s;
        }
        .staff-login-transition-overlay.is-expanding .staff-login-transition-check-circle,
        .staff-login-transition-overlay.is-expanding .staff-login-transition-check-mark {
            stroke-dashoffset: 0;
        }
        .staff-login-transition-text {
            direction: rtl;
            color: #fff;
            font-family: 'Vazirmatn', sans-serif;
            font-size: 19px;
            font-weight: 600;
            letter-spacing: .2px;
            min-height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: translateY(6px);
            transition: opacity .35s ease, transform .35s ease;
        }
        .staff-login-transition-overlay.is-expanding .staff-login-transition-text {
            opacity: 1;
            transform: translateY(0);
        }
        .staff-login-transition-cursor {
            display: inline-block;
            width: 2px;
            height: 20px;
            margin-inline-start: 2px;
            background: #fff;
            animation: staff-login-caret-blink 0.9s steps(1) infinite;
        }
        @keyframes staff-login-caret-blink { 50% { opacity: 0; } }
        @media (prefers-reduced-motion: reduce) {
            .staff-login-transition-overlay,
            .staff-login-transition-overlay *,
            .staff-login-shell-fade {
                transition-duration: .01ms !important;
            }
            .staff-login-transition-cursor { animation: none; }
        }
    </style>
    @livewireStyles
</head>
<body class="staff-auth-page">
    {{ $slot }}

    <div id="staff-login-transition-overlay" class="staff-login-transition-overlay" aria-hidden="true">
        <div class="staff-login-transition-check">
            <svg viewBox="0 0 52 52" class="staff-login-transition-check-svg">
                <circle class="staff-login-transition-check-circle" cx="26" cy="26" r="24"/>
                <path class="staff-login-transition-check-mark" d="M14 27l7 7 16-16"/>
            </svg>
        </div>
        <div id="staff-login-transition-text" class="staff-login-transition-text"></div>
    </div>

    @livewireScripts
    @include('partials._swal')
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

            function runStaffLoginTransition(url) {
                var fallbackUrl = url || '{{ route('admin.login') }}';

                try {
                    var card = document.querySelector('.staff-auth-card');
                    var shell = document.querySelector('.staff-auth-shell');
                    var overlay = document.getElementById('staff-login-transition-overlay');
                    var textEl = document.getElementById('staff-login-transition-text');

                    if (!card || !overlay || !url) {
                        window.location.href = fallbackUrl;
                        return;
                    }

                    var rect = card.getBoundingClientRect();
                    overlay.style.top = rect.top + 'px';
                    overlay.style.left = rect.left + 'px';
                    overlay.style.width = rect.width + 'px';
                    overlay.style.height = rect.height + 'px';
                    overlay.style.borderRadius = '12px';
                    overlay.classList.add('is-active');

                    // Commit the starting rect before animating, then expand to fullscreen.
                    overlay.offsetHeight;

                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            if (shell) {
                                shell.classList.add('staff-login-shell-fade');
                                shell.classList.add('is-fading');
                            }
                            overlay.classList.add('is-expanding');
                            overlay.style.top = '0px';
                            overlay.style.left = '0px';
                            overlay.style.width = '100vw';
                            overlay.style.height = '100vh';
                            overlay.style.borderRadius = '0px';
                        });
                    });

                    // Fire the chime right as the checkmark tick strokes itself in.
                    setTimeout(playCheckmarkSound, 480);

                    var navigated = false;
                    var navigate = function () {
                        if (navigated) return;
                        navigated = true;
                        window.location.href = url;
                    };

                    // Start typing once the checkmark has drawn itself in (~800ms),
                    // then hold briefly on the finished text before navigating.
                    if (textEl) {
                        setTimeout(function () {
                            typeText(textEl, WELCOME_TEXT, 45, function () {
                                setTimeout(navigate, 450);
                            });
                        }, 850);
                    }

                    // Safety net in case typing/transition events never resolve.
                    setTimeout(navigate, 3200);
                } catch (e) {
                    window.location.href = fallbackUrl;
                }
            }

            function handleLoginSuccessPayload(d) {
                if (Array.isArray(d) && d.length) d = d[0];
                if (typeof d === 'object' && d !== null && d.detail) d = d.detail;
                if (Array.isArray(d) && d.length) d = d[0];
                runStaffLoginTransition(d && d.url ? d.url : null);
            }

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
