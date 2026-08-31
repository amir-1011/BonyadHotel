/**
 * Site-wide Persian digits (۰۱۲۳۴۵۶۷۸۹) for visible UI.
 * Storage, JSON payloads, and form values stay English via request middleware.
 */
import {
    autoConvertDigitsToEN,
    digitsArToFa,
    digitsEnToFa,
} from '@persian-tools/persian-tools';

const SKIP_SELECTOR = 'script, style, textarea, noscript, iframe, [data-latin-digits], [contenteditable="true"]';

function toFa(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const str = typeof value === 'number' ? String(value) : String(value);
    if (str === '' || ! /[0-9٠-٩]/.test(str)) {
        return str;
    }

    try {
        return digitsArToFa(digitsEnToFa(str));
    } catch {
        return str;
    }
}

function toEn(value) {
    if (value === null || value === undefined) {
        return '';
    }

    const str = String(value);
    if (str === '') {
        return '';
    }

    try {
        return autoConvertDigitsToEN(str);
    } catch {
        return str;
    }
}

function formatNumber(value, options) {
    const numeric = typeof value === 'number' ? value : Number(toEn(value));
    if (! Number.isFinite(numeric)) {
        return toFa(value);
    }

    return new Intl.NumberFormat('fa-IR', options).format(numeric);
}

function isSkippedElement(el) {
    if (! el || el.nodeType !== Node.ELEMENT_NODE) {
        return true;
    }
    if (el.matches?.(SKIP_SELECTOR) || el.closest?.(SKIP_SELECTOR)) {
        return true;
    }
    if (el.isContentEditable) {
        return true;
    }
    const tag = el.tagName;
    return tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA' || tag === 'NOSCRIPT' || tag === 'IFRAME';
}

function convertTextNode(node) {
    if (! node || node.nodeType !== Node.TEXT_NODE) {
        return;
    }
    const parent = node.parentElement;
    if (! parent || isSkippedElement(parent)) {
        return;
    }
    const current = node.nodeValue;
    if (! current || ! /[0-9٠-٩]/.test(current)) {
        return;
    }
    const next = toFa(current);
    if (next !== current) {
        node.nodeValue = next;
    }
}

function walk(root) {
    if (! root) {
        return;
    }
    if (root.nodeType === Node.TEXT_NODE) {
        convertTextNode(root);
        return;
    }
    if (root.nodeType !== Node.ELEMENT_NODE || isSkippedElement(root)) {
        return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (! parent || isSkippedElement(parent)) {
                return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const nodes = [];
    let current = walker.nextNode();
    while (current) {
        nodes.push(current);
        current = walker.nextNode();
    }
    nodes.forEach(convertTextNode);
}

function patchToLocaleString() {
    if (Number.prototype.__bonyadFaDigitsPatched) {
        return;
    }

    const original = Number.prototype.toLocaleString;
    Number.prototype.toLocaleString = function patchedToLocaleString(locales, options) {
        if (locales == null || locales === 'en' || locales === 'en-US' || locales === 'en-GB') {
            return original.call(this, 'fa-IR', options);
        }
        return original.call(this, locales, options);
    };
    Number.prototype.__bonyadFaDigitsPatched = true;
}

let digitWalkQueued = new Set();
let digitWalkRaf = 0;
let digitWalkApplying = false;

function scheduleDigitWalk(node) {
    if (! node || digitWalkApplying) {
        return;
    }
    digitWalkQueued.add(node);
    if (digitWalkRaf) {
        return;
    }
    digitWalkRaf = requestAnimationFrame(() => {
        digitWalkRaf = 0;
        const batch = digitWalkQueued;
        digitWalkQueued = new Set();
        digitWalkApplying = true;
        try {
            batch.forEach((item) => walk(item));
        } finally {
            digitWalkApplying = false;
        }
    });
}

function observeDigits() {
    if (typeof MutationObserver === 'undefined' || document.documentElement.dataset.bonyadFaDigitsObserve === '1') {
        return;
    }
    document.documentElement.dataset.bonyadFaDigitsObserve = '1';

    const observer = new MutationObserver((mutations) => {
        if (digitWalkApplying) {
            return;
        }
        for (const mutation of mutations) {
            mutation.addedNodes.forEach((node) => scheduleDigitWalk(node));
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });
}

function boot() {
    patchToLocaleString();
    walk(document.body);
    observeDigits();
}

window.BonyadDigits = {
    toFa,
    toEn,
    formatNumber,
};

if (! window.__bonyadFaDigitsReady) {
    window.__bonyadFaDigitsReady = true;
    patchToLocaleString();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    document.addEventListener('livewire:navigated', () => scheduleDigitWalk(document.body));
}

export { toFa, toEn, formatNumber };
