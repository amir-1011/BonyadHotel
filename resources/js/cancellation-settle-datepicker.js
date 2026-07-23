/**
 * Jalali datepicker for cancellation settlement modal.
 */
(function () {
    'use strict';

    if (window.__bonyadCancellationSettleDatepickerReady) {
        if (document.querySelector('#cancellation-settle-modal-root .cancellation-settle-jalali-date') && window.__bonyadCancellationSettleDatepickerReboot) {
            window.__bonyadCancellationSettleDatepickerReboot();
        }
        return;
    }
    window.__bonyadCancellationSettleDatepickerReady = true;

    const NS = (window.CancellationSettleDatepicker = window.CancellationSettleDatepicker || {
        ready: false,
        docBound: false,
        livewireHookBound: false,
    });

    function ensureJqueryGlobal$() {
        if (typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
    }

    function settleWire() {
        const root = document.getElementById('cancellation-settle-modal-root');
        if (!root) return null;
        const host = root.closest('[wire\\:id]');
        if (!host || typeof Livewire === 'undefined') return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function settleInputs() {
        return document.querySelectorAll('#cancellation-settle-modal-root .cancellation-settle-jalali-date');
    }

    function syncSettleDateToWire(input) {
        const wire = settleWire();
        const prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            return wire.set(prop, input.value || '');
        }
        return Promise.resolve();
    }

    function destroySettleDatepickers() {
        ensureJqueryGlobal$();
        const jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') {
            NS.ready = false;
            return;
        }

        jq(settleInputs()).each(function () {
            const $input = jq(this);
            if ($input.data('pDatepicker')) {
                try {
                    $input.pDatepicker('destroy');
                } catch (e) {
                    /* ignore */
                }
                $input.removeData('pDatepicker');
            }
        });
        NS.ready = false;
    }

    function initSettleDatepickers() {
        ensureJqueryGlobal$();
        const jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') return;
        if (!document.querySelector('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) return;

        let boundAny = false;

        jq(settleInputs()).each(function () {
            const $input = jq(this);
            if ($input.data('pDatepicker')) {
                boundAny = true;
                return;
            }

            $input.pDatepicker({
                format: 'YYYY/MM/DD',
                viewMode: 'day',
                autoClose: true,
                initialValue: false,
                initialValueType: 'persian',
                persianDigit: false,
                toolbox: {
                    enabled: true,
                    todayButton: { enabled: true },
                    submitButton: { enabled: false },
                },
                onSelect: function () {
                    const el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                    syncSettleDateToWire(el);
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
            boundAny = true;
        });

        NS.ready = boundAny;
    }

    function bootSettleDatepickers() {
        ensureJqueryGlobal$();
        NS.ready = false;
        initSettleDatepickers();
    }

    function scheduleBoot() {
        setTimeout(bootSettleDatepickers, 0);
    }

    function bindLivewireHooks() {
        if (NS.livewireHookBound || typeof Livewire === 'undefined') return;
        NS.livewireHookBound = true;

        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                if (!document.querySelector('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) return;
                ensureJqueryGlobal$();
                const jq = window.jQuery;
                if (!jq) return;
                let needsInit = false;
                jq(settleInputs()).each(function () {
                    if (!jq(this).data('pDatepicker')) {
                        needsInit = true;
                    }
                });
                if (needsInit) {
                    NS.ready = false;
                    initSettleDatepickers();
                }
            });
        });

        Livewire.on('cancellation-settle-modal-opened', () => {
            destroySettleDatepickers();
            setTimeout(bootSettleDatepickers, 50);
        });
    }

    function bindDocumentHandlers() {
        if (NS.docBound) return;
        NS.docBound = true;

        document.addEventListener(
            'blur',
            (e) => {
                if (e.target && e.target.matches && e.target.matches('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) {
                    syncSettleDateToWire(e.target);
                }
            },
            true,
        );

        document.addEventListener(
            'focus',
            (e) => {
                if (!e.target || !e.target.matches || !e.target.matches('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) return;
                ensureJqueryGlobal$();
                const jq = window.jQuery;
                if (!jq) return;
                const $input = jq(e.target);
                if ($input.data('pDatepicker')) return;
                NS.ready = false;
                initSettleDatepickers();
                if ($input.data('pDatepicker')) {
                    try {
                        $input.pDatepicker('show');
                    } catch (err) {
                        /* ignore */
                    }
                }
            },
            true,
        );
    }

    function boot() {
        bindDocumentHandlers();
        bindLivewireHooks();
        if (document.querySelector('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) {
            scheduleBoot();
        }
    }

    function reboot() {
        destroySettleDatepickers();
        scheduleBoot();
    }

    window.__bonyadCancellationSettleDatepickerReboot = reboot;

    document.addEventListener('livewire:initialized', boot);
    document.addEventListener('livewire:navigated', () => {
        if (!document.querySelector('#cancellation-settle-modal-root .cancellation-settle-jalali-date')) return;
        reboot();
    });

    if (typeof window.Livewire !== 'undefined') {
        boot();
    } else {
        bindDocumentHandlers();
    }
})();
