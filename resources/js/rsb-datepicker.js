/**
 * Jalali datepicker for Room Status Board.
 * Loaded from the parent dashboard page (not the deferred Livewire view),
 * because #[Defer] AJAX renders discard nested @push('scripts').
 */
const NS = (window.RoomStatusBoardDatepicker = window.RoomStatusBoardDatepicker || {
    ready: false,
    docBound: false,
    livewireHookBound: false,
});

/** persian-datepicker calls global $ internally — Livewire may replace it. */
function ensureJqueryGlobal$() {
    if (typeof window.jQuery !== 'undefined') {
        window.$ = window.jQuery;
    }
}

function roomStatusWire() {
    const root = document.getElementById('room-status-board-root');
    if (!root) return null;
    const host = root.closest('[wire\\:id]');
    if (!host || typeof Livewire === 'undefined') return null;
    return Livewire.find(host.getAttribute('wire:id'));
}

function syncRoomStatusDateToWire(input) {
    const wire = roomStatusWire();
    const prop = input.getAttribute('data-wire-prop');
    if (wire && prop) {
        wire.set(prop, input.value || '');
    }
    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(input);
}

function syncRoomStatusBoardDate() {
    const input = document.getElementById('room-status-board-date');
    if (input) syncRoomStatusDateToWire(input);
}

function rsbInputs() {
    return document.querySelectorAll('#room-status-board-root .rsb-jalali-date');
}

function destroyRoomStatusDatepicker() {
    ensureJqueryGlobal$();
    const jq = window.jQuery;
    if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') {
        NS.ready = false;
        return;
    }

    jq(rsbInputs()).each(function () {
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

function initRoomStatusDatepicker() {
    ensureJqueryGlobal$();
    const jq = window.jQuery;
    if (!jq || !jq.fn || typeof jq.fn.pDatepicker !== 'function') return;
    if (!document.getElementById('room-status-board-date')) return;

    let boundAny = false;

    jq(rsbInputs()).each(function () {
        const $input = jq(this);
        if ($input.data('pDatepicker')) {
            boundAny = true;
            return;
        }

        ensureJqueryGlobal$();
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
                syncRoomStatusDateToWire(el);
            },
        });
        boundAny = true;
    });

    NS.ready = boundAny;
}

function bootRoomStatusDatepicker() {
    ensureJqueryGlobal$();
    NS.ready = false;
    initRoomStatusDatepicker();
}

function scheduleBoot() {
    setTimeout(bootRoomStatusDatepicker, 0);
}

function bindLivewireHooks() {
    if (NS.livewireHookBound || typeof Livewire === 'undefined') return;
    NS.livewireHookBound = true;

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            if (!document.getElementById('room-status-board-date')) return;
            ensureJqueryGlobal$();
            const jq = window.jQuery;
            if (!jq) return;
            const $input = jq('#room-status-board-date');
            if ($input.length && !$input.data('pDatepicker')) {
                NS.ready = false;
                initRoomStatusDatepicker();
            }
        });
    });
}

function bindDocumentHandlers() {
    if (NS.docBound) return;
    NS.docBound = true;

    document.addEventListener(
        'blur',
        (e) => {
            if (e.target && e.target.matches && e.target.matches('#room-status-board-root .rsb-jalali-date')) {
                syncRoomStatusDateToWire(e.target);
            }
        },
        true,
    );

    document.addEventListener(
        'focus',
        (e) => {
            if (!e.target || !e.target.matches || !e.target.matches('#room-status-board-root .rsb-jalali-date')) return;
            ensureJqueryGlobal$();
            const jq = window.jQuery;
            if (!jq) return;
            const $input = jq(e.target);
            if ($input.data('pDatepicker')) return;
            NS.ready = false;
            initRoomStatusDatepicker();
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

    document.addEventListener('click', (e) => {
        const btn = e.target.closest && e.target.closest('.room-status-board-clear-date');
        if (!btn) return;
        const targetId = btn.getAttribute('data-target');
        const input = targetId ? document.getElementById(targetId) : null;
        if (!input) return;
        input.value = '';
        syncRoomStatusDateToWire(input);
    });
}

function boot() {
    bindDocumentHandlers();
    bindLivewireHooks();
    scheduleBoot();
}

window.syncRoomStatusBoardDate = syncRoomStatusBoardDate;

document.addEventListener('livewire:initialized', boot);
document.addEventListener('livewire:navigated', () => {
    if (!document.getElementById('room-status-board-date')) return;
    destroyRoomStatusDatepicker();
    scheduleBoot();
});

if (typeof window.Livewire !== 'undefined') {
    boot();
} else {
    bindDocumentHandlers();
}
