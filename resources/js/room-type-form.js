/**
 * Room type create/edit form helpers (plain HTML forms, wire:navigate-safe).
 * Mirrors the manual-add UX from accommodation province/city fields.
 */

function slugId(value) {
    return String(value).replace(/\s+/g, '-').replace(/[^\w\u0600-\u06FF-]/g, '');
}

function bindManualRoomCategory(form) {
    const select = form.querySelector('[data-room-category-select]');
    const toggleBtn = form.querySelector('[data-action="toggle-add-room-category"]');
    const panel = form.querySelector('[data-room-category-add-panel]');
    const input = form.querySelector('[data-room-category-new-input]');
    const errorEl = form.querySelector('[data-room-category-error]');
    const confirmBtn = form.querySelector('[data-action="confirm-add-room-category"]');
    const cancelBtn = form.querySelector('[data-action="cancel-add-room-category"]');

    if (!select || !toggleBtn || !panel || !input) {
        return;
    }

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

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', () => {
        const name = input.value.trim();
        if (!name) {
            showError('نام نوع اتاق را وارد کنید.');
            return;
        }

        const exists = Array.from(select.options).some((opt) => opt.value === name);
        if (exists) {
            showError('این نوع قبلاً در لیست وجود دارد.');
            return;
        }

        const option = document.createElement('option');
        option.value = name;
        option.textContent = name;
        select.appendChild(option);
        select.value = name;
        closePanel();
    });
}

function bindManualAmenities(form) {
    const grid = form.querySelector('[data-amenities-grid]');
    const toggleBtn = form.querySelector('[data-action="toggle-add-amenity"]');
    const panel = form.querySelector('[data-amenity-add-panel]');
    const input = form.querySelector('[data-amenity-new-input]');
    const errorEl = form.querySelector('[data-amenity-error]');
    const confirmBtn = form.querySelector('[data-action="confirm-add-amenity"]');
    const cancelBtn = form.querySelector('[data-action="cancel-add-amenity"]');

    if (!grid || !toggleBtn || !panel || !input) {
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

    const addAmenityTile = (name, checked = true) => {
        const id = `am_custom_${slugId(name)}_${Date.now()}`;
        const col = document.createElement('div');
        col.className = 'col-6 col-md-4 col-lg-3';

        const tile = document.createElement('label');
        tile.className = 'rt-amenity-tile';

        const checkbox = document.createElement('input');
        checkbox.className = 'rt-amenity-input';
        checkbox.type = 'checkbox';
        checkbox.name = 'amenities[]';
        checkbox.value = name;
        checkbox.id = id;
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
        grid.appendChild(col);
    };

    toggleBtn.addEventListener('click', openPanel);
    cancelBtn?.addEventListener('click', closePanel);

    confirmBtn?.addEventListener('click', () => {
        const name = input.value.trim();
        if (!name) {
            showError('نام امکان را وارد کنید.');
            return;
        }

        if (existingValues().includes(name)) {
            showError('این امکان قبلاً در لیست وجود دارد.');
            return;
        }

        addAmenityTile(name, true);
        closePanel();
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
