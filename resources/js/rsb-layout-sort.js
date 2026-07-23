import { animations, dragAndDrop, dropOrSwap, tearDown } from '@formkit/drag-and-drop';

const initialized = new WeakSet();
let livewireHookBound = false;
let dragActive = false;
let pendingSync = null;

function rsbWire() {
    const root = document.getElementById('room-status-board-root');
    if (!root) return null;

    const host = root.closest('[wire\\:id]');
    if (!host || typeof Livewire === 'undefined') return null;

    return Livewire.find(host.getAttribute('wire:id'));
}

function readChildValues(parent, selector, attr) {
    return Array.from(parent.children)
        .filter((el) => el.matches(selector))
        .map((el) => el.getAttribute(attr))
        .filter(Boolean);
}

function syncDomToValues(parent, values, selector, attr) {
    const root = document.getElementById('room-status-board-root');

    values.forEach((value) => {
        const escaped = CSS.escape(String(value));
        let item = parent.querySelector(`${selector}[${attr}="${escaped}"]`);

        if (!item && root) {
            item = root.querySelector(`${selector}[${attr}="${escaped}"]`);
        }

        if (item) {
            parent.appendChild(item);
        }
    });
}

function isRsbDragging() {
    if (dragActive) return true;

    const root = document.getElementById('room-status-board-root');

    return !!root?.querySelector(
        '.rsb-dnd-dragging, .rsb-dnd-placeholder, .rsb-dnd-synth-dragging',
    );
}

function queueLivewireSync(payload) {
    pendingSync = payload;

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            if (!pendingSync) return;

            const { method, args } = pendingSync;
            pendingSync = null;

            const wire = rsbWire();
            if (wire) {
                wire.call(method, ...args);
            }
        });
    });
}

function destroyLayoutParents() {
    const root = document.getElementById('room-status-board-root');
    if (!root) return;

    root.querySelectorAll('[data-rsb-rows-list], [data-rsb-rooms-row]').forEach((parent) => {
        if (!initialized.has(parent)) return;

        tearDown(parent);
        initialized.delete(parent);
    });
}

function bindDragParent(parent, config) {
    if (initialized.has(parent)) return;

    dragAndDrop({
        parent,
        getValues: () => config.readValues(parent),
        setValues: (values) => {
            syncDomToValues(parent, values, config.itemSelector, config.valueAttr);
        },
        config: {
            threshold: { horizontal: 0.2, vertical: 0.35 },
            draggingClass: 'rsb-dnd-dragging',
            synthDraggingClass: 'rsb-dnd-synth-dragging',
            synthDragPlaceholderClass: 'rsb-dnd-placeholder',
            dragPlaceholderClass: 'rsb-dnd-placeholder',
            dropZoneParentClass: 'rsb-dnd-drop-parent',
            dropZoneClass: 'rsb-dnd-drop-zone',
            plugins: [
                dropOrSwap(),
                animations({ duration: 180, easing: 'cubic-bezier(0.2, 0, 0, 1)' }),
            ],
            onDragstart: () => {
                dragActive = true;
            },
            onDragend: (event) => {
                dragActive = false;

                if (!event.draggedNodes?.length) return;

                const payload = config.buildPayload(event);
                if (payload) {
                    queueLivewireSync(payload);
                }
            },
            ...config.options,
        },
    });

    initialized.add(parent);
}

function initRowLists(root) {
    root.querySelectorAll('[data-rsb-rows-list]').forEach((parent) => {
        const accId = parent.dataset.rsbAccommodationId;
        if (!accId) return;

        bindDragParent(parent, {
            itemSelector: '.room-status-row-wrapper',
            valueAttr: 'data-rsb-row-index',
            readValues: (el) => readChildValues(el, '.room-status-row-wrapper', 'data-rsb-row-index'),
            buildPayload: (event) => {
                const rowIndex = parseInt(event.draggedNodes[0].data.value, 10);
                const position = event.values.indexOf(event.draggedNodes[0].data.value);

                if (Number.isNaN(rowIndex) || position < 0) return null;

                return { method: 'sortLayoutRow', args: [rowIndex, position, accId] };
            },
            options: {
                dragHandle: '.room-status-row__drag',
                draggable: (child) => child.classList.contains('room-status-row-wrapper'),
            },
        });
    });
}

function initRoomRows(root) {
    root.querySelectorAll('[data-rsb-rooms-row]').forEach((parent) => {
        const accId = parent.dataset.rsbAccommodationId;
        if (!accId) return;

        bindDragParent(parent, {
            itemSelector: '.room-status-sortable-item',
            valueAttr: 'data-rsb-room-id',
            readValues: (el) => readChildValues(el, '.room-status-sortable-item', 'data-rsb-room-id'),
            buildPayload: (event) => {
                const roomId = parseInt(event.draggedNodes[0].data.value, 10);
                const rowEl = event.parent.el;
                const rowIndex = parseInt(rowEl.dataset.rsbRowIndex, 10);
                const position = event.values.indexOf(event.draggedNodes[0].data.value);

                if (Number.isNaN(roomId) || Number.isNaN(rowIndex) || position < 0) return null;

                return {
                    method: 'sortRoom',
                    args: [roomId, position, `${accId}:${rowIndex}`],
                };
            },
            options: {
                group: `room-board-${accId}`,
                dragHandle: '.room-status-box__drag',
                draggable: (child) => child.classList.contains('room-status-sortable-item'),
            },
        });
    });
}

export function initRsbLayoutSort() {
    if (isRsbDragging()) return;

    const root = document.getElementById('room-status-board-root');
    if (!root || !root.querySelector('[data-rsb-rows-list]')) return;

    initRowLists(root);
    initRoomRows(root);
}

function scheduleReinit() {
    if (isRsbDragging()) return;

    requestAnimationFrame(() => {
        destroyLayoutParents();
        initRsbLayoutSort();
    });
}

function bindLivewireHooks() {
    if (livewireHookBound || typeof Livewire === 'undefined') return;

    livewireHookBound = true;

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => scheduleReinit());
    });
}

function bindLayoutEditEvent() {
    if (typeof Livewire === 'undefined' || bindLayoutEditEvent.bound) return;

    bindLayoutEditEvent.bound = true;

    Livewire.on('rsb-layout-edit-opened', scheduleReinit);
    Livewire.on('rsb-layout-edit-closed', () => {
        dragActive = false;
        destroyLayoutParents();
    });
}
bindLayoutEditEvent.bound = false;

function bootRsbLayoutSort() {
    bindLivewireHooks();
    bindLayoutEditEvent();
    scheduleReinit();
}

if (typeof window !== 'undefined') {
    window.initRsbLayoutSort = initRsbLayoutSort;
    window.bootRsbLayoutSort = bootRsbLayoutSort;
}

document.addEventListener('livewire:initialized', bootRsbLayoutSort);
document.addEventListener('livewire:navigated', scheduleReinit);

if (typeof window.Livewire !== 'undefined') {
    bootRsbLayoutSort();
} else if (document.readyState !== 'loading') {
    scheduleReinit();
} else {
    document.addEventListener('DOMContentLoaded', scheduleReinit);
}
