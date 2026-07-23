function clampDashboardAccFilterMenu(menu) {
    if (!menu) {
        return;
    }

    const root = menu.closest('.dashboard-acc-filter');
    const toggle = root?.querySelector('[data-bs-toggle="dropdown"]');
    const toggleRect = toggle?.getBoundingClientRect();
    const sidebar = document.getElementById('sidebar');
    const sidebarWidth = sidebar && window.innerWidth >= 992
        ? Math.ceil(sidebar.getBoundingClientRect().width)
        : 0;
    const safeRight = window.innerWidth - sidebarWidth - 12;
    const minLeft = 12;
    const gap = 8;

    menu.style.zIndex = '1065';
    menu.classList.add('dashboard-acc-filter__menu--fixed');

    if (window.innerWidth < 576) {
        menu.style.position = 'fixed';
        menu.style.left = '12px';
        menu.style.right = `${sidebarWidth + 12}px`;
        menu.style.width = 'auto';
        menu.style.minWidth = '0';
        menu.style.maxWidth = 'none';
        menu.style.transform = 'none';

        const top = toggleRect
            ? Math.min(toggleRect.bottom + gap, window.innerHeight - menu.offsetHeight - 12)
            : 12;
        menu.style.top = `${Math.max(12, top)}px`;

        return;
    }

    const width = Math.min(360, Math.max(260, safeRight - minLeft));
    let left = toggleRect
        ? Math.min(toggleRect.left, safeRight - width)
        : menu.getBoundingClientRect().left;
    left = Math.max(minLeft, Math.min(left, safeRight - width));

    let top = (toggleRect?.bottom ?? menu.getBoundingClientRect().top) + gap;
    if (top + menu.offsetHeight > window.innerHeight - 12) {
        top = Math.max(12, (toggleRect?.top ?? top) - menu.offsetHeight - gap);
    }

    menu.style.position = 'fixed';
    menu.style.left = `${left}px`;
    menu.style.right = 'auto';
    menu.style.top = `${top}px`;
    menu.style.width = `${width}px`;
    menu.style.transform = 'none';
}

function resetDashboardAccFilterMenu(menu) {
    if (!menu) {
        return;
    }

    menu.classList.remove('dashboard-acc-filter__menu--fixed');
    menu.style.position = '';
    menu.style.left = '';
    menu.style.right = '';
    menu.style.top = '';
    menu.style.width = '';
    menu.style.minWidth = '';
    menu.style.maxWidth = '';
    menu.style.transform = '';
    menu.style.zIndex = '';
}

function registerDashboardAccommodationFilter() {
    if (window.__dashboardAccFilterRegistered) {
        return;
    }

    window.__dashboardAccFilterRegistered = true;

    const register = () => {
        if (!window.Alpine) {
            return;
        }

        Alpine.data('dashboardAccommodationFilter', () => ({
            options: [],
            appliedAll: true,
            appliedIds: [],
            draftAll: true,
            draftIds: [],

            boot(appliedAll, appliedIds) {
                this.options = [...this.$root.querySelectorAll('[data-acc-id]')].map((el) => ({
                    id: Number.parseInt(el.dataset.accId || '0', 10),
                    name: el.dataset.accName || el.textContent.trim(),
                })).filter((option) => option.id > 0);

                this.appliedAll = !!appliedAll;
                this.appliedIds = [...(appliedIds || [])];
                this.resetDraft();
                this.updateButtonLabel();
                this.bindDropdownPositioning();
            },

            bindDropdownPositioning() {
                if (this.$root.dataset.accFilterBound === '1') {
                    return;
                }

                this.$root.dataset.accFilterBound = '1';

                this.$root.addEventListener('shown.bs.dropdown', () => {
                    requestAnimationFrame(() => {
                        clampDashboardAccFilterMenu(this.$refs.menu);
                    });
                });

                this.$root.addEventListener('hidden.bs.dropdown', () => {
                    resetDashboardAccFilterMenu(this.$refs.menu);
                });
            },

            allIds() {
                return this.options.map((option) => option.id);
            },

            isSelected(id) {
                if (this.draftAll) {
                    return true;
                }

                return this.draftIds.includes(id);
            },

            toggle(id) {
                const allIds = this.allIds();

                if (this.draftAll) {
                    this.draftAll = false;
                    this.draftIds = allIds.filter((itemId) => itemId !== id);
                    return;
                }

                if (this.draftIds.includes(id)) {
                    this.draftIds = this.draftIds.filter((itemId) => itemId !== id);
                    return;
                }

                this.draftIds.push(id);
                this.draftIds.sort((a, b) => a - b);

                if (this.draftIds.length === allIds.length) {
                    this.selectAll();
                }
            },

            selectAll() {
                this.draftAll = true;
                this.draftIds = [];
            },

            clear() {
                this.draftAll = false;
                this.draftIds = [];
            },

            resetDraft() {
                this.draftAll = this.appliedAll;
                this.draftIds = [...this.appliedIds];
            },

            hasPending() {
                if (this.draftAll !== this.appliedAll) {
                    return true;
                }

                if (this.draftAll) {
                    return false;
                }

                const draft = [...this.draftIds].sort((a, b) => a - b);
                const applied = [...this.appliedIds].sort((a, b) => a - b);

                if (draft.length !== applied.length) {
                    return true;
                }

                return draft.some((id, index) => id !== applied[index]);
            },

            syncFromApplied(detail) {
                this.appliedAll = !!detail.allSelected;
                this.appliedIds = [...(detail.selectedIds || [])];
                this.resetDraft();
            },

            closeMenu() {
                const toggle = this.$root.querySelector('[data-bs-toggle="dropdown"]');
                if (!toggle || !window.bootstrap?.Dropdown) {
                    return;
                }

                bootstrap.Dropdown.getInstance(toggle)?.hide();
            },

            apply() {
                if (!this.hasPending()) {
                    return;
                }

                const allSelected = this.draftAll;
                const ids = allSelected ? [] : [...this.draftIds];

                this.$wire.applyDashboardAccommodationFilterFromClient(allSelected, ids).then(() => {
                    this.appliedAll = !!this.$wire.dashboardAccommodationAllSelected;
                    this.appliedIds = [...(this.$wire.selectedAccommodationIds || [])];
                    this.resetDraft();
                    this.updateButtonLabel();
                    this.closeMenu();
                });
            },

            updateButtonLabel() {
                const label = this.$root.querySelector('.dashboard-acc-filter__label');
                if (!label) {
                    return;
                }

                const total = this.options.length;

                if (this.appliedAll) {
                    label.textContent = `همه اقامتگاه‌ها (${total})`;
                    return;
                }

                if (this.appliedIds.length === 0) {
                    label.textContent = 'هیچ اقامتگاهی انتخاب نشده';
                    return;
                }

                if (this.appliedIds.length === 1) {
                    const name = this.options.find((option) => option.id === this.appliedIds[0])?.name || '';
                    label.textContent = name.length > 32 ? `${name.slice(0, 32)}…` : name;
                    return;
                }

                label.textContent = `${this.appliedIds.length} از ${total} اقامتگاه`;
            },
        }));
    };

    document.addEventListener('alpine:init', register);

    if (window.Alpine) {
        register();
    }

    window.addEventListener('resize', () => {
        document.querySelectorAll('.dashboard-acc-filter .dropdown-menu.show').forEach((menu) => {
            clampDashboardAccFilterMenu(menu);
        });
    });
}

registerDashboardAccommodationFilter();
