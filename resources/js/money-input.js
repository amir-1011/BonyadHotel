/**
 * Thousand-separator formatting for money inputs (Persian digits).
 *
 * This file is loaded as a classic script in admin/host layouts (no type="module").
 * Do not add ESM imports here — Vite would emit `import` and the browser will throw.
 */
function toEnDigits(value) {
    if (window.BonyadDigits && typeof window.BonyadDigits.toEn === 'function') {
        return window.BonyadDigits.toEn(value);
    }

    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const arabic = '٠١٢٣٤٥٦٧٨٩';

    return String(value ?? '')
        .replace(/[۰-۹]/g, (d) => String(persian.indexOf(d)))
        .replace(/[٠-٩]/g, (d) => String(arabic.indexOf(d)));
}

export function parseMoney(value) {
    const normalized = toEnDigits(value);
    const digits = normalized.replace(/[^\d]/g, '');
    return digits === '' ? 0 : parseInt(digits, 10);
}

export function formatMoney(value) {
    if (value === '' || value === null || value === undefined) {
        return '';
    }
    const n = parseInt(value, 10);
    if (Number.isNaN(n)) {
        return '';
    }
    return n.toLocaleString('fa-IR');
}

function registerAlpineMoneyInput() {
    if (!window.Alpine) {
        return;
    }

    window.Alpine.data('moneyInputWire', (property, isLive = false) => ({
        display: '',

        init() {
            this.refreshDisplay();

            if (typeof this.$wire?.$watch === 'function') {
                this.$wire.$watch(property, (value) => {
                    const formatted = formatMoney(value ?? 0);
                    if (this.display !== formatted) {
                        this.display = formatted;
                    }
                });
            }
        },

        refreshDisplay() {
            const val = this.$wire?.get(property);
            this.display = formatMoney(val ?? 0);
        },

        onInput() {
            const parsed = parseMoney(this.display);
            this.display = formatMoney(parsed);
            this.pushToWire(parsed);
        },

        onBlur() {
            if (!isLive) {
                this.pushToWire(parseMoney(this.display));
            }
        },

        pushToWire(parsed) {
            if (!this.$wire) {
                return;
            }
            // Update Livewire client state without a network round-trip per keystroke.
            this.$wire.set(property, parsed, false);
        },
    }));
}

function bindPlainMoneyInput(el) {
    if (el.dataset.moneyBound === '1') {
        return;
    }
    el.dataset.moneyBound = '1';
    el.type = 'text';
    el.inputMode = 'numeric';
    el.autocomplete = 'off';
    if (!el.getAttribute('dir')) {
        el.dir = 'ltr';
    }

    if (el.value) {
        el.value = formatMoney(parseMoney(el.value));
    }

    el.addEventListener('input', () => {
        const parsed = parseMoney(el.value);
        el.value = formatMoney(parsed);
    });
}

export function initMoneyInputs(root = document) {
    root.querySelectorAll('input.money-input').forEach(bindPlainMoneyInput);
}

function wireMoneyInputIsHealthy(el) {
    const input = el.querySelector('input[type="text"]');
    if (!input || !el._x_dataStack?.length) {
        return false;
    }

    try {
        const data = window.Alpine.$data(el);
        return data && Object.prototype.hasOwnProperty.call(data, 'display');
    } catch {
        return false;
    }
}

function initWireMoneyInputTrees(root = document) {
    if (!window.Alpine) {
        return;
    }

    registerAlpineMoneyInput();

    const scope = root instanceof Element ? root : document;
    const nodes = typeof scope.matches === 'function' && scope.matches('[x-data*="moneyInputWire"]')
        ? [scope]
        : [...scope.querySelectorAll('[x-data*="moneyInputWire"]')];

    nodes.forEach((el) => {
        if (el._x_dataStack?.length && typeof window.Alpine.destroyTree === 'function') {
            if (wireMoneyInputIsHealthy(el)) {
                return;
            }

            window.Alpine.destroyTree(el);
        }

        window.Alpine.initTree(el);
    });
}

function bindLivewireMoneyInputHooks() {
    if (document.body.dataset.moneyLivewireBound === '1') {
        return;
    }
    document.body.dataset.moneyLivewireBound = '1';

    const scan = ({ el }) => initWireMoneyInputTrees(el);

    document.addEventListener('livewire:init', () => {
        registerAlpineMoneyInput();
        window.Livewire.hook('morph.added', scan);
        window.Livewire.hook('morph.updated', scan);
    });

    document.addEventListener('livewire:initialized', () => {
        if (typeof window.Livewire?.on !== 'function') {
            return;
        }

        window.Livewire.on('cancellation-request-modal-opened', () => {
            requestAnimationFrame(() => initWireMoneyInputTrees());
        });

        window.Livewire.on('cancellation-settle-modal-opened', () => {
            requestAnimationFrame(() => initWireMoneyInputTrees());
        });
    });

    document.addEventListener('livewire:updated', () => initWireMoneyInputTrees());
}

function stripMoneyInputsOnSubmit(event) {
    const form = event.target;
    if (!form || form.tagName !== 'FORM') {
        return;
    }

    form.querySelectorAll('input.money-input').forEach((el) => {
        const parsed = parseMoney(el.value);
        // Empty field must not submit "0" — that was stored as custom_price=0 and zeroed nightly rates.
        el.value = parsed > 0 ? String(parsed) : '';
    });
}

function bootMoneyInput() {
    registerAlpineMoneyInput();
    initMoneyInputs();
    initWireMoneyInputTrees();
    bindLivewireMoneyInputHooks();

    if (!document.body.dataset.moneySubmitBound) {
        document.body.dataset.moneySubmitBound = '1';
        document.addEventListener('submit', stripMoneyInputsOnSubmit, true);
    }
}

// Avoid double-binding when Livewire re-evaluates this classic script on navigate.
if (!window.__bonyadMoneyInputReady) {
    window.__bonyadMoneyInputReady = true;

    if (window.Alpine) {
        registerAlpineMoneyInput();
    } else {
        document.addEventListener('alpine:init', registerAlpineMoneyInput);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootMoneyInput);
    } else {
        bootMoneyInput();
    }

    document.addEventListener('livewire:navigated', () => {
        registerAlpineMoneyInput();
        initMoneyInputs();
        initWireMoneyInputTrees();
    });
}

window.parseMoney = parseMoney;
window.formatMoney = formatMoney;
window.initMoneyInputs = initMoneyInputs;
