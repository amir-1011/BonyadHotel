import { digitsEnToFa } from '@persian-tools/persian-tools';

const statusColors = { confirmed: 'success', pending: 'warning', cancelled: 'danger' };

function escapeHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function showOccDayModal(cell) {
    const root = cell.closest('[data-occ-cal-root]');
    if (!root) return;

    const modalId = root.dataset.modalId;
    const bookingUrlTpl = root.dataset.bookingUrl || '';
    const modalEl = document.getElementById(modalId);
    if (!modalEl || typeof bootstrap === 'undefined') return;

    const titleEl = modalEl.querySelector('.occ-day-modal-title');
    const dateEl = modalEl.querySelector('.occ-day-modal-date');
    const bodyEl = modalEl.querySelector('.occ-day-modal-body');
    if (!bodyEl) return;

    let bookings = [];
    try {
        bookings = JSON.parse(cell.dataset.bookings || '[]');
    } catch {
        bookings = [];
    }

    const jalali = cell.dataset.jalali || '';
    if (titleEl) {
        titleEl.textContent = bookings.length > 0 ? digitsEnToFa(bookings.length + ' رزرو فعال') : 'بدون رزرو';
    }
    if (dateEl) {
        dateEl.textContent = jalali ? 'تاریخ: ' + digitsEnToFa(jalali) : '';
    }

    if (bookings.length === 0) {
        bodyEl.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-calendar-x fs-2 d-block mb-2 opacity-50"></i>در این روز رزرو فعالی ثبت نشده است.</div>';
    } else {
        bodyEl.innerHTML = bookings.map((b) => {
            const st = statusColors[b.status] || 'secondary';
            const href = bookingUrlTpl.replace('999999', String(b.id));
            return `<div class="booking-row">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <div class="fw-bold">${escapeHtml(b.guest)}</div>
                        <code class="small text-muted">${escapeHtml(b.code)}</code>
                    </div>
                    <span class="badge bg-${st}">${escapeHtml(b.status_label)}</span>
                </div>
                <div class="small text-muted mb-1"><i class="bi bi-building me-1"></i>${escapeHtml(b.acc)}</div>
                <div class="small text-muted mb-1"><i class="bi bi-door-open me-1"></i>${escapeHtml(b.room)} · ${escapeHtml(b.rooms)} اتاق</div>
                <div class="small text-muted mb-2"><i class="bi bi-calendar-range me-1"></i>${escapeHtml(b.check_in)} → ${escapeHtml(b.check_out)}</div>
                <a href="${href}" wire:navigate class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>مشاهده رزرو</a>
            </div>`;
        }).join('');
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}

function bindOccCalClicks() {
    if (window._occCalBound) return;
    window._occCalBound = true;

    document.addEventListener('click', (e) => {
        const cell = e.target.closest('[data-occ-cal-root] .occ-cal-cell--day');
        if (!cell) return;
        e.preventDefault();
        showOccDayModal(cell);
    });

    document.addEventListener('livewire:navigated', () => {
        document.querySelectorAll('.occ-day-modal').forEach((modalEl) => {
            bootstrap.Modal.getInstance(modalEl)?.dispose();
        });
    });
}

bindOccCalClicks();
