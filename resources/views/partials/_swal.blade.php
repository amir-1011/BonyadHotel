{{--
    ██████████████████████████████████████████████████████████
    GLOBAL SWEETALERT2 — TOASTS + CONFIRMATIONS
    Included once in all 3 layouts (admin, host, app).

    Exports:
      window.bnbToast(icon, message)            — floating toast
      window.bnbConfirm(message[, opts])        — returns Promise<SweetAlertResult>
      window.bnbPrompt(opts)                    — dialog with required textarea
      attribute: data-swal-confirm="message"    — auto-intercepts wire:click / form buttons & submits
      attribute: data-swal-prompt               — dialog + textarea, then calls Livewire method
    ██████████████████████████████████████████████████████████
--}}

{{-- SweetAlert2 (vendor copy; ~100KB one-time download, cached thereafter) --}}
<link rel="stylesheet" href="{{ asset('vendor/sweetalert2/sweetalert2.min.css') }}">
<script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}"></script>

<style>
/* ── BNB Toast — LinkedIn-inspired notification card ───────── */
/* Force physical top-center (Swal toast uses inset + translateX) */
body.swal2-toast-shown .swal2-container.swal2-top,
.swal2-container.swal2-top {
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
.swal2-container.swal2-top > .swal2-popup {
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

/* Confirm dialog — matches product confirmation card */
.bnb-swal-popup.swal2-popup {
    font-family: var(--bnb-font, 'Vazirmatn', Tahoma, sans-serif) !important;
    direction: rtl !important;
    width: min(400px, calc(100vw - 32px)) !important;
    padding: 22px 22px 18px !important;
    border-radius: 18px !important;
    border: 1px solid rgba(0, 0, 0, 0.04) !important;
    box-shadow:
        0 10px 15px -3px rgba(0, 0, 0, 0.08),
        0 24px 48px -12px rgba(0, 0, 0, 0.16) !important;
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
    gap: 14px;
    text-align: right;
}
.bnb-confirm-icon {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 50%;
    background: #fde8ea;
    color: #e11d48;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    line-height: 1;
}
.bnb-confirm-text {
    flex: 1;
    min-width: 0;
    padding-top: 2px;
}
.bnb-confirm-title {
    font-size: 16px;
    font-weight: 700;
    color: #1f2937;
    line-height: 1.4;
    margin: 0 0 4px;
}
.bnb-confirm-msg {
    font-size: 13.5px;
    font-weight: 400;
    color: #6b7280;
    line-height: 1.55;
    margin: 0;
}
.bnb-swal-popup .swal2-actions.bnb-swal-actions {
    margin: 20px 0 0 !important;
    padding: 0 !important;
    width: 100% !important;
    display: flex !important;
    justify-content: flex-end !important; /* RTL: end = physical left */
    gap: 10px !important;
    flex-wrap: wrap;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm,
.bnb-swal-popup .swal2-styled.bnb-swal-cancel {
    margin: 0 !important;
    box-shadow: none !important;
    border-radius: 10px !important;
    font-family: inherit !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    padding: 9px 18px !important;
    line-height: 1.3 !important;
    transition: background .15s, border-color .15s, color .15s, transform .1s;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm {
    background: #ef4444 !important;
    border: 1px solid #ef4444 !important;
    color: #fff !important;
    order: 1;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm:hover {
    background: #dc2626 !important;
    border-color: #dc2626 !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-confirm:focus {
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel {
    background: #fff !important;
    border: 1px solid #e5e7eb !important;
    color: #374151 !important;
    order: 2;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel:hover {
    background: #f9fafb !important;
    border-color: #d1d5db !important;
}
.bnb-swal-popup .swal2-styled.bnb-swal-cancel:focus {
    box-shadow: 0 0 0 3px rgba(156, 163, 175, 0.25) !important;
}
.bnb-swal-popup .bnb-swal-confirm .bi {
    font-size: 14px;
    vertical-align: -1px;
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
                    /* Hard-pin container to viewport top-center (RTL-safe) */
                    var container = toast.closest('.swal2-container');
                    if (container) {
                        container.style.setProperty('inset', '0 auto auto 50%', 'important');
                        container.style.setProperty('left', '50%', 'important');
                        container.style.setProperty('right', 'auto', 'important');
                        container.style.setProperty('transform', 'translateX(-50%)', 'important');
                        container.style.setProperty('z-index', '10050', 'important');
                    }
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
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
        getToast().fire({
            icon: swalIcon,
            iconHtml: TOAST_ICONS[type] || TOAST_ICONS.success,
            title: message || '',
            customClass: {
                popup: 'bnb-swal-toast bnb-toast--' + type
            }
        });
    };

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ── 2. CONFIRM HELPER ──────────────────────────────────── */
    window.bnbConfirm = function (message, opts) {
        opts = opts || {};
        var msg = message || 'آیا مطمئن هستید؟';
        var heading = opts.titleText || opts.heading || 'تأیید عملیات';

        var defaults = {
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body">' +
                    '<div class="bnb-confirm-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>' +
                    '<div class="bnb-confirm-text">' +
                        '<div class="bnb-confirm-title">' + escapeHtml(heading) + '</div>' +
                        '<div class="bnb-confirm-msg">' + escapeHtml(msg) + '</div>' +
                    '</div>' +
                '</div>',
            showCancelButton: true,
            focusCancel: true,
            reverseButtons: false,
            buttonsStyling: true,
            confirmButtonText: '<i class="bi bi-check-lg ms-1"></i>بله',
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#ef4444',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup',
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
        var merged = Object.assign({}, defaults, opts);
        if (!opts.html) {
            merged.html = defaults.html;
            merged.title = '';
            merged.icon = undefined;
        }
        if (opts.customClass) {
            merged.customClass = Object.assign({}, defaults.customClass, opts.customClass);
        }

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

        return Swal.fire({
            title: '',
            icon: undefined,
            html:
                '<div class="bnb-confirm-body">' +
                    '<div class="bnb-confirm-icon"><i class="bi bi-chat-left-text-fill"></i></div>' +
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
            reverseButtons: false,
            confirmButtonText: confirmText,
            cancelButtonText: 'انصراف',
            confirmButtonColor: '#ef4444',
            showClass: { popup: 'swal2-show' },
            hideClass: { popup: 'swal2-hide' },
            customClass: {
                popup: 'bnb-swal-popup',
                htmlContainer: 'bnb-swal-html',
                actions: 'bnb-swal-actions',
                confirmButton: 'bnb-swal-confirm',
                cancelButton: 'bnb-swal-cancel'
            },
            didOpen: function (popup) {
                var container = popup.closest('.swal2-container') || document.querySelector('.swal2-container');
                if (container) container.style.zIndex = '10060';
                var input = popup.querySelector('.bnb-swal-prompt-input');
                if (input) {
                    setTimeout(function () { input.focus(); }, 50);
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
        });
    };

    /* ── 3. data-swal-confirm INTERCEPTOR ───────────────────── */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-swal-confirm]');
        if (!btn || btn._swalBypassed) return;

        e.preventDefault();
        e.stopImmediatePropagation();

        var message = btn.dataset.swalConfirm || 'آیا از این عملیات مطمئن هستید؟';

        bnbConfirm(message).then(function (result) {
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

        bnbConfirm(message).then(function (result) {
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

    /* ── 4. LIVEWIRE TOAST EVENTS ───────────────────────────── */
    function handleToastPayload(d) {
        if (!d) return;
        if (Array.isArray(d) && d.length) d = d[0];
        if (typeof d === 'object' && d !== null && d.detail) d = d.detail;
        if (Array.isArray(d) && d.length) d = d[0];
        bnbToast(d.type || d.icon || 'success', d.message || d.title || '');
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
