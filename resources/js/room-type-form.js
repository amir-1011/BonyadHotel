/**
 * Room type create/edit form helpers (plain HTML forms, wire:navigate-safe).
 */

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

function showRoomTypeFormError(message) {
    if (typeof window.bnbToast === 'function') {
        window.bnbToast('error', message);
        return;
    }

    window.alert(message);
}

const roomTypeCatalogApi = {
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
};

function bindManualRoomCategory(form) {
    const select = form.querySelector('[data-room-category-select]');
    const catalog = form.querySelector('[data-room-categories-catalog]');
    const toggleBtn = form.querySelector('[data-action="toggle-add-room-category"]');
    const panel = form.querySelector('[data-room-category-add-panel]');
    const input = form.querySelector('[data-room-category-new-input]');
    const errorEl = form.querySelector('[data-room-category-error]');
    const confirmBtn = form.querySelector('[data-action="confirm-add-room-category"]');
    const cancelBtn = form.querySelector('[data-action="cancel-add-room-category"]');
    const storeUrl = form.dataset.categoryStoreUrl;
    const destroyBaseUrl = form.dataset.categoryDestroyUrl;

    if (!select || !catalog || !toggleBtn || !panel || !input || !storeUrl || !destroyBaseUrl) {
        return;
    }

    const existingValues = () =>
        Array.from(select.options).map((opt) => opt.value).filter(Boolean);

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

    const buildRemoveButton = (id, name) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rt-catalog-pill__remove';
        btn.dataset.action = 'remove-room-category';
        btn.dataset.categoryId = String(id);
        btn.dataset.categoryName = name;
        btn.title = 'حذف از لیست سراسری';
        btn.setAttribute('aria-label', `حذف ${name} از لیست`);
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        return btn;
    };

    const addCategoryUi = (id, name, canDelete = true) => {
        if (!existingValues().includes(name)) {
            const option = document.createElement('option');
            option.value = name;
            option.textContent = name;
            option.dataset.categoryId = String(id);
            select.appendChild(option);
        }

        if (catalog.querySelector(`[data-category-id="${id}"]`)) {
            select.value = name;
            return;
        }

        const pill = document.createElement('span');
        pill.className = 'rt-catalog-pill';
        pill.dataset.categoryId = String(id);

        const label = document.createElement('span');
        label.className = 'rt-catalog-pill__label';
        label.textContent = name;
        pill.appendChild(label);

        if (canDelete) {
            pill.appendChild(buildRemoveButton(id, name));
        }

        catalog.appendChild(pill);
        select.value = name;
    };

    const removeCategoryUi = (id, name) => {
        catalog.querySelectorAll(`[data-category-id="${id}"]`).forEach((el) => el.remove());

        Array.from(select.options).forEach((opt) => {
            if (opt.value === name || opt.dataset.categoryId === String(id)) {
                if (select.value === opt.value) {
                    select.value = '';
                }
                opt.remove();
            }
        });
    };

    const removeCategory = async (button) => {
        const id = button.dataset.categoryId;
        const name = button.dataset.categoryName;
        if (!id || !name) {
            return;
        }

        const confirmed = await bnbConfirmDelete(`«${name}» از لیست سراسری انواع اتاق حذف شود؟`);
        if (!confirmed) {
            return;
        }

        button.disabled = true;

        try {
            await roomTypeCatalogApi.delete(`${destroyBaseUrl}/${id}`);
            removeCategoryUi(id, name);
        } catch (error) {
            button.disabled = false;
            showRoomTypeFormError(error.message || 'حذف نوع اتاق انجام نشد.');
        }
    };

    catalog.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action="remove-room-category"]');
        if (!button || !catalog.contains(button)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        removeCategory(button);
    });

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', async () => {
        const name = input.value.trim();
        if (!name) {
            showError('نام نوع اتاق را وارد کنید.');
            return;
        }

        if (existingValues().includes(name)) {
            showError('این نوع قبلاً در لیست وجود دارد.');
            return;
        }

        confirmBtn.disabled = true;

        try {
            const payload = await roomTypeCatalogApi.post(storeUrl, { name });
            addCategoryUi(payload.id, payload.name, Boolean(payload.can_delete));
            closePanel();
        } catch (error) {
            showError(error.message || 'افزودن نوع اتاق انجام نشد.');
        } finally {
            confirmBtn.disabled = false;
        }
    });
}

function removeAmenityFromPhysicalRooms(documentRoot, name) {
    documentRoot.querySelectorAll('.physical-room-amenity input[type="checkbox"]').forEach((input) => {
        if (input.value === name) {
            input.closest('.physical-room-amenity')?.remove();
        }
    });
}

function addAmenityToPhysicalRooms(documentRoot, name) {
    documentRoot.querySelectorAll('.physical-room-amenities').forEach((container) => {
        const exists = Array.from(container.querySelectorAll('input[type="checkbox"]'))
            .some((input) => input.value === name);
        if (exists) {
            return;
        }

        const sample = container.querySelector('input[type="checkbox"]');
        if (!sample) {
            return;
        }

        const label = document.createElement('label');
        label.className = 'physical-room-amenity';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = sample.name;
        checkbox.value = name;

        const span = document.createElement('span');
        span.textContent = name;

        label.appendChild(checkbox);
        label.appendChild(span);
        container.appendChild(label);
    });
}

