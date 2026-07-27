function toggleOverviewStats(wrap) {
    const btn = wrap.querySelector('[data-overview-stats-toggle]');
    if (!btn) return;

    const hiddenCols = wrap.querySelectorAll('.admin-overview-stats__col--hidden');
    const labelCollapsed = btn.querySelector('[data-label-collapsed]');
    const labelExpanded = btn.querySelector('[data-label-expanded]');
    const expanded = wrap.classList.toggle('is-expanded');

    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    hiddenCols.forEach((col) => {
        col.hidden = !expanded;
    });

    if (labelCollapsed) labelCollapsed.hidden = expanded;
    if (labelExpanded) labelExpanded.hidden = !expanded;
}

if (window.__adminOverviewStatsBound) {
    // already registered (wire:navigate may reload the module)
} else {
    window.__adminOverviewStatsBound = true;

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-overview-stats-toggle]');
        if (!btn) return;

        event.preventDefault();

        const wrap = btn.closest('[data-overview-stats]');
        if (!wrap) return;

        toggleOverviewStats(wrap);
    });
}
