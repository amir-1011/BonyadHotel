/**
 * Keep Bootstrap Collapse working across Livewire wire:navigate.
 * Root cause: without data-navigate-once, bootstrap.bundle re-registers
 * document click handlers and even toggles cancel each other out.
 * This helper also disposes stale instances after body swaps.
 */

function resetCollapseTransitions(root = document) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) return;

    root.querySelectorAll('.collapse').forEach((el) => {
        el.classList.remove('collapsing');
        el.style.removeProperty('height');
        const inst = bootstrap.Collapse.getInstance(el);
        if (inst) inst.dispose();
    });
}

function syncCollapseTriggers(root = document) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) return;

    root.querySelectorAll('[data-bs-toggle="collapse"]').forEach((trigger) => {
        delete trigger.dataset.bonyadCollapseBound;

        const selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
        if (!selector || selector === '#') return;

        const target = document.querySelector(selector);
        if (!target) return;

        bootstrap.Collapse.getInstance(target)?.dispose();
        const isOpen = target.classList.contains('show');
        trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
}

function bindCollapseTriggers(root = document) {
    if (typeof bootstrap === 'undefined' || !bootstrap.Collapse) return;

    root.querySelectorAll('[data-bs-toggle="collapse"]').forEach((trigger) => {
        if (trigger.dataset.bonyadCollapseBound === '1') return;
        trigger.dataset.bonyadCollapseBound = '1';

        trigger.addEventListener('click', (event) => {
            // Let nested interactive controls (links/buttons/inputs) keep their own behavior.
            const nested = event.target.closest('a, button, input, select, textarea');
            if (nested && nested !== trigger) return;

            const selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
            if (!selector || selector === '#') return;

            const target = document.querySelector(selector);
            if (!target || typeof bootstrap === 'undefined') return;

            // Stop Bootstrap's data-api so stacked listeners cannot double-toggle.
            event.preventDefault();
            event.stopPropagation();
            bootstrap.Collapse.getOrCreateInstance(target).toggle();
        });
    });
}

function refreshBootstrapCollapses() {
    resetCollapseTransitions();
    syncCollapseTriggers();
    bindCollapseTriggers();
}

document.addEventListener('livewire:navigating', () => resetCollapseTransitions());
document.addEventListener('livewire:navigated', () => refreshBootstrapCollapses());

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => refreshBootstrapCollapses());
} else {
    refreshBootstrapCollapses();
}

window.bonyadRefreshBootstrapCollapses = refreshBootstrapCollapses;
