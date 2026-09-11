function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function bnbConfirmDelete(message) {
    if (typeof window.bnbConfirm === 'function') {
        const result = await window.bnbConfirm(message);
        return Boolean(result.isConfirmed);
    }

    return window.confirm(message);
}

function showHallFormError(message) {
    if (typeof window.bnbToast === 'function') {
        window.bnbToast('error', message);
        return;
    }

    window.alert(message);
}

const hallCatalogApi = {
    async delete(url) {
        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload?.message || 'حذف انجام نشد.');
        }

        return payload;
    },

    async post(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = payload?.message || payload?.errors?.name?.[0] || 'عملیات انجام نشد.';
            throw new Error(message);
        }

        return payload;
    },

    async patch(url, body) {
        const response = await fetch(url, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = payload?.message || payload?.errors?.name?.[0] || 'عملیات انجام نشد.';
            throw new Error(message);
        }

        return payload;
    },
};

function bindHallTypes(form) {
    const select = form.querySelector('[data-hall-type-select]');
    const catalog = form.querySelector('[data-hall-types-catalog]');
    const toggleBtn = form.querySelector('[data-action="toggle-add-hall-type"]');
    const panel = form.querySelector('[data-hall-type-add-panel]');
    const input = form.querySelector('[data-hall-type-new-input]');
    const errorEl = form.querySelector('[data-hall-type-error]');
    const confirmBtn = form.querySelector('[data-action="confirm-add-hall-type"]');
    const cancelBtn = form.querySelector('[data-action="cancel-add-hall-type"]');
    const storeUrl = form.dataset.typeStoreUrl;
    const updateBaseUrl = form.dataset.typeUpdateUrl;
    const destroyBaseUrl = form.dataset.typeDestroyUrl;

    if (!select || !catalog || !toggleBtn || !panel || !input || !storeUrl || !updateBaseUrl || !destroyBaseUrl) {
        return;
    }

    const existingValues = () =>
        Array.from(select.options).map((opt) => opt.textContent.trim()).filter(Boolean);

    const showError = (msg) => {
        if (!errorEl) {
            return;
        }
        if (msg) {
            errorEl.textContent = msg;
            errorEl.classList.remove('d-none');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
        }
    };

    const closePanel = () => {
        panel.classList.add('d-none');
        toggleBtn.classList.remove('d-none');
        input.value = '';
        showError('');
    };

    const openPanel = () => {
        panel.classList.remove('d-none');
        toggleBtn.classList.add('d-none');
        input.focus();
        showError('');
    };

    const addTypeUi = (id, name, canDelete, canEdit) => {
        if (select.querySelector(`option[value="${id}"]`)) {
            return;
        }

        const option = document.createElement('option');
        option.value = String(id);
        option.dataset.typeId = String(id);
        option.textContent = name;
        option.selected = true;
        select.appendChild(option);

        const pill = document.createElement('span');
        pill.className = 'rt-catalog-pill';
        pill.dataset.typeId = String(id);
        pill.innerHTML = `<span class="rt-catalog-pill__label">${name}</span>`;

        if (canEdit) {
            const renameBtn = document.createElement('button');
            renameBtn.type = 'button';
            renameBtn.className = 'rt-catalog-pill__rename';
            renameBtn.dataset.action = 'rename-hall-type';
            renameBtn.dataset.typeId = String(id);
            renameBtn.dataset.typeName = name;
            renameBtn.title = 'تغییر نام';
            renameBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            pill.appendChild(renameBtn);
        }

        if (canDelete) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'rt-catalog-pill__remove';
            removeBtn.dataset.action = 'remove-hall-type';
            removeBtn.dataset.typeId = String(id);
            removeBtn.dataset.typeName = name;
            removeBtn.title = 'حذف از لیست سراسری';
            removeBtn.innerHTML = '<i class="bi bi-x-lg"></i>';
            pill.appendChild(removeBtn);
        }

        catalog.appendChild(pill);
    };

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', async () => {
        const name = (input.value || '').trim();
        if (!name) {
            showError('نام نوع سالن را وارد کنید.');
            return;
        }

        if (existingValues().includes(name)) {
            showError('این نوع قبلاً در لیست وجود دارد.');
            return;
        }

        confirmBtn.disabled = true;
        try {
            const payload = await hallCatalogApi.post(storeUrl, { name });
            addTypeUi(payload.id, payload.name, Boolean(payload.can_delete), Boolean(payload.can_edit));
            closePanel();
        } catch (error) {
            showError(error.message || 'افزودن نوع سالن انجام نشد.');
        } finally {
            confirmBtn.disabled = false;
        }
    });

    catalog.addEventListener('click', async (event) => {
        const removeBtn = event.target.closest('[data-action="remove-hall-type"]');
        const renameBtn = event.target.closest('[data-action="rename-hall-type"]');

        if (removeBtn) {
            const id = removeBtn.dataset.typeId;
            const name = removeBtn.dataset.typeName;
            const confirmed = await bnbConfirmDelete(`نوع «${name}» از لیست سراسری حذف شود؟`);
            if (!confirmed) {
                return;
            }
            try {
                await hallCatalogApi.delete(`${destroyBaseUrl}/${id}`);
                catalog.querySelector(`[data-type-id="${id}"]`)?.remove();
                select.querySelector(`option[value="${id}"]`)?.remove();
            } catch (error) {
                showHallFormError(error.message || 'حذف انجام نشد.');
            }
            return;
        }

        if (renameBtn) {
            const id = renameBtn.dataset.typeId;
            const current = renameBtn.dataset.typeName || '';
            const next = window.prompt('نام جدید نوع سالن', current);
            if (next === null) {
                return;
            }
            const name = next.trim();
            if (!name || name === current) {
                return;
            }
            try {
                const payload = await hallCatalogApi.patch(`${updateBaseUrl}/${id}`, { name });
                const option = select.querySelector(`option[value="${id}"]`);
                if (option) {
                    option.textContent = payload.name;
                }
                const pill = catalog.querySelector(`[data-type-id="${id}"]`);
                if (pill) {
                    const label = pill.querySelector('.rt-catalog-pill__label');
                    if (label) {
                        label.textContent = payload.name;
                    }
                    pill.querySelectorAll('[data-type-name]').forEach((el) => {
                        el.dataset.typeName = payload.name;
                    });
                }
            } catch (error) {
                showHallFormError(error.message || 'ویرایش انجام نشد.');
            }
        }
    });
}

