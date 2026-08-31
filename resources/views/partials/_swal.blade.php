{{--
    ██████████████████████████████████████████████████████████
    GLOBAL SWEETALERT2 — TOASTS + CONFIRMATIONS
    Included once in all 3 layouts (admin, host, app).

    Exports:
      window.bnbToast(icon, message)            — floating toast
      window.bnbConfirm(message[, opts])        — returns Promise<SweetAlertResult>
      window.bnbPrompt(opts)                    — dialog with required textarea
      window.bnbPriceConfirm(preview[, opts])   — price delta confirm with editable input
      attribute: data-swal-confirm="message"    — auto-intercepts wire:click / form buttons & submits
      attribute: data-bnb-price-change          — preview price impact, confirm delta, then Livewire execute
      attribute: data-bnb-price-action="method"  — Livewire action name for price change flow
      attribute: data-bnb-price-params='{}'     — optional JSON params for the action
      attribute: data-swal-confirm-title="title" — optional modal heading (default: حذف مورد for deletes)
      attribute: data-swal-confirm-variant="warn|delete|info" — optional confirm dialog style
      attribute: data-swal-prompt               — dialog + textarea, then calls Livewire method
    ██████████████████████████████████████████████████████████
--}}

{{-- SweetAlert2 (vendor copy; ~100KB one-time download, cached thereafter) --}}
<link rel="stylesheet" href="{{ vasset('vendor/sweetalert2/sweetalert2.min.css') }}">
<script src="{{ vasset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>

<style>
/* ── BNB Toast — LinkedIn-inspired notification card ───────── */
/* Force physical top-center (Swal toast uses inset + translateX).
   Scope to the toast container class — never steal confirm dialogs. */
body.swal2-toast-shown .swal2-container.bnb-toast-container,
.swal2-container.bnb-toast-container:not(.bnb-ios-overlay-container) {
    inset: 0 auto auto 50% !important;
    top: 0 !important;
    right: auto !important;
    bottom: auto !important;
    left: 50% !important;
    transform: translateX(-50%) !important;
    width: auto !important;
    max-width: calc(100vw - 24px) !important;
    padding: 18px 12px 0 12px !important;
    z-index: 10050 !important;
    pointer-events: none;
    overflow-x: visible !important;
}
.swal2-container.bnb-toast-container > .swal2-popup {
    pointer-events: auto;
    margin: 0 auto !important;
}

.bnb-swal-toast.swal2-popup.swal2-toast {
    direction: rtl;
    font-family: var(--bnb-font, 'Vazirmatn', Tahoma, sans-serif) !important;
    background: #fff !important;
    color: #1a1a1a !important;
    border: 1px solid rgba(0, 0, 0, 0.06) !important;
    border-radius: 14px !important;
    box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.06),
        0 16px 40px -8px rgba(0, 0, 0, 0.14),
        0 0 0 1px rgba(0, 0, 0, 0.02) !important;
    padding: 12px 14px 12px 10px !important;
    min-width: 300px;
    max-width: min(420px, calc(100vw - 32px));
    width: auto !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px;
    overflow: hidden;
    will-change: transform, opacity, filter;
    backface-visibility: hidden;
    -webkit-font-smoothing: antialiased;
}

.bnb-swal-toast .swal2-icon {
    width: 36px !important;
    height: 36px !important;
    min-width: 36px !important;
    margin: 0 !important;
    border: none !important;
    border-radius: 50% !important;
    flex-shrink: 0;
    box-shadow: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: relative !important;
    order: 1;
}
/* Hide broken SweetAlert line-drawn icons — we use Bootstrap Icons via iconHtml */
.bnb-swal-toast .swal2-success-circular-line-left,
.bnb-swal-toast .swal2-success-circular-line-right,
.bnb-swal-toast .swal2-success-fix-tip,
.bnb-swal-toast .swal2-success-fix-long,
.bnb-swal-toast .swal2-success-fix-hide,
.bnb-swal-toast .swal2-success-ring,
.bnb-swal-toast .swal2-success-line-tip,
.bnb-swal-toast .swal2-success-line-long,
.bnb-swal-toast .swal2-x-mark,
.bnb-swal-toast .swal2-x-mark-line-left,
.bnb-swal-toast .swal2-x-mark-line-right {
    display: none !important;
}
.bnb-swal-toast .swal2-icon .swal2-icon-content {
    font-size: 0 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 100% !important;
    height: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    line-height: 1 !important;
}
.bnb-swal-toast .swal2-icon .bi,
.bnb-swal-toast .swal2-icon .swal2-icon-content .bi {
    font-size: 18px !important;
    line-height: 1 !important;
    font-weight: 700 !important;
    display: block !important;
}

.bnb-swal-toast .swal2-icon.swal2-success {
    background: #e8f8ef !important;
    color: #0f9d58 !important;
}
.bnb-swal-toast .swal2-icon.swal2-error,
.bnb-swal-toast.bnb-toast--danger .swal2-icon {
    background: #fdecee !important;
    color: #d93025 !important;
}
.bnb-swal-toast .swal2-icon.swal2-warning {
    background: #fff4e5 !important;
    color: #e37400 !important;
}
.bnb-swal-toast .swal2-icon.swal2-info,
.bnb-swal-toast .swal2-icon.swal2-question {
    background: #e8f0fe !important;
    color: #1a73e8 !important;
}

.bnb-swal-toast .swal2-title {
    font-size: 13.5px !important;
    font-weight: 500 !important;
    line-height: 1.55 !important;
    color: #222 !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: right !important;
    flex: 1;
    align-self: center !important;
    order: 2;
}

.bnb-swal-toast.bnb-toast--multiline.swal2-popup.swal2-toast {
    align-items: flex-start !important;
}

.bnb-swal-toast.bnb-toast--multiline .swal2-icon {
    margin-top: 2px !important;
    align-self: flex-start !important;
}

.bnb-swal-toast.bnb-toast--multiline .swal2-title {
    white-space: pre-line;
    align-self: flex-start !important;
}

/* Close (×) — sits on the left in RTL toast layout */
.bnb-swal-toast .swal2-close {
    position: static !important;
    width: 28px !important;
    height: 28px !important;
    min-width: 28px !important;
    margin: 0 !important;
    padding: 0 !important;
    border-radius: 8px !important;
    font-size: 22px !important;
    font-weight: 400 !important;
    line-height: 28px !important;
    color: #9aa0a6 !important;
    background: transparent !important;
    box-shadow: none !important;
    outline: none !important;
    flex-shrink: 0;
    align-self: center !important;
    order: 3; /* after icon+title in RTL → visual left */
    transition: color .15s, background .15s;
}
.bnb-swal-toast .swal2-close:hover {
    color: #5f6368 !important;
    background: #f1f3f4 !important;
}
.bnb-swal-toast .swal2-close:focus {
    box-shadow: none !important;
}

