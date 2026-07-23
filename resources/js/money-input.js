/**
 * Thousand-separator formatting for money inputs (Persian UI, Western digits + commas).
 */

export function parseMoney(value) {
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const normalized = String(value ?? '').replace(/[۰-۹]/g, (d) => String(persian.indexOf(d)));
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
    return n.toLocaleString('en-US');
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
    });
}

window.parseMoney = parseMoney;
window.formatMoney = formatMoney;
window.initMoneyInputs = initMoneyInputs;
