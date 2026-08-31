/**
 * Jalali datepicker for program booking wizard (step 1).
 * Loaded from the parent create page — nested Livewire @push('scripts') is unreliable.
 * Wrapped in IIFE so duplicate <script> tags (wire:navigate) do not redeclare top-level bindings.
 */
(function () {
    'use strict';

    if (window.__bonyadProgramDatepickerReady) {
        if (document.querySelector('#program-form-root .jalali-picker-program') && window.__bonyadProgramDatepickerReboot) {
            window.__bonyadProgramDatepickerReboot();
        }
        return;
    }
    window.__bonyadProgramDatepickerReady = true;

    const NS = (window.ProgramDatepicker = window.ProgramDatepicker || {
        ready: false,
        docBound: false,
        livewireHookBound: false,
    });

    function ensureJqueryGlobal$() {
        if (typeof window.jQuery !== 'undefined') {
            window.$ = window.jQuery;
        }
    }

    function programWire() {
        const root = document.getElementById('program-form-root');
        if (!root) return null;
        const host = root.closest('[wire\\:id]');
        if (!host || typeof Livewire === 'undefined') return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function programInputs() {
        return document.querySelectorAll('#program-form-root .jalali-picker-program');
    }

    function syncProgramDateToWire(input) {
        const wire = programWire();
        const prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            return wire.set(prop, input.value || '');
        }
        return Promise.resolve();
    }

    async function syncAllProgramDates() {
        const inputs = programInputs();
        const tasks = [];
        inputs.forEach((input) => {
            tasks.push(syncProgramDateToWire(input));
        });
        await Promise.all(tasks);
    }

    function destroyProgramDatepickers() {
        ensureJqueryGlobal$();
        const jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') {
            NS.ready = false;
            return;
        }

        jq(programInputs()).each(function () {
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

    function initProgramDatepickers() {
        ensureJqueryGlobal$();
        const jq = window.jQuery;
        if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') return;
        if (!document.querySelector('#program-form-root .jalali-picker-program')) return;

        let boundAny = false;

        jq(programInputs()).each(function () {
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
                persianDigit: true,
                toolbox: {
                    enabled: true,
                    todayButton: { enabled: true },
                    submitButton: { enabled: false },
                },
                onSelect: function () {
                    const el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                    syncProgramDateToWire(el);
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
            boundAny = true;
        });

        NS.ready = boundAny;
    }

    function bootProgramDatepickers() {
        ensureJqueryGlobal$();
        NS.ready = false;
        initProgramDatepickers();
    }

    function scheduleBoot() {
        setTimeout(bootProgramDatepickers, 0);
    }

    function bindLivewireHooks() {
        if (NS.livewireHookBound || typeof Livewire === 'undefined') return;
        NS.livewireHookBound = true;

        Livewire.hook('commit', ({ succeed }) => {
            succeed(() => {
                if (!document.querySelector('#program-form-root .jalali-picker-program')) return;
                ensureJqueryGlobal$();
                const jq = window.jQuery;
                if (!jq) return;
                let needsInit = false;
                jq(programInputs()).each(function () {
                    if (!jq(this).data('pDatepicker')) {
                        needsInit = true;
                    }
                });
                if (needsInit) {
                    NS.ready = false;
                    initProgramDatepickers();
                }
            });
        });

        Livewire.on('program-step-changed', (payload) => {
            const step = payload && payload.step !== undefined ? Number(payload.step) : 0;
            if (step !== 1) return;
            destroyProgramDatepickers();
            setTimeout(bootProgramDatepickers, 50);
        });
    }

    function bindDocumentHandlers() {
        if (NS.docBound) return;
        NS.docBound = true;

        document.addEventListener(
            'blur',
            (e) => {
                if (e.target && e.target.matches && e.target.matches('#program-form-root .jalali-picker-program')) {
                    syncProgramDateToWire(e.target);
                }
            },
            true,
        );

        document.addEventListener(
            'focus',
            (e) => {
                if (!e.target || !e.target.matches || !e.target.matches('#program-form-root .jalali-picker-program')) return;
                ensureJqueryGlobal$();
                const jq = window.jQuery;
                if (!jq) return;
                const $input = jq(e.target);
                if ($input.data('pDatepicker')) return;
                NS.ready = false;
                initProgramDatepickers();
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
        scheduleBoot();
    }

    function reboot() {
        destroyProgramDatepickers();
        scheduleBoot();
    }

    window.syncProgramDates = syncAllProgramDates;
    window.__bonyadProgramDatepickerReboot = reboot;

    document.addEventListener('livewire:initialized', boot);
    document.addEventListener('livewire:navigated', () => {
        if (!document.querySelector('#program-form-root .jalali-picker-program')) return;
        reboot();
    });

    if (typeof window.Livewire !== 'undefined') {
        boot();
    } else {
        bindDocumentHandlers();
    }
})();