.bnb-swal-toast .swal2-timer-progress-bar-container {
    position: absolute !important;
    right: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    height: 3px !important;
    background: transparent !important;
    border-radius: 0 0 14px 14px;
    overflow: hidden;
}
.bnb-swal-toast .swal2-timer-progress-bar {
    height: 3px !important;
    background: linear-gradient(90deg, #0a66c2, #378fe9) !important;
    border-radius: 0;
}

.bnb-swal-toast.bnb-toast--success .swal2-timer-progress-bar {
    background: linear-gradient(90deg, #0f9d58, #34a853) !important;
}
.bnb-swal-toast.bnb-toast--error .swal2-timer-progress-bar,
.bnb-swal-toast.bnb-toast--danger .swal2-timer-progress-bar {
    background: linear-gradient(90deg, #d93025, #ea4335) !important;
}
.bnb-swal-toast.bnb-toast--warning .swal2-timer-progress-bar {
    background: linear-gradient(90deg, #e37400, #f9ab00) !important;
}
.bnb-swal-toast.bnb-toast--info .swal2-timer-progress-bar {
    background: linear-gradient(90deg, #1a73e8, #4285f4) !important;
}

/* Accent strip */
.bnb-swal-toast::before {
    content: '';
    position: absolute;
    top: 10px;
    bottom: 10px;
    right: 0;
    width: 3px;
    border-radius: 3px 0 0 3px;
    background: #0a66c2;
}
.bnb-swal-toast.bnb-toast--success::before { background: #0f9d58; }
.bnb-swal-toast.bnb-toast--error::before,
.bnb-swal-toast.bnb-toast--danger::before  { background: #d93025; }
.bnb-swal-toast.bnb-toast--warning::before { background: #e37400; }
.bnb-swal-toast.bnb-toast--info::before    { background: #1a73e8; }

/* Smooth drop from top-center — professional ease, no bounce */
@keyframes bnb-toast-in {
    0% {
        opacity: 0;
        filter: blur(6px);
        transform: translate3d(0, -36px, 0) scale(0.96);
    }
    100% {
        opacity: 1;
        filter: blur(0);
        transform: translate3d(0, 0, 0) scale(1);
    }
}
@keyframes bnb-toast-out {
    0% {
        opacity: 1;
        filter: blur(0);
        transform: translate3d(0, 0, 0) scale(1);
    }
    100% {
        opacity: 0;
        filter: blur(4px);
        transform: translate3d(0, -20px, 0) scale(0.97);
    }
}

.bnb-swal-toast.swal2-show {
    animation: bnb-toast-in 0.55s cubic-bezier(0.16, 1, 0.3, 1) both !important;
}
.bnb-swal-toast.swal2-hide {
    animation: bnb-toast-out 0.32s cubic-bezier(0.4, 0, 0.2, 1) both !important;
}

@media (prefers-reduced-motion: reduce) {
    .bnb-swal-toast.swal2-show,
    .bnb-swal-toast.swal2-hide {
        animation-duration: 0.01ms !important;
        filter: none !important;
    }
}

@media (max-width: 480px) {
    .bnb-swal-toast.swal2-popup.swal2-toast {
        min-width: 0;
        width: calc(100vw - 32px) !important;
    }
}

/* Confirm / delete dialog — product delete-card layout (RTL) */
.bnb-swal-popup.swal2-popup {
    font-family: var(--bnb-font, 'Vazirmatn', Tahoma, sans-serif) !important;
    direction: rtl !important;
    width: min(420px, calc(100vw - 32px)) !important;
    padding: 28px 28px 24px !important;
    border-radius: 20px !important;
    border: none !important;
    box-shadow:
        0 4px 6px -2px rgba(0, 0, 0, 0.05),
        0 20px 40px -8px rgba(0, 0, 0, 0.18) !important;
    background: #fff !important;
}
.bnb-swal-popup .swal2-icon {
    display: none !important;
}
.bnb-swal-popup .swal2-title {
    display: none !important;
}
.bnb-swal-popup .swal2-html-container.bnb-swal-html {
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
    text-align: right !important;
    color: inherit !important;
}
.bnb-confirm-body {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    text-align: right;
}
.bnb-confirm-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    line-height: 1;
    flex-shrink: 0;
}
.bnb-confirm-icon--delete {
    background: #fee2e2;
    color: #dc2626;
}
.bnb-confirm-icon--warn {
    background: #fff4e5;
    color: #e37400;
}
.bnb-confirm-icon--info {
    background: #e8f0fe;
    color: #1a73e8;
}
.bnb-confirm-text {
    flex: 1;
    min-width: 0;
    padding-top: 4px;
}
.bnb-confirm-title {
    font-size: 17px;
    font-weight: 700;
    color: #111827;
    line-height: 1.35;
    margin: 0 0 6px;
}
.bnb-confirm-msg {
    font-size: 14px;
    font-weight: 400;
    color: #6b7280;
    line-height: 1.6;
    margin: 0;
}
.bnb-swal-popup .swal2-actions.bnb-swal-actions {
    margin: 24px 0 0 !important;
    padding: 0 !important;
    width: 100% !important;
    display: flex !important;
    flex-direction: row !important;
    gap: 12px !important;
    flex-wrap: nowrap !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm,
.bnb-swal-popup .swal2-styled.bnb-swal-cancel {
    margin: 0 !important;
    box-shadow: none !important;
    border-radius: 12px !important;
    font-family: inherit !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    padding: 11px 16px !important;
    line-height: 1.3 !important;
    flex: 1 1 0 !important;
    min-width: 0 !important;
    transition: background .15s, border-color .15s, color .15s;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm {
    background: #ef4444 !important;
    border: 1px solid #ef4444 !important;
    color: #fff !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm:hover {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.22) !important;
}
.bnb-swal-popup--generic .swal2-styled.bnb-swal-confirm {
    background: #0a66c2 !important;
    border-color: #0a66c2 !important;
}
.bnb-swal-popup--generic .swal2-styled.bnb-swal-confirm:hover {
    background: #004182 !important;
    border-color: #004182 !important;
}
.bnb-swal-popup--generic .swal2-styled.bnb-swal-confirm:focus {
    box-shadow: 0 0 0 3px rgba(10, 102, 194, 0.22) !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel:hover {
    background: #f9fafb !important;
    border-color: #d1d5db !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel:focus {
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.2) !important;
}

body.swal2-shown:not(.swal2-toast-shown) .swal2-container {
    background: rgba(15, 23, 42, 0.48) !important;
    backdrop-filter: blur(2px);
}

@keyframes bnb-confirm-in {
    0% {
        opacity: 0;
        transform: scale(0.94) translateY(8px);
    }
    100% {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}
.bnb-swal-popup.swal2-show {
    animation: bnb-confirm-in 0.28s cubic-bezier(0.16, 1, 0.3, 1) both !important;
}
.bnb-prompt-field {
    margin-top: 14px;
    text-align: right;
}
.bnb-prompt-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 6px;
}
.bnb-prompt-field textarea {
    width: 100%;
    min-height: 96px;
    resize: vertical;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 13.5px;
    line-height: 1.5;
    color: #1f2937;
    background: #fff;
    direction: rtl;
}
.bnb-prompt-field textarea:focus {
    outline: none;
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
}
.bnb-price-summary {
    margin-top: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    font-size: 13px;
    line-height: 1.7;
    color: #374151;
}
.bnb-price-summary strong {
    color: #111827;
}
.bnb-price-delta-hint {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}
.bnb-price-delta-hint.positive { color: #059669; }
.bnb-price-delta-hint.negative { color: #dc2626; }
.bnb-price-field input.money-input,
.bnb-price-field .bnb-price-delta-input {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 10px 12px;
    font-family: inherit;
    font-size: 14px;
    color: #1f2937;
    background: #fff;
    direction: ltr;
    text-align: left;
    pointer-events: auto;
    -webkit-user-select: text;
    user-select: text;
    cursor: text;
}
.bnb-price-field input.money-input:focus,
.bnb-price-field .bnb-price-delta-input:focus {
    outline: none;
    border-color: #0a66c2;
    box-shadow: 0 0 0 3px rgba(10, 102, 194, 0.15);
}
.bnb-swal-popup--price .swal2-input {
    display: none !important;
}
.bnb-price-reason-field textarea {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 8px 10px;
    font-family: inherit;
    font-size: 13px;
    resize: vertical;
    min-height: 64px;
}
.bnb-swal-popup--payment .bnb-swal-step-body {
    animation: bnbSwalStepIn 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}
@keyframes bnbSwalStepIn {
    from { opacity: 0; transform: translateX(18px); }
    to { opacity: 1; transform: translateX(0); }
}
.bnb-payment-doc-host .form-control[type="file"] {
    font-size: 12px;
}
</style>

<script>
(function () {
    /* ── LinkedIn-like notification chime (Web Audio) ───────── */
    var _audioCtx = null;
    function getAudioCtx() {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        if (!_audioCtx) _audioCtx = new AC();
        return _audioCtx;
    }

    /* Unlock AudioContext after first gesture (browser autoplay policy). */
    function unlockToastAudio() {
        try {
            var ctx = getAudioCtx();
            if (!ctx) return;
            if (ctx.state === 'suspended') ctx.resume().catch(function () {});
        } catch (e) {}
    }
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, unlockToastAudio, { once: true, passive: true });
    });

    /**
     * Soft two-note ascending chime, similar in character to
     * LinkedIn's in-app notification ping (short, polite, bright).
     */
    function playToastSound() {
        try {
            var ctx = getAudioCtx();
            if (!ctx) return;

            function run() {
                var now = ctx.currentTime;
                var master = ctx.createGain();
                master.gain.setValueAtTime(0.0001, now);
                master.gain.exponentialRampToValueAtTime(0.16, now + 0.018);
                master.gain.exponentialRampToValueAtTime(0.0001, now + 0.4);
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

                /* LinkedIn-ish: soft mid → bright upper ping */
                tone(784.0, now, 0.15, 0.2);         /* G5 */
                tone(1174.7, now + 0.085, 0.22, 0.26); /* D6 */
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

    /* ── iOS 27: topbar morphs into toast / confirm ─────────── */
    var morphState = null;
    var morphGen = 0;
    var MORPH_MS = 560;
    var CONTENT_FADE_MS = 240;

    function isIosPanel() {
        return document.body.classList.contains('ta-ios');
    }

    function overlayReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function getTopbar() {
        return document.querySelector('body.ta-ios .ta-main > header.ta-topbar')
            || document.querySelector('body.ta-ios header.ta-topbar');
    }

    function rectOf(el) {
        var r = el.getBoundingClientRect();
        return {
            left: r.left,
            top: r.top,
            width: r.width,
            height: r.height,
            right: r.right,
            bottom: r.bottom
        };
    }

    function hideTopbar(el) {
        if (!el) return;
        el.classList.add('ta-topbar--overlay-hidden');
        el.setAttribute('aria-hidden', 'true');
    }

    function showTopbar(el) {
        if (!el) return;
        el.classList.remove('ta-topbar--overlay-hidden');
        el.removeAttribute('aria-hidden');
    }

    function cleanupMorph(restore) {
        morphGen += 1;
        document.querySelectorAll('.ta-ios-morph-ghost').forEach(function (g) {
            if (g.parentNode) g.parentNode.removeChild(g);
        });
        if (restore) {
            document.querySelectorAll('header.ta-topbar.ta-topbar--overlay-hidden').forEach(showTopbar);
        }
        if (morphState && morphState.popup) {
            morphState.popup.classList.remove('bnb-ios-overlay-ready');
            morphState.popup.style.removeProperty('opacity');
            morphState.popup.style.removeProperty('pointer-events');
            morphState.popup.style.removeProperty('visibility');
        }
        morphState = null;
    }

    function pinOverlayContainer(container, barRect, mode) {
        if (!container || !barRect) return;
        var rightPad = Math.max(0, window.innerWidth - barRect.right);
        container.classList.add('bnb-ios-overlay-container');
        container.dataset.bnbOverlayMode = mode;
        container.style.setProperty('--bnb-overlay-top', Math.max(0, barRect.top) + 'px');
        container.style.setProperty('--bnb-overlay-left', Math.max(0, barRect.left) + 'px');
        container.style.setProperty('--bnb-overlay-right', rightPad + 'px');
        container.style.setProperty('--bnb-overlay-width', barRect.width + 'px');
        container.style.zIndex = mode === 'toast' ? '10050' : '10060';
    }

    function makeGhost(rect, tint) {
        var g = document.createElement('div');
        g.className = 'ta-ios-morph-ghost' + (tint ? ' ta-ios-morph-ghost--' + tint : '');
        g.setAttribute('aria-hidden', 'true');
        g.style.width = rect.width + 'px';
        g.style.height = rect.height + 'px';
        g.style.transform = 'translate3d(' + rect.left + 'px,' + rect.top + 'px,0)';
        document.body.appendChild(g);
        void g.offsetWidth;
        return g;
    }

    function moveGhost(g, rect, tint) {
        if (!g) return;
        g.className = 'ta-ios-morph-ghost' + (tint ? ' ta-ios-morph-ghost--' + tint : '');
        g.style.width = rect.width + 'px';
        g.style.height = rect.height + 'px';
        g.style.transform = 'translate3d(' + rect.left + 'px,' + rect.top + 'px,0)';
    }

    function startMorph(popup, mode, tint) {
        if (!isIosPanel() || !popup) return;

        cleanupMorph(false);

        var bar = getTopbar();
        var container = popup.closest('.swal2-container');

        if (!bar || overlayReducedMotion()) {
            if (container && bar) pinOverlayContainer(container, rectOf(bar), mode);
            popup.classList.add('bnb-ios-overlay-ready');
            return;
        }

        var first = rectOf(bar);
        if (first.width < 80 || first.height < 24) {
            popup.classList.add('bnb-ios-overlay-ready');
            return;
        }

        var gen = ++morphGen;
        pinOverlayContainer(container, first, mode);
        hideTopbar(bar);

        popup.style.opacity = '0';
        popup.style.visibility = 'hidden';
        popup.style.pointerEvents = 'none';

        var ghost = makeGhost(first, tint || '');

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                if (gen !== morphGen) return;
                var last = rectOf(popup);
                if (last.width < 8 || last.height < 8) {
                    last = {
                        left: mode === 'toast' ? first.left + Math.max(0, (first.width - 340) / 2) : first.left,
                        top: first.top,
                        width: mode === 'toast' ? Math.min(340, first.width) : first.width,
                        height: Math.max(first.height, mode === 'toast' ? 52 : 88)
                    };
                }
                morphState = {
                    el: bar,
                    first: first,
                    ghost: ghost,
                    popup: popup,
                    mode: mode,
                    tint: tint,
                    gen: gen
                };
                moveGhost(ghost, last, tint);
                window.setTimeout(function () {
                    if (gen !== morphGen) return;
                    popup.style.visibility = '';
                    popup.style.opacity = '';
                    popup.style.pointerEvents = '';
                    popup.classList.add('bnb-ios-overlay-ready');
                    if (ghost) ghost.classList.add('ta-ios-morph-ghost--settled');
                }, CONTENT_FADE_MS);
            });
        });

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                if (gen !== morphGen) return;
                var last = rectOf(popup);
                if (last.width < 8 || last.height < 8) {
                    last = {
                        left: mode === 'toast' ? first.left + Math.max(0, (first.width - 340) / 2) : first.left,
                        top: first.top,
                        width: mode === 'toast' ? Math.min(340, first.width) : first.width,
                        height: Math.max(first.height, mode === 'toast' ? 52 : 88)
                    };
                }
                morphState = {
                    el: bar,
                    first: first,
                    ghost: ghost,
                    popup: popup,
                    mode: mode,
                    tint: tint,
                    gen: gen
                };
                moveGhost(ghost, last, tint);
                window.setTimeout(function () {
                    if (gen !== morphGen) return;
                    popup.style.visibility = '';
                    popup.style.opacity = '';
                    popup.style.pointerEvents = '';
                    popup.classList.add('bnb-ios-overlay-ready');
                    if (ghost) ghost.classList.add('ta-ios-morph-ghost--settled');
                }, CONTENT_FADE_MS);
            });
        });
    }

    function reverseMorph() {
        if (!isIosPanel()) return;

        var state = morphState;
        var bar = (state && state.el) || getTopbar();
        var popup = state && state.popup;
        var first = state && state.first;
        var ghost = state && state.ghost;
        var tint = state && state.tint;
        var gen = ++morphGen;

        if (overlayReducedMotion() || !bar || !first) {
            cleanupMorph(true);
            return;
        }

        var from = popup ? rectOf(popup) : first;
        if (popup) {
            popup.classList.remove('bnb-ios-overlay-ready');
            popup.style.opacity = '0';
            popup.style.pointerEvents = 'none';
        }

        if (!ghost || !ghost.parentNode) {
            ghost = makeGhost(from, tint);
        } else {
            ghost.classList.remove('ta-ios-morph-ghost--settled');
            moveGhost(ghost, from, tint);
            void ghost.offsetWidth;
        }

        morphState = { el: bar, first: first, ghost: ghost, popup: popup, tint: tint, gen: gen };

        requestAnimationFrame(function () {
            if (gen !== morphGen) return;
            moveGhost(ghost, first, null);
        });

        window.setTimeout(function () {
            if (gen !== morphGen) return;
            cleanupMorph(true);
        }, MORPH_MS);
    }

    function iosOverlayHooks(mode, tint) {
        if (!isIosPanel()) {
            return {};
        }
        return {
            position: 'top',
            scrollbarPadding: false,
            heightAuto: false,
            showClass: { popup: 'bnb-ios-morph-show', backdrop: 'swal2-backdrop-show' },
            hideClass: { popup: 'bnb-ios-morph-hide', backdrop: 'swal2-backdrop-hide' },
            didOpen: function (popup) {
                startMorph(popup, mode, tint);
            },
            willClose: function () {
                reverseMorph();
            },
            didDestroy: function () {
                window.setTimeout(function () {
                    if (!document.querySelector('.swal2-container')) {
                        cleanupMorph(true);
                    }
                }, 0);
            }
        };
    }

    document.addEventListener('livewire:navigated', function () {
        cleanupMorph(true);
    });

    /* ── 1. TOAST MIXIN ─────────────────────────────────────── */
    var _Toast = null;
    function getToast() {
        if (!_Toast) {
            _Toast = Swal.mixin({
                toast: true,
                position: 'top',
                showConfirmButton: false,
                showCloseButton: true,
                closeButtonHtml: '&times;',
                timer: 3800,
                timerProgressBar: true,
                showClass: {
                    popup: 'swal2-show'
                },
                hideClass: {
                    popup: 'swal2-hide'
                },
                customClass: { popup: 'bnb-swal-toast', container: 'bnb-toast-container' },
                didOpen: function (toast) {
                    playToastSound();
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                    if (isIosPanel()) return;
                    var container = toast.closest('.swal2-container');
                    if (container) {
                        container.style.setProperty('inset', '0 auto auto 50%', 'important');
                        container.style.setProperty('left', '50%', 'important');
                        container.style.setProperty('right', 'auto', 'important');
                        container.style.setProperty('transform', 'translateX(-50%)', 'important');
                        container.style.setProperty('z-index', '10050', 'important');
                    }
                }
            });
        }
        return _Toast;
    }

    var TOAST_TYPES = { success: 1, error: 1, warning: 1, info: 1, question: 1, danger: 1 };
    var TOAST_ICONS = {
        success:  '<i class="bi bi-check-lg"></i>',
        error:    '<i class="bi bi-x-lg"></i>',
        warning:  '<i class="bi bi-exclamation-lg"></i>',
        info:     '<i class="bi bi-info-lg"></i>',
        question: '<i class="bi bi-question-lg"></i>',
        danger:   '<i class="bi bi-trash3-fill"></i>'
    };

    /* Delete / remove confirmations → red danger toast */
    function isDeleteToast(message) {
        return /حذف|پاک\s*شد|پاکسازی/.test(String(message || ''));
    }

    window.bnbToast = function (icon, message) {
        if (!window.Swal) return;
        var type = (icon && TOAST_TYPES[icon]) ? icon : 'success';
        if (type === 'success' && isDeleteToast(message)) {
            type = 'danger';
        }
        /* Swal only knows its built-in icons; map danger → error class for layout */
        var swalIcon = (type === 'danger') ? 'error' : type;
        var text = String(message || '');
        var isMultiline = text.indexOf('\n') !== -1;
        var hooks = iosOverlayHooks('toast', type);
        var mixinOpen = null;
        getToast().fire(Object.assign({
            icon: swalIcon,
            iconHtml: TOAST_ICONS[type] || TOAST_ICONS.success,
            title: text,
            customClass: {
                popup: 'bnb-swal-toast bnb-toast--' + type + (isMultiline ? ' bnb-toast--multiline' : ''),
                container: 'bnb-toast-container'
            }
        }, hooks, {
            didOpen: function (toast) {
                playToastSound();
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
                if (hooks.didOpen) {
                    hooks.didOpen(toast);
                    return;
                }
                var container = toast.closest('.swal2-container');
                if (container) {
                    container.style.setProperty('inset', '0 auto auto 50%', 'important');
                    container.style.setProperty('left', '50%', 'important');
                    container.style.setProperty('right', 'auto', 'important');
                    container.style.setProperty('transform', 'translateX(-50%)', 'important');
                    container.style.setProperty('z-index', '10050', 'important');
                }
            }
        }));
    };

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function isDeleteConfirmMessage(message) {
        return /حذف|پاک\s*(شود|شوند|کردن|می‌کنید|کنید)|remove/i.test(String(message || ''));
    }

    function isDestructiveConfirmMessage(message) {
        return isDeleteConfirmMessage(message) || /لغو\s*(شود|شوند|می‌کنید|کنید)|برنامه\s+لغو/i.test(String(message || ''));
    }

    function enhanceDeleteMessage(message) {
        var msg = String(message || '').trim() || 'آیا از حذف این مورد اطمینان دارید؟';
        if (/^(حذف\s*شود\??|پاک\s*شود\??)$/i.test(msg)) {
            msg = 'آیا از حذف این مورد اطمینان دارید؟';
        }
        if (!/بازگشت|غیرقابل|برگشت/i.test(msg)) {
            msg += ' این عمل غیرقابل بازگشت است.';
        }
        return msg;
    }

    function buildConfirmHtml(variant, heading, message) {
        var iconClass = 'bnb-confirm-icon--delete';
        var icon = '<i class="bi bi-trash3-fill"></i>';
        if (variant === 'warn') {
            iconClass = 'bnb-confirm-icon--warn';
            icon = '<i class="bi bi-exclamation-triangle-fill"></i>';
        } else if (variant === 'info') {
            iconClass = 'bnb-confirm-icon--info';
            icon = '<i class="bi bi-question-lg"></i>';
        }

        return (
            '<div class="bnb-confirm-body">' +
                '<div class="bnb-confirm-icon ' + iconClass + '">' + icon + '</div>' +
                '<div class="bnb-confirm-text">' +
                    '<div class="bnb-confirm-title">' + escapeHtml(heading) + '</div>' +
                    '<div class="bnb-confirm-msg">' + escapeHtml(message) + '</div>' +
                '</div>' +
            '</div>'
        );
    }

    function resolveConfirmVariant(message, opts) {
        if (opts && opts.variant) return opts.variant;
        if (isDeleteConfirmMessage(message)) return 'delete';
        if (isDestructiveConfirmMessage(message)) return 'warn';
        return 'info';
    }

    /* ── 2. CONFIRM HELPER ──────────────────────────────────── */
    window.bnbConfirm = function (message, opts) {
        opts = opts || {};
        var variant = resolveConfirmVariant(message, opts);
        var isDelete = variant === 'delete';
        var isDestructive = isDelete || variant === 'warn';
        var msg = message || (isDelete ? 'آیا از حذف این مورد اطمینان دارید؟' : 'آیا مطمئن هستید؟');
        if (isDelete && !opts.skipEnhance) {
            msg = enhanceDeleteMessage(msg);
        }
        var heading = opts.titleText || opts.heading || (
            isDelete ? 'حذف مورد' : (variant === 'warn' ? 'تأیید عملیات' : 'تأیید عملیات')
        );
        var tint = isDelete ? 'danger' : (variant === 'warn' ? 'warning' : '');
        var hooks = iosOverlayHooks('confirm', tint);

        var defaults = {
            title: '',
            icon: undefined,
            html: buildConfirmHtml(isDelete ? 'delete' : (variant === 'warn' ? 'warn' : 'info'), heading, msg),
            showCancelButton: true,
            focusCancel: true,
            reverseButtons: true,
            buttonsStyling: true,
            confirmButtonText: isDestructive ? 'تایید' : 'بله',
            cancelButtonText: 'انصراف',
            confirmButtonColor: isDestructive ? '#ff3b30' : '#007aff',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup bnb-swal-popup--bar' + (isDestructive ? '' : ' bnb-swal-popup--generic'),
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            },
            didOpen: function (popup) {
                var container = popup.closest('.swal2-container') || document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '10060';
            }
        };

        /* Allow callers to override, but keep our html/title layout unless they pass html */
        var userDidOpen = opts.didOpen;
        var userWillClose = opts.willClose;
        var merged = Object.assign({}, defaults, opts, hooks);
        if (!opts.html) {
            merged.html = defaults.html;
            merged.title = '';
            merged.icon = undefined;
        }
        if (opts.customClass) {
            merged.customClass = Object.assign({}, defaults.customClass, opts.customClass);
        } else if (hooks.customClass) {
            merged.customClass = Object.assign({}, defaults.customClass, hooks.customClass);
        }
        merged.didOpen = function (popup) {
            if (hooks.didOpen) hooks.didOpen(popup);
            else defaults.didOpen(popup);
            if (typeof userDidOpen === 'function') userDidOpen(popup);
        };
        merged.willClose = function () {
            if (hooks.willClose) hooks.willClose();
            if (typeof userWillClose === 'function') userWillClose();
        };
        if (hooks.didDestroy) merged.didDestroy = hooks.didDestroy;

        return Swal.fire(merged);
    };

    /* ── 2b. PROMPT HELPER (textarea) ───────────────────────── */
    window.bnbPrompt = function (opts) {
        opts = opts || {};
        var title = opts.title || opts.titleText || 'توضیحات';
        var label = opts.label || 'توضیحات';
        var placeholder = opts.placeholder || '';
        var confirmText = opts.confirmButtonText || '<i class="bi bi-check-lg ms-1"></i>ثبت';
        var fieldId = 'bnb-swal-prompt-' + Date.now();
        var hooks = iosOverlayHooks('confirm', 'danger');

        return Swal.fire(Object.assign({
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body">' +
                    '<div class="bnb-confirm-icon bnb-confirm-icon--info"><i class="bi bi-chat-left-text-fill"></i></div>' +
                    '<div class="bnb-confirm-text">' +
                        '<div class="bnb-confirm-title">' + escapeHtml(title) + '</div>' +
                        '<div class="bnb-prompt-field">' +
                            '<label for="' + fieldId + '">' + escapeHtml(label) + ' <span style="color:#ef4444;">*</span></label>' +
                            '<textarea id="' + fieldId + '" class="bnb-swal-prompt-input" placeholder="' + escapeHtml(placeholder) + '"></textarea>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            focusCancel: false,
            reverseButtons: true,
            confirmButtonText: confirmText,
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#ff3b30',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup bnb-swal-popup--sheet',
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            }
        }, hooks, {
            didOpen: function (popup) {
                if (hooks.didOpen) hooks.didOpen(popup);
                else {
                    var container = popup.closest('.swal2-container') || document.querySelector('.swal2-container');
                    if (container) container.style.zIndex = '10060';
                }
                var input = popup.querySelector('.bnb-swal-prompt-input');
                if (input) {
                    setTimeout(function () { input.focus(); }, isIosPanel() ? 280 : 50);
                }
            },
            preConfirm: function () {
                var input = Swal.getPopup().querySelector('.bnb-swal-prompt-input');
                var value = (input && input.value ? input.value : '').trim();
                if (!value) {
                    Swal.showValidationMessage('ذکر توضیحات الزامی است.');
                    return false;
                }
                return value;
            }
        }));
    };

    function formatTomanAmount(n) {
        var num = parseInt(n, 10) || 0;
        return new Intl.NumberFormat('fa-IR').format(num);
    }

    function parsePlainMoneyDigits(raw) {
        if (typeof window.parseMoney === 'function') {
            return window.parseMoney(raw);
        }
        var s = String(raw ?? '').trim()
            .replace(/[,،٬]/g, '')
            .replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
            .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
        var digits = s.replace(/[^\d]/g, '');
        return digits === '' ? 0 : parseInt(digits, 10);
    }

    function formatPlainMoneyDigits(value) {
        if (typeof window.formatMoney === 'function') {
            return window.formatMoney(value);
        }
        if (value === '' || value === null || value === undefined) {
            return '';
        }
        var n = parseInt(value, 10);
        if (Number.isNaN(n)) {
            return '';
        }
        return n.toLocaleString('fa-IR');
    }

    function parseSignedTomanInput(raw) {
        if (raw == null) return NaN;
        var s = String(raw).trim();
        if (s === '' || s === '+' ) return NaN;
        if (s === '-' || s === '−') return NaN;
        var negative = /^[-−]/.test(s);
        var digits = parsePlainMoneyDigits(s.replace(/^[-−+]/, ''));
        return negative ? -digits : digits;
    }

    function formatSignedMoneyInput(value) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }
        var n = parseInt(value, 10);
        if (Number.isNaN(n)) {
            return '';
        }
        if (n === 0) {
            return '0';
        }
        var formatted = formatPlainMoneyDigits(Math.abs(n));
        return n < 0 ? '-' + formatted : formatted;
    }

    var swalBootstrapFocusFixInstalled = false;
    function installSwalBootstrapFocusFix() {
        if (swalBootstrapFocusFixInstalled) return;
        swalBootstrapFocusFixInstalled = true;
        document.addEventListener('focusin', function (e) {
            if (typeof Swal === 'undefined' || !Swal.isVisible()) return;
            if (e.target && e.target.closest && e.target.closest('.swal2-container')) {
                e.stopImmediatePropagation();
            }
        }, true);
    }

    function pauseBootstrapModalFocusTraps() {
        if (!window.bootstrap?.Modal) return;
        document.querySelectorAll('.modal.show').forEach(function (modalEl) {
            var inst = bootstrap.Modal.getInstance(modalEl);
            if (inst && inst._focustrap && modalEl.dataset.bnbFocusTrapPaused !== '1') {
                inst._focustrap.deactivate();
                modalEl.dataset.bnbFocusTrapPaused = '1';
            }
        });
    }

    function resumeBootstrapModalFocusTraps() {
        if (!window.bootstrap?.Modal) return;
        document.querySelectorAll('.modal.show').forEach(function (modalEl) {
            if (modalEl.dataset.bnbFocusTrapPaused === '1') {
                var inst = bootstrap.Modal.getInstance(modalEl);
                if (inst && inst._focustrap) {
                    inst._focustrap.activate();
                }
                delete modalEl.dataset.bnbFocusTrapPaused;
            }
        });
    }

    installSwalBootstrapFocusFix();

    function resolveLivewireFromEl(el) {
        if (typeof Livewire === 'undefined') return null;
        var wireEl = el && el.closest ? el.closest('[wire\\:id]') : null;
        if (!wireEl) return null;
        return Livewire.find(wireEl.getAttribute('wire:id'));
    }

    function parsePriceChangeParams(raw) {
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function bindPriceDeltaInput(input, onChange, options) {
        options = options || {};
        var absolute = options.absolute === true;
        if (!input || input._bnbPriceBound) return;
        input._bnbPriceBound = true;
        input.removeAttribute('readonly');
        input.removeAttribute('disabled');
        input.removeAttribute('aria-disabled');
        input.type = 'text';
        input.inputMode = 'numeric';
        input.dir = 'ltr';
        input.autocomplete = 'off';
        input.spellcheck = false;
        input.classList.add('money-input', 'bnb-price-delta-input', 'form-control', 'form-control-sm');

        ['keydown', 'keyup', 'keypress'].forEach(function (evtName) {
            input.addEventListener(evtName, function (e) {
                e.stopPropagation();
            }, true);
        });

        ['mousedown', 'click', 'touchstart'].forEach(function (evtName) {
            input.addEventListener(evtName, function (e) {
                e.stopPropagation();
            });
        });

        input.addEventListener('focus', function () {
            setTimeout(function () {
                input.focus();
                if (typeof input.select === 'function') {
                    input.select();
                }
            }, 0);
        });

        input.addEventListener('input', function () {
            var raw = String(input.value || '');
            var trimmed = raw.trim();

            if (!absolute && (trimmed === '-' || trimmed === '−')) {
                input.value = '-';
                return;
            }

            var parsed = absolute ? parsePlainMoneyDigits(raw) : parseSignedTomanInput(raw);
            if (!Number.isNaN(parsed)) {
                input.value = absolute ? formatPlainMoneyDigits(parsed) : formatSignedMoneyInput(parsed);
            }

            if (typeof onChange === 'function') {
                onChange(parsed);
            }
        });
    }

    function focusPriceDeltaInput(input) {
        if (!input) return;
        input.focus();
        if (typeof input.select === 'function') {
            input.select();
        }
        try {
            input.setSelectionRange(0, String(input.value || '').length);
        } catch (e) {}
    }

    function readPriceDeltaInputValue(popup) {
        var input = popup ? popup.querySelector('.bnb-price-delta-input') : null;
        return input ? input.value : '';
    }

    window.bnbPriceConfirm = function (preview, opts) {
        opts = opts || {};
        preview = preview || {};
        var delta = parseInt(preview.auto_delta, 10) || 0;
        var currentTotal = parseInt(preview.current_total, 10) || 0;
        var isAbsolute = (preview.price_input_mode || 'delta') === 'absolute';
        var actionLabel = preview.action_label || 'تأیید تغییر مبلغ';
        var description = preview.description || 'این عملیات مبلغ رزرو را تغییر می‌دهد.';
        var deltaClass = delta > 0 ? 'positive' : (delta < 0 ? 'negative' : '');
        var deltaVerb = delta > 0 ? 'افزایش' : (delta < 0 ? 'کاهش' : 'بدون تغییر');
        var projectedId = 'bnb-price-projected-' + Date.now();
        var fieldId = 'bnb-price-delta-' + Date.now();
        var initialInputDisplay = isAbsolute
            ? formatPlainMoneyDigits(currentTotal)
            : formatSignedMoneyInput(delta);
        var reasonFieldId = 'bnb-price-reason-' + Date.now();
        var calculatedTotal = parseInt(preview.calculated_total, 10) || currentTotal;
        var defaultDelta = parseInt(preview.auto_delta, 10) || 0;

        function buildReasonFieldHtml() {
            return '<div class="bnb-prompt-field bnb-price-reason-field d-none" id="' + reasonFieldId + '-wrap">' +
                '<label for="' + reasonFieldId + '">توضیحات تغییر مبلغ (اختیاری)</label>' +
                '<textarea id="' + reasonFieldId + '" rows="2" maxlength="500" placeholder="در صورت تغییر مبلغ پیش‌فرض، دلیل را بنویسید..."></textarea>' +
            '</div>';
        }

        function toggleReasonField(inputValue) {
            var wrap = document.getElementById(reasonFieldId + '-wrap');
            if (!wrap) return;
            var changed = false;
            if (isAbsolute) {
                var parsed = parsePlainMoneyDigits(inputValue);
                changed = !Number.isNaN(parsed) && parsed !== calculatedTotal;
            } else {
                var signed = parseSignedTomanInput(inputValue);
                changed = !Number.isNaN(signed) && signed !== defaultDelta;
            }
            wrap.classList.toggle('d-none', !changed);
        }
        var inputLabel = isAbsolute
            ? 'مبلغ نهایی (ریال)'
            : 'مبلغ تغییر (ریال) — عدد مثبت افزایش، عدد منفی کاهش';
        var inputHint = isAbsolute
            ? 'مبلغ محاسبه‌شده را می‌توانید ویرایش کنید؛ با جداکننده سه‌رقمی.'
            : 'مبلغ را مثل سایر فیلدهای قیمت ویرایش کنید؛ با جداکننده سه‌رقمی.';

        function buildSummaryHtml(projectedTotal) {
            if (isAbsolute) {
                return '<div class="bnb-price-summary">' +
                    'مبلغ محاسبه‌شده: <strong dir="ltr">' + formatTomanAmount(currentTotal) + '</strong> ریال<br>' +
                    'مبلغ نهایی رزرو: <strong dir="ltr" id="' + projectedId + '">' + formatTomanAmount(projectedTotal) + '</strong> ریال' +
                '</div>';
            }

            var html = '<div class="bnb-price-summary">' +
                'مبلغ فعلی رزرو: <strong dir="ltr">' + formatTomanAmount(currentTotal) + '</strong> ریال<br>' +
                'تغییر پیشنهادی: <strong dir="ltr" class="' + deltaClass + '">' + (delta > 0 ? '+' : '') + formatTomanAmount(delta) + '</strong> ریال (' + deltaVerb + ')<br>' +
                'مبلغ پس از اعمال: <strong dir="ltr" id="' + projectedId + '">' + formatTomanAmount(projectedTotal) + '</strong> ریال';

            var listSubtotal = parseInt(preview.list_subtotal, 10) || 0;
            var policyDiscount = parseInt(preview.policy_discount, 10) || 0;
            if (listSubtotal > 0 && policyDiscount > 0) {
                html += '<br><span class="d-block mt-2 text-muted" style="font-size:.82rem;line-height:1.6">' +
                    'قیمت واحد خدمت: <strong dir="ltr">' + formatTomanAmount(listSubtotal) + '</strong> ریال<br>' +
                    'تخفیف ایثارگری خدمت: <strong dir="ltr" class="negative">−' + formatTomanAmount(policyDiscount) + '</strong> ریال<br>' +
                    'افزوده به مبلغ رزرو: <strong dir="ltr" class="' + deltaClass + '">' + (delta > 0 ? '+' : '') + formatTomanAmount(delta) + '</strong> ریال' +
                '</span>';
            } else if (preview.delta_explanation) {
                html += '<br><span class="d-block mt-2 text-muted" style="font-size:.82rem">' + escapeHtml(preview.delta_explanation) + '</span>';
            }

            return html + '</div>';
        }

        pauseBootstrapModalFocusTraps();

        var hooks = iosOverlayHooks('confirm', 'info');

        return Swal.fire(Object.assign({
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body">' +
                    '<div class="bnb-confirm-icon bnb-confirm-icon--info"><i class="bi bi-cash-coin"></i></div>' +
                    '<div class="bnb-confirm-text">' +
                        '<div class="bnb-confirm-title">' + escapeHtml(actionLabel) + '</div>' +
                        '<div class="bnb-confirm-msg">' + escapeHtml(description) + '</div>' +
                        buildSummaryHtml(isAbsolute ? currentTotal : (currentTotal + delta)) +
                        '<div class="bnb-prompt-field bnb-price-field">' +
                            '<label for="' + fieldId + '">' + escapeHtml(inputLabel) + '</label>' +
                            '<input id="' + fieldId + '" type="text" inputmode="numeric" dir="ltr" autocomplete="off" class="money-input bnb-price-delta-input form-control form-control-sm" value="' + escapeHtml(initialInputDisplay) + '">' +
                        '</div>' +
                        '<div class="bnb-price-delta-hint ' + deltaClass + '">' + escapeHtml(inputHint) + '</div>' +
                        buildReasonFieldHtml() +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            focusCancel: false,
            focusConfirm: false,
            reverseButtons: true,
            confirmButtonText: 'ثبت با این مبلغ',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#007aff',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup bnb-swal-popup--generic bnb-swal-popup--price bnb-swal-popup--sheet',
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            }
        }, hooks, {
            didOpen: function (popup) {
                if (hooks.didOpen) hooks.didOpen(popup);
                else {
                    var container = popup.closest('.swal2-container') || document.querySelector('.swal2-container');
                    if (container) container.style.zIndex = '10060';
                }

                var input = popup.querySelector('#' + fieldId);
                bindPriceDeltaInput(input, function (parsed) {
                    var projectedEl = popup.querySelector('#' + projectedId);
                    if (projectedEl && !Number.isNaN(parsed)) {
                        projectedEl.textContent = formatTomanAmount(
                            isAbsolute ? Math.max(0, parsed) : Math.max(0, currentTotal + parsed)
                        );
                    }
                    toggleReasonField(readPriceDeltaInputValue(popup));
                }, { absolute: isAbsolute });
                toggleReasonField(initialInputDisplay);
                setTimeout(function () { focusPriceDeltaInput(input); }, isIosPanel() ? 280 : 40);
            },
            willClose: function () {
                if (hooks.willClose) hooks.willClose();
                resumeBootstrapModalFocusTraps();
            },
            didDestroy: hooks.didDestroy,
            preConfirm: function () {
                var popup = Swal.getPopup();
                var reasonEl = popup ? popup.querySelector('#' + reasonFieldId) : null;
                var reasonWrap = document.getElementById(reasonFieldId + '-wrap');
                var priceReason = reasonEl && reasonWrap && !reasonWrap.classList.contains('d-none')
                    ? String(reasonEl.value || '').trim()
                    : '';

                if (isAbsolute) {
                    var finalTotal = parsePlainMoneyDigits(readPriceDeltaInputValue(popup));
                    if (Number.isNaN(finalTotal) || finalTotal < 0) {
                        Swal.showValidationMessage('مبلغ نهایی معتبر نیست.');
                        return false;
                    }
                    return { delta: finalTotal - currentTotal, price_adjustment_reason: priceReason };
                }

                var parsed = parseSignedTomanInput(readPriceDeltaInputValue(popup));
                if (Number.isNaN(parsed)) {
                    Swal.showValidationMessage('مبلغ تغییر معتبر نیست.');
                    return false;
                }
                return { delta: parsed, price_adjustment_reason: priceReason };
            }
        })).finally(function () {
            resumeBootstrapModalFocusTraps();
        });
    };

    function shouldSkipPaymentCapture(preview, confirmedDelta) {
        if (preview && preview.skip_payment_capture) return true;
        if (typeof confirmedDelta === 'number' && confirmedDelta < 0) return true;
        return false;
    }

    function dockPaymentUploadSlot(popup) {
        var slot = document.getElementById('bnb-payment-doc-slot');
        var host = popup ? popup.querySelector('.bnb-payment-doc-host') : null;
        if (!slot || !host) return;
        host.appendChild(slot);
        slot.classList.remove('d-none');
        slot.dataset.bnbDocked = '1';
    }

    function restorePaymentUploadSlot() {
        var slot = document.getElementById('bnb-payment-doc-slot');
        var anchor = document.getElementById('bnb-payment-doc-anchor');
        if (!slot || !anchor || slot.dataset.bnbDocked !== '1') return;
        anchor.appendChild(slot);
        slot.classList.add('d-none');
        delete slot.dataset.bnbDocked;
    }

    function refreshPosTerminalOptions(selectEl, terminals) {
        if (!selectEl) return;
        var current = selectEl.value;
        selectEl.innerHTML = '<option value="">— انتخاب ترمینال —</option>';
        (terminals || []).forEach(function (terminal) {
            var opt = document.createElement('option');
            opt.value = String(terminal.id);
            opt.textContent = terminal.label;
            selectEl.appendChild(opt);
        });
        if (current) selectEl.value = current;
    }

    window.bnbPaymentCaptureConfirm = function (preview, priceResult, component) {
        preview = preview || {};
        priceResult = priceResult || {};
        var terminals = preview.pos_terminals || [];
        var defaultDate = preview.default_payment_date || '';
        var defaultTime = preview.default_payment_time || '';
        var priceReason = priceResult.price_adjustment_reason || '';
        var fieldPrefix = 'bnb-pay-' + Date.now();
        var cardId = fieldPrefix + '-card';
        var trackingId = fieldPrefix + '-tracking';
        var dateId = fieldPrefix + '-date';
        var timeId = fieldPrefix + '-time';
        var terminalId = fieldPrefix + '-terminal';

        pauseBootstrapModalFocusTraps();
        var hooks = iosOverlayHooks('confirm', 'info');

        return Swal.fire(Object.assign({
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body bnb-swal-step-body">' +
                    '<div class="bnb-confirm-icon bnb-confirm-icon--info"><i class="bi bi-credit-card-2-front"></i></div>' +
                    '<div class="bnb-confirm-text">' +
                        '<div class="bnb-confirm-title">ثبت اطلاعات پرداخت</div>' +
                        '<div class="bnb-confirm-msg">۴ رقم آخر کارت یا شماره پیگیری (حداقل یکی) و ترمینال پرداخت را ثبت کنید.</div>' +
                        '<div class="row g-2 mt-2">' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + cardId + '">۴ رقم آخر کارت</label>' +
                                '<input id="' + cardId + '" type="text" maxlength="4" inputmode="numeric" dir="ltr" class="form-control form-control-sm" placeholder="1234">' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + trackingId + '">شماره پیگیری تراکنش</label>' +
                                '<input id="' + trackingId + '" type="text" dir="ltr" class="form-control form-control-sm" placeholder="رسید / پیگیری">' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + dateId + '">تاریخ پرداخت</label>' +
                                '<input id="' + dateId + '" type="text" dir="ltr" class="form-control form-control-sm jalali-date-input" value="' + escapeHtml(defaultDate) + '">' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + timeId + '">ساعت پرداخت</label>' +
                                '<input id="' + timeId + '" type="text" dir="ltr" class="form-control form-control-sm" value="' + escapeHtml(defaultTime) + '" placeholder="14:30">' +
                            '</div>' +
                            '<div class="col-12">' +
                                '<label class="form-label small" for="' + terminalId + '">شماره ترمینال <span class="text-danger">*</span></label>' +
                                '<select id="' + terminalId + '" class="form-select form-select-sm"></select>' +
                                '<button type="button" class="btn btn-link btn-sm p-0 text-decoration-none mt-1" id="' + fieldPrefix + '-add-terminal">' +
                                    '<i class="bi bi-plus-circle me-1"></i>ترمینال در لیست نیست؟ افزودن' +
                                '</button>' +
                            '</div>' +
                            '<div class="col-12 bnb-payment-doc-host"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            focusCancel: false,
            focusConfirm: false,
            reverseButtons: true,
            confirmButtonText: 'ثبت نهایی',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#007aff',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup bnb-swal-popup--generic bnb-swal-popup--payment bnb-swal-popup--sheet',
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            }
        }, hooks, {
            didOpen: function (popup) {
                if (hooks.didOpen) hooks.didOpen(popup);
                var selectEl = popup.querySelector('#' + terminalId);
                refreshPosTerminalOptions(selectEl, terminals);
                dockPaymentUploadSlot(popup);
                var addBtn = popup.querySelector('#' + fieldPrefix + '-add-terminal');
                if (addBtn && component) {
                    addBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        if (typeof Livewire !== 'undefined') {
                            Livewire.dispatch('bnb-open-pos-terminal-modal');
                        }
                    });
                }
                if (component) {
                    component.call('listPosTerminalsForPaymentCapture').then(function (list) {
                        refreshPosTerminalOptions(selectEl, list || terminals);
                    });
                }
                document.addEventListener('bnb-pos-terminals-updated', function handler() {
                    if (!component) return;
                    component.call('listPosTerminalsForPaymentCapture').then(function (list) {
                        refreshPosTerminalOptions(selectEl, list || []);
                    });
                });
            },
            willClose: function () {
                restorePaymentUploadSlot();
                if (hooks.willClose) hooks.willClose();
                resumeBootstrapModalFocusTraps();
            },
            didDestroy: hooks.didDestroy,
            preConfirm: function () {
                var popup = Swal.getPopup();
                var card = popup.querySelector('#' + cardId);
                var tracking = popup.querySelector('#' + trackingId);
                var dateEl = popup.querySelector('#' + dateId);
                var timeEl = popup.querySelector('#' + timeId);
                var terminalEl = popup.querySelector('#' + terminalId);
                var cardVal = card ? String(card.value || '').replace(/\D/g, '') : '';
                var trackingVal = tracking ? String(tracking.value || '').trim() : '';
                if (cardVal.length > 0 && cardVal.length !== 4) {
                    Swal.showValidationMessage('۴ رقم آخر کارت باید دقیقاً ۴ رقم باشد.');
                    return false;
                }
                if (!cardVal && !trackingVal) {
                    Swal.showValidationMessage('حداقل یکی از «۴ رقم آخر کارت» یا «شماره پیگیری» الزامی است.');
                    return false;
                }
                if (!terminalEl || !terminalEl.value) {
                    Swal.showValidationMessage('انتخاب ترمینال الزامی است.');
                    return false;
                }
                return {
                    payment_capture: {
                        card_last_four: cardVal || null,
                        transaction_tracking: trackingVal || null,
                        payment_date_jalali: dateEl ? dateEl.value : defaultDate,
                        payment_time: timeEl ? timeEl.value : defaultTime,
                        pos_terminal_id: parseInt(terminalEl.value, 10),
                        price_adjustment_reason: priceReason || null,
                    }
                };
            }
        })).finally(function () {
            restorePaymentUploadSlot();
            resumeBootstrapModalFocusTraps();
        });
    };

    window.bnbPosTerminalForm = function (opts) {
        opts = opts || {};
        var isEdit = opts.mode === 'edit';
        var terminal = opts.terminal || {};
        var provinces = opts.provinces || [];
        var prefix = 'bnb-pos-t-' + Date.now();
        var provinceId = prefix + '-province';
        var numberId = prefix + '-number';
        var labelId = prefix + '-label';
        var activeId = prefix + '-active';
        var defaultProvince = isEdit
            ? String(terminal.province_id || '')
            : String(opts.defaultProvinceId || provinces[0]?.id || '');

        var provinceOptions = '<option value="">— انتخاب استان —</option>';
        provinces.forEach(function (p) {
            var selected = String(p.id) === defaultProvince ? ' selected' : '';
            provinceOptions += '<option value="' + escapeHtml(String(p.id)) + '"' + selected + '>' + escapeHtml(p.name) + '</option>';
        });

        pauseBootstrapModalFocusTraps();
        var hooks = iosOverlayHooks('confirm', 'info');

        return Swal.fire(Object.assign({
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body bnb-swal-step-body">' +
                    '<div class="bnb-confirm-icon bnb-confirm-icon--info"><i class="bi bi-upc-scan"></i></div>' +
                    '<div class="bnb-confirm-text">' +
                        '<div class="bnb-confirm-title">' + (isEdit ? 'ویرایش ترمینال' : 'افزودن ترمینال') + '</div>' +
                        '<div class="bnb-confirm-msg">' + (isEdit ? 'اطلاعات ترمینال را ویرایش کنید.' : 'ترمینال جدید را برای استان انتخاب‌شده ثبت کنید.') + '</div>' +
                        '<div class="row g-2 mt-2">' +
                            '<div class="col-12">' +
                                '<label class="form-label small" for="' + provinceId + '">استان <span class="text-danger">*</span></label>' +
                                '<select id="' + provinceId + '" class="form-select form-select-sm">' + provinceOptions + '</select>' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + numberId + '">شماره ترمینال <span class="text-danger">*</span></label>' +
                                '<input id="' + numberId + '" type="text" dir="ltr" class="form-control form-control-sm" value="' + escapeHtml(terminal.terminal_number || '') + '">' +
                            '</div>' +
                            '<div class="col-md-6">' +
                                '<label class="form-label small" for="' + labelId + '">عنوان (اختیاری)</label>' +
                                '<input id="' + labelId + '" type="text" class="form-control form-control-sm" value="' + escapeHtml(terminal.label || '') + '">' +
                            '</div>' +
                            '<div class="col-12">' +
                                '<div class="form-check mt-1">' +
                                    '<input type="checkbox" id="' + activeId + '" class="form-check-input"' + ((isEdit && !terminal.is_active) ? '' : ' checked') + '>' +
                                    '<label class="form-check-label small" for="' + activeId + '">فعال</label>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            focusCancel: false,
            focusConfirm: false,
            reverseButtons: true,
            confirmButtonText: isEdit ? 'ذخیره تغییرات' : 'ثبت ترمینال',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#007aff',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup bnb-swal-popup--generic bnb-swal-popup--price bnb-swal-popup--sheet',
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            }
        }, hooks, {
            willClose: function () {
                if (hooks.willClose) hooks.willClose();
                resumeBootstrapModalFocusTraps();
            },
            didDestroy: hooks.didDestroy,
            preConfirm: function () {
                var popup = Swal.getPopup();
                var provinceEl = popup.querySelector('#' + provinceId);
                var numberEl = popup.querySelector('#' + numberId);
                var labelEl = popup.querySelector('#' + labelId);
                var activeEl = popup.querySelector('#' + activeId);
                var provinceVal = provinceEl ? provinceEl.value : '';
                var numberVal = numberEl ? String(numberEl.value || '').trim() : '';
                if (!provinceVal) {
                    Swal.showValidationMessage('انتخاب استان الزامی است.');
                    return false;
                }
                if (!numberVal) {
                    Swal.showValidationMessage('شماره ترمینال الزامی است.');
                    return false;
                }
                return {
                    editing_id: isEdit ? parseInt(terminal.id, 10) || null : null,
                    province_id: provinceVal,
                    terminal_number: numberVal,
                    label: labelEl ? String(labelEl.value || '').trim() : '',
                    is_active: activeEl ? activeEl.checked : true
                };
            }
        })).finally(function () {
            resumeBootstrapModalFocusTraps();
        });
    };

    function readPosTerminalProvinces() {
        var el = document.getElementById('bnb-pos-terminal-provinces');
        if (!el) return [];
        try {
            return JSON.parse(el.textContent || '[]');
        } catch (e) {
            return [];
        }
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-bnb-pos-terminal-form]');
        if (!btn) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var mode = btn.getAttribute('data-bnb-pos-terminal-form') || 'create';
        var component = resolveLivewireFromEl(btn);
        if (!component) {
            bnbToast('error', 'امکان انجام عملیات وجود ندارد.');
            return;
        }

        var opts = {
            mode: mode,
            provinces: readPosTerminalProvinces(),
            defaultProvinceId: btn.getAttribute('data-default-province-id') || ''
        };

        if (mode === 'edit') {
            opts.terminal = {
                id: parseInt(btn.getAttribute('data-terminal-id'), 10) || 0,
                province_id: btn.getAttribute('data-province-id') || '',
                terminal_number: btn.getAttribute('data-terminal-number') || '',
                label: btn.getAttribute('data-label') || '',
                is_active: btn.getAttribute('data-is-active') !== '0'
            };
        }

        bnbPosTerminalForm(opts).then(function (result) {
            if (!result.isConfirmed || !result.value) return;
            var v = result.value;
            component.call(
                'saveFromSwal',
                v.editing_id,
                v.province_id,
                v.terminal_number,
                v.label,
                v.is_active
            );
        });
    }, true);

    function finalizeConfirmedPriceChange(component, action, params, confirmedDelta, priceResult) {
        var merged = Object.assign({}, params || {});
        if (priceResult && priceResult.price_adjustment_reason) {
            merged.price_adjustment_reason = priceResult.price_adjustment_reason;
        }
        if (priceResult && priceResult.payment_capture) {
            merged.payment_capture = priceResult.payment_capture;
        }
        component.call('executeConfirmedPriceChange', action, confirmedDelta, merged);
    }

    function runBookingPriceChangeFlow(component, action, params, onCancel) {
        if (!component || !action) {
            bnbToast('error', 'امکان انجام عملیات وجود ندارد.');
            if (typeof onCancel === 'function') onCancel();
            return;
        }

        component.call('previewBookingPriceChange', action, params || {}).then(function (preview) {
            if (!preview) {
                if (typeof onCancel === 'function') onCancel();
                return;
            }
            if (preview.error) {
                bnbToast('error', preview.message || 'خطا در محاسبه تغییر مبلغ.');
                if (typeof onCancel === 'function') onCancel();
                return;
            }
            if (!preview.affects_price) {
                var infoMsg = preview.info_message || preview.description || 'ادامه می‌دهید؟';
                bnbConfirm(infoMsg, { titleText: preview.action_label || 'تأیید عملیات', variant: 'info' }).then(function (result) {
                    if (!result.isConfirmed) {
                        if (typeof onCancel === 'function') onCancel();
                        return;
                    }
                    component.call('executeConfirmedPriceChange', action, 0, params || {});
                });
                return;
            }
            bnbPriceConfirm(preview).then(function (result) {
                if (!result.isConfirmed) {
                    if (typeof onCancel === 'function') onCancel();
                    return;
                }
                var confirmedDelta = result.value && typeof result.value.delta === 'number'
                    ? result.value.delta
                    : (parseInt(preview.auto_delta, 10) || 0);
                var priceResult = result.value || {};

                if (shouldSkipPaymentCapture(preview, confirmedDelta)) {
                    finalizeConfirmedPriceChange(component, action, params, confirmedDelta, priceResult);
                    return;
                }

                bnbPaymentCaptureConfirm(preview, priceResult, component).then(function (payResult) {
                    if (!payResult.isConfirmed) {
                        if (typeof onCancel === 'function') onCancel();
                        return;
                    }
                    var mergedPrice = Object.assign({}, priceResult, payResult.value || {});
                    finalizeConfirmedPriceChange(component, action, params, confirmedDelta, mergedPrice);
                });
            });
        });
    }

    /* ── 3. data-swal-confirm INTERCEPTOR ───────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-swal-confirm]');
        if (!btn || btn._swalBypassed) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var message = btn.dataset.swalConfirm || 'آیا از این عملیات مطمئن هستید؟';
        var confirmOpts = {};
        if (btn.dataset.swalConfirmTitle) {
            confirmOpts.titleText = btn.dataset.swalConfirmTitle;
        }
        if (btn.dataset.swalConfirmVariant) {
            confirmOpts.variant = btn.dataset.swalConfirmVariant;
        }

        bnbConfirm(message, confirmOpts).then(function (result) {
            if (!result.isConfirmed) return;
            btn._swalBypassed = true;
            var form = btn.form || btn.closest('form');
            if (form) form._swalBypassed = true;
            btn.click();
            Promise.resolve().then(function () {
                delete btn._swalBypassed;
                if (form) delete form._swalBypassed;
            });
        });
    }, true);

    /* ── 3b. FORM SUBMIT INTERCEPTOR ────────────────────────── */
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form._swalBypassed) return;

        var submitter = e.submitter;
        var el = null;
        if (submitter && submitter.matches('[data-swal-confirm]')) {
            el = submitter;
        } else if (form.matches('[data-swal-confirm]')) {
            el = form;
        }
        if (!el) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var message = el.dataset.swalConfirm || 'آیا از این عملیات مطمئن هستید؟';
        var confirmOpts = {};
        if (el.dataset.swalConfirmTitle) {
            confirmOpts.titleText = el.dataset.swalConfirmTitle;
        }
        if (el.dataset.swalConfirmVariant) {
            confirmOpts.variant = el.dataset.swalConfirmVariant;
        }

        bnbConfirm(message, confirmOpts).then(function (result) {
            if (!result.isConfirmed) return;
            form._swalBypassed = true;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
            Promise.resolve().then(function () { delete form._swalBypassed; });
        });
    }, true);

    /* ── 3c. data-swal-prompt INTERCEPTOR (textarea → Livewire) ─ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-swal-prompt]');
        if (!btn || btn._swalBypassed) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var requestId = btn.dataset.swalPromptRequestId;
        var method = btn.dataset.swalPromptMethod || 'submitReject';
        if (!requestId) return;

        var root = btn.closest('[wire\\:id], [wire-id]');
        var wireId = root ? (root.getAttribute('wire:id') || root.getAttribute('wire-id')) : null;
        if (!wireId || typeof Livewire === 'undefined') return;

        bnbPrompt({
            title: btn.dataset.swalPromptTitle || 'رد درخواست کنسلی',
            label: btn.dataset.swalPromptLabel || 'دلیل رد درخواست',
            placeholder: btn.dataset.swalPromptPlaceholder || 'لطفاً دلیل رد این درخواست را برای مهمان بنویسید...',
            confirmButtonText: '<i class="bi bi-x-lg ms-1"></i>رد درخواست'
        }).then(function (result) {
            if (!result.isConfirmed) return;
            var value = (result.value || '').trim();
            if (!value) {
                bnbToast('warning', 'ذکر دلیل رد درخواست الزامی است.');
                return;
            }
            Livewire.find(wireId).call(method, parseInt(requestId, 10), value);
        });
    }, true);

    /* ── 3d. data-bnb-price-change INTERCEPTOR ──────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-bnb-price-change]');
        if (!btn || btn._bnbPriceBypassed) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var action = btn.dataset.bnbPriceAction || '';
        var params = parsePriceChangeParams(btn.dataset.bnbPriceParams || '{}');
        var component = resolveLivewireFromEl(btn);
        var onCancel = null;

        if (action === 'applyServiceQuotaSettings' && params.serviceId) {
            var serviceId = parseInt(params.serviceId, 10);
            onCancel = function () {
                if (component) component.call('revertServiceQuotaUi', serviceId);
            };
        }

        runBookingPriceChangeFlow(component, action, params, onCancel);
    }, true);

    function bindPriceChangeRequestListener() {
        if (typeof Livewire === 'undefined' || bindPriceChangeRequestListener.bound) return;
        bindPriceChangeRequestListener.bound = true;
        Livewire.on('bnb-price-change-request', function (payload) {
            if (Array.isArray(payload) && payload.length) payload = payload[0];
            var action = payload && payload.action;
            var params = (payload && payload.params) || {};
            var componentId = payload && payload.componentId;
            var component = componentId ? Livewire.find(componentId) : null;
            var onCancel = null;

            if (action === 'applyServiceQuotaSettings' && params.serviceId) {
                var serviceId = parseInt(params.serviceId, 10);
                onCancel = function () {
                    if (component) component.call('revertServiceQuotaUi', serviceId);
                };
            }

            runBookingPriceChangeFlow(component, action, params, onCancel);
        });
    }
    bindPriceChangeRequestListener.bound = false;
    document.addEventListener('livewire:init', bindPriceChangeRequestListener);
    document.addEventListener('livewire:initialized', bindPriceChangeRequestListener);

    /* ── 4. LIVEWIRE TOAST EVENTS ───────────────────────────── */
    function handleToastPayload(d) {
        if (!d) return;
        var swalOpen = document.querySelector('.swal2-container.swal2-shown');
        var isToastPopup = swalOpen && swalOpen.querySelector('.bnb-swal-toast');
        if (swalOpen && !isToastPopup) {
            return;
        }
        if (Array.isArray(d) && d.length) d = d[0];
        if (typeof d === 'object' && d !== null && d.detail) d = d.detail;
        if (Array.isArray(d) && d.length) d = d[0];
        var message = d.message || d.title || '';
        if (Array.isArray(d.messages) && d.messages.length) {
            message = d.messages.filter(Boolean).join('\n');
        }
        bnbToast(d.type || d.icon || 'success', message);
    }

    window.addEventListener('toast', function (e) {
        handleToastPayload(e.detail);
    });

    function bindLivewireToastListener() {
        if (typeof Livewire === 'undefined' || bindLivewireToastListener.bound) return;
        bindLivewireToastListener.bound = true;
        Livewire.on('toast', function (payload) {
            handleToastPayload(payload);
        });
    }
    bindLivewireToastListener.bound = false;

    document.addEventListener('livewire:init', bindLivewireToastListener);
    document.addEventListener('livewire:initialized', bindLivewireToastListener);
})();
</script>

{{-- Session-based flash toasts (shown on page load after redirect / full render) --}}
@php
    $__swalStatus  = session('status')  ?? session('success');
    $__swalError   = session('error');
    $__swalWarning = session('warning');
@endphp

@if($__swalStatus || $__swalError || $__swalWarning)
<script>
(function () {
    function showSessionToasts() {
        @if($__swalStatus)  bnbToast('success', @json($__swalStatus)); @endif
        @if($__swalError)   bnbToast('error',   @json($__swalError));  @endif
        @if($__swalWarning) bnbToast('warning', @json($__swalWarning));@endif
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showSessionToasts);
    } else {
        showSessionToasts();
    }
})();
</script>
@endif