function applyAmenityToAllPhysicalRooms(documentRoot, name, checked) {
    const containers = documentRoot.querySelectorAll('.physical-room-amenities');
    if (!containers.length) {
        if (typeof window.bnbToast === 'function') {
            window.bnbToast('warning', 'اتاق فیزیکی برای اعمال وجود ندارد.');
        }
        return;
    }

    if (checked) {
        addAmenityToPhysicalRooms(documentRoot, name);
    }

    documentRoot.querySelectorAll('.physical-room-amenity input[type="checkbox"]').forEach((input) => {
        if (input.value === name) {
            input.checked = checked;
        }
    });

    if (typeof window.bnbToast === 'function') {
        const message = checked
            ? `«${name}» روی همه اتاق‌های فیزیکی اعمال شد.`
            : `«${name}» از همه اتاق‌های فیزیکی برداشته شد.`;
        window.bnbToast('success', message);
    }
}

function bindManualAmenities(form) {
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

    const buildRemoveButton = (id, name) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rt-amenity-remove';
        btn.dataset.action = 'remove-amenity';
        btn.dataset.amenityId = String(id);
        btn.dataset.amenityName = name;
        btn.title = 'حذف از لیست سراسری';
        btn.setAttribute('aria-label', `حذف ${name} از لیست`);
        btn.innerHTML = '<i class="bi bi-x-lg"></i>';
        return btn;
    };

    const buildApplyAllButton = (name) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rt-amenity-apply-all';
        btn.dataset.action = 'apply-amenity-to-all-rooms';
        btn.dataset.amenityName = name;
        btn.title = 'اعمال روی همه اتاق‌های فیزیکی';
        btn.setAttribute('aria-label', `اعمال ${name} روی همه اتاق‌های فیزیکی`);
        btn.innerHTML = '<i class="bi bi-layers"></i>';
        return btn;
    };

    const removeAmenityUi = (id, name) => {
        document.querySelectorAll(`[data-amenity-id="${id}"]`).forEach((el) => el.remove());
        removeAmenityFromPhysicalRooms(document, name);
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
        checkbox.id = `am_${id}`;
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
        col.appendChild(buildApplyAllButton(name));

        if (canDelete) {
            col.appendChild(buildRemoveButton(id, name));
        }

        grid.appendChild(col);
        addAmenityToPhysicalRooms(document, name);
    };

    const removeAmenity = async (button) => {
        const id = button.dataset.amenityId;
        const name = button.dataset.amenityName;
        if (!id || !name) {
            return;
        }

        const confirmed = await bnbConfirmDelete(`«${name}» از لیست سراسری امکانات حذف شود؟`);
        if (!confirmed) {
            return;
        }

        button.disabled = true;

        try {
            await roomTypeCatalogApi.delete(`${destroyBaseUrl}/${id}`);
            removeAmenityUi(id, name);
        } catch (error) {
            button.disabled = false;
            showRoomTypeFormError(error.message || 'حذف امکان انجام نشد.');
        }
    };

    grid.addEventListener('click', (event) => {
        const applyBtn = event.target.closest('[data-action="apply-amenity-to-all-rooms"]');
        if (applyBtn && grid.contains(applyBtn)) {
            event.preventDefault();
            event.stopPropagation();
            const name = applyBtn.dataset.amenityName;
            const source = applyBtn.closest('[data-amenity-id]')?.querySelector('input[name="amenities[]"]');
            if (name && source) {
                applyAmenityToAllPhysicalRooms(document, name, source.checked);
            }
            return;
        }

        const button = event.target.closest('[data-action="remove-amenity"]');
        if (!button || !grid.contains(button)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        removeAmenity(button);
    });

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', async () => {
        const name = input.value.trim();
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
            const payload = await roomTypeCatalogApi.post(storeUrl, { name });
            addAmenityTile(payload.id, payload.name, true, Boolean(payload.can_delete));
            closePanel();
        } catch (error) {
            showError(error.message || 'افزودن امکان انجام نشد.');
        } finally {
            confirmBtn.disabled = false;
        }
    });
}

function bindImagePreview(form) {
    const input = form.querySelector('[data-room-images-input]');
    const box = form.querySelector('[data-room-images-preview]');

    if (!input || !box) {
        return;
    }

    input.addEventListener('change', () => {
        box.innerHTML = '';
        Array.from(input.files).forEach((file) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'preview';
            img.className = 'rt-image-preview-thumb';
            box.appendChild(img);
        });
    });
}

function bindRoomTypeForm(form) {
    bindManualRoomCategory(form);
    bindManualAmenities(form);
    bindImagePreview(form);
}

export function initRoomTypeForms(root = document) {
    root.querySelectorAll('[data-room-type-form]').forEach((form) => {
        if (form.dataset.roomTypeFormBound === '1') {
            return;
        }
        form.dataset.roomTypeFormBound = '1';
        bindRoomTypeForm(form);
    });
}

function bootRoomTypeForm() {
    initRoomTypeForms();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootRoomTypeForm);
} else {
    bootRoomTypeForm();
}

document.addEventListener('livewire:navigated', () => initRoomTypeForms());

window.initRoomTypeForms = initRoomTypeForms;
