{{--
    Global Button Loading Spinner
    ─────────────────────────────
    Include this partial just before </body> in every layout.
    Covers:
      • wire:click  Livewire action buttons
      • type="submit"  classic form submit buttons
      • [data-async-btn]  Alpine.js buttons that call form.submit()
--}}
<style>
/* ─── Button Loading Spinner ──────────────────────────────────────────── */
.btn-loading,
.btn-loading:hover,
.btn-loading:focus,
.btn-loading:active {
    position: relative !important;
    pointer-events: none !important;
    color: transparent !important;
    text-shadow: none !important;
}
.btn-loading * {
    visibility: hidden !important;
}
.btn-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 1.1em;
    height: 1.1em;
    margin-top: -0.55em;
    margin-left: -0.55em;
    border: 0.16em solid var(--_bsp, currentColor);
    border-right-color: transparent;
    border-radius: 50%;
    animation: _btnSpin 0.65s linear infinite;
    box-sizing: border-box;
}
@keyframes _btnSpin {
    to { transform: rotate(360deg); }
}
</style>
<script>
(function () {
    'use strict';

    /** Set of buttons currently in loading state */
    const _active = new Set();

    function _start(btn) {
        if (!btn || btn.classList.contains('btn-loading')) return;
        /* Capture text colour before making it transparent */
        btn.style.setProperty('--_bsp', window.getComputedStyle(btn).color);
        btn.classList.add('btn-loading');
        _active.add(btn);
    }

    function _stop(btn) {
        if (!btn) return;
        btn.classList.remove('btn-loading');
        btn.style.removeProperty('--_bsp');
        _active.delete(btn);
    }

    function _stopAll() {
        _active.forEach(_stop);
    }

    /* ── 1. wire:click buttons ──────────────────────────────────────── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[wire\\:click]');
        if (!btn) return;
        _start(btn);
        /*
         * Safety valve: if no Livewire commit fires within 600 ms
         * (e.g. user clicked "Cancel" in a confirm dialog),
         * revert the loading state automatically.
         */
        btn._lwt = setTimeout(function () { _stop(btn); }, 600);
    }, true /* capture phase — fires before onclick confirm dialogs */);

    /* ── 2. data-async-btn (Alpine.js form-submit buttons) ──────────── */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-async-btn]');
        if (!btn) return;
        _start(btn);
        /* Page navigates away on its own — no manual restore needed */
    });

    /* ── 3. Traditional form submit buttons ─────────────────────────── */
    document.addEventListener('submit', function (e) {
        const btn = e.target.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
        if (btn) _start(btn);
    });

    /* ── Livewire commit lifecycle hooks ────────────────────────────── */
    function _attachHooks() {
        if (typeof Livewire === 'undefined') return;
        Livewire.hook('commit', function (info) {
            /* A real Livewire request started — cancel the 600ms safety timers */
            _active.forEach(function (b) {
                if (b._lwt) { clearTimeout(b._lwt); delete b._lwt; }
            });
            info.succeed(function () { setTimeout(_stopAll, 80); });
            info.fail(function ()    { setTimeout(_stopAll, 80); });
        });
    }

    /* Livewire may or may not be initialised when this script runs */
    document.addEventListener('livewire:initialized', _attachHooks);
    if (typeof Livewire !== 'undefined') _attachHooks();

    /* ── Restore on navigation / page reload ────────────────────────── */
    document.addEventListener('livewire:navigated', _stopAll);
    window.addEventListener('pageshow', _stopAll);
})();
</script>