function bindHallAmenities(form) {
    const grid = form.querySelector('[data-amenities-grid]');
    const toggleBtn = form.querySelector('[data-action="toggle-add-amenity"]');
    const panel = form.querySelector('[data-amenity-add-panel]');
    const input = form.querySelector('[data-amenity-new-input]');
    const errorEl = form.querySelector('[data-amenity-error]');
    const confirmBtn = form.querySelector('[data-action="confirm-add-amenity"]');
    const cancelBtn = form.querySelector('[data-action="cancel-add-amenity"]');
    const storeUrl = form.dataset.amenityStoreUrl;
    const destroyBaseUrl = form.dataset.amenityDestroyUrl;

    if (!grid || !toggleBtn || !panel || !input || !storeUrl || !destroyBaseUrl) {
        return;
    }

    const existingValues = () =>
        Array.from(grid.querySelectorAll('input[name="amenities[]"]')).map((el) => el.value);

    const showError = (msg) => {
        if (!errorEl) {
            return;
        }
        if (msg) {
            errorEl.textContent = msg;
            errorEl.classList.remove('d-none');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('d-none');
        }
    };

    const closePanel = () => {
        panel.classList.add('d-none');
        toggleBtn.classList.remove('d-none');
        input.value = '';
        showError('');
    };

    const openPanel = () => {
        panel.classList.remove('d-none');
        toggleBtn.classList.add('d-none');
        input.focus();
        showError('');
    };

    const addAmenityTile = (id, name, checked = true, canDelete = true) => {
        if (document.querySelector(`[data-amenity-id="${id}"]`)) {
            return;
        }

        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';
        col.dataset.amenityId = String(id);

        const tile = document.createElement('label');
        tile.className = 'rt-amenity-tile';

        const checkbox = document.createElement('input');
        checkbox.className = 'rt-amenity-input';
        checkbox.type = 'checkbox';
        checkbox.name = 'amenities[]';
        checkbox.value = name;
        checkbox.id = `ham_${id}`;
        checkbox.checked = checked;

        const label = document.createElement('span');
        label.className = 'rt-amenity-tile__label';
        label.textContent = name;

        const checkIcon = document.createElement('i');
        checkIcon.className = 'bi bi-check-circle-fill rt-amenity-tile__check';
        checkIcon.setAttribute('aria-hidden', 'true');

        tile.appendChild(checkbox);
        tile.appendChild(label);
        tile.appendChild(checkIcon);
        col.appendChild(tile);

        if (canDelete) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rt-amenity-remove';
            btn.dataset.action = 'remove-amenity';
            btn.dataset.amenityId = String(id);
            btn.dataset.amenityName = name;
            btn.title = 'حذف از لیست سراسری';
            btn.innerHTML = '<i class="bi bi-x-lg"></i>';
            col.appendChild(btn);
        }

        grid.appendChild(col);
    };

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', async () => {
        const name = (input.value || '').trim();
        if (!name) {
            showError('نام امکان را وارد کنید.');
            return;
        }
        if (existingValues().includes(name)) {
            showError('این امکان قبلاً در لیست وجود دارد.');
            return;
        }

        confirmBtn.disabled = true;
        try {
            const payload = await hallCatalogApi.post(storeUrl, { name });
            addAmenityTile(payload.id, payload.name, true, Boolean(payload.can_delete));
            closePanel();
        } catch (error) {
            showError(error.message || 'افزودن امکان انجام نشد.');
        } finally {
            confirmBtn.disabled = false;
        }
    });

    grid.addEventListener('click', async (event) => {
        const removeBtn = event.target.closest('[data-action="remove-amenity"]');
        if (!removeBtn) {
            return;
        }

        const id = removeBtn.dataset.amenityId;
        const name = removeBtn.dataset.amenityName;
        const confirmed = await bnbConfirmDelete(`امکان «${name}» از لیست سراسری حذف شود؟`);
        if (!confirmed) {
            return;
        }

        try {
            await hallCatalogApi.delete(`${destroyBaseUrl}/${id}`);
            document.querySelectorAll(`[data-amenity-id="${id}"]`).forEach((el) => el.remove());
        } catch (error) {
            showHallFormError(error.message || 'حذف انجام نشد.');
        }
    });
}

function bindHallForm(root = document) {
    root.querySelectorAll('[data-hall-form]').forEach((form) => {
        if (form.dataset.hallFormBound === '1') {
            return;
        }
        form.dataset.hallFormBound = '1';
        bindHallTypes(form);
        bindHallAmenities(form);
    });
}

function bootHallForm() {
    bindHallForm(document);
}

document.addEventListener('DOMContentLoaded', bootHallForm);
document.addEventListener('livewire:navigated', bootHallForm);
