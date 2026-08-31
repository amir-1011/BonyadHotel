/**
 * Highlight jalali date inputs when the selected value is today.
 * Also exposes helpers for booking calendars (gregorian today check).
 */
import { autoConvertDigitsToEN } from '@persian-tools/persian-tools';

function normalizeJalaliDigits(str) {
    if (!str) return '';
    return autoConvertDigitsToEN(String(str).trim());
}

function todayJalali() {
    if (typeof persianDate !== 'undefined') {
        return new persianDate().format('YYYY/MM/DD');
    }
    return null;
}

function todayGregorian() {
    const d = new Date();
    return d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');
}

function isJalaliToday(value) {
    const t = todayJalali();
    if (!t || !value) return false;
    return normalizeJalaliDigits(value) === normalizeJalaliDigits(t);
}

function isGregorianToday(greg) {
    return !!greg && greg === todayGregorian();
}

const INPUT_SELECTORS = [
    '.jalali-picker-booking',
    '.jalali-picker-commission',
    '.jalali-picker-program',
    '.rsb-jalali-date',
    '.cancellation-settle-jalali-date',
    '.blocked-date-input',
    '#daily-availability-form [name=date_from]',
    '#daily-availability-form [name=date_to]',
].join(',');

function syncInputTodayClass(input) {
    if (!input || input.type === 'hidden') return;
    input.classList.toggle('jalali-date-is-today', isJalaliToday(input.value));
}

function syncAllInputs() {
    document.querySelectorAll(INPUT_SELECTORS).forEach(syncInputTodayClass);
}

function bindInput(input) {
    if (!input || input.dataset.jalaliTodayBound === '1') return;
    input.dataset.jalaliTodayBound = '1';
    ['input', 'change', 'blur'].forEach((ev) => {
        input.addEventListener(ev, () => syncInputTodayClass(input));
    });
    syncInputTodayClass(input);
}

function bindAllInputs(root = document) {
    root.querySelectorAll(INPUT_SELECTORS).forEach(bindInput);
}

function wrapOnSelect(original, inputEl) {
    return function wrappedOnSelect() {
        if (typeof original === 'function') {
            original.apply(this, arguments);
        }
        const el = inputEl || (this.model && this.model.inputElement);
        if (el) syncInputTodayClass(el);
    };
}

function boot() {
    bindAllInputs();
}

window.BonyadJalaliDate = {
    normalizeJalaliDigits,
    todayJalali,
    todayGregorian,
    isJalaliToday,
    isGregorianToday,
    syncInputTodayClass,
    syncAllInputs,
    bindInput,
    bindAllInputs,
    wrapOnSelect,
    INPUT_SELECTORS,
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

document.addEventListener('livewire:navigated', () => {
    bindAllInputs();
});
