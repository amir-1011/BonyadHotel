<div class="card shadow-sm">
    <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-person-lines-fill me-2"></i>لیست مهمانان اردو</span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary">{{ $this->filledGuestCount() }} / {{ $guestCount }} ثبت‌شده</span>
        </div>
    </div>
    <div class="card-body">
        <div class="border rounded p-3 mb-4 bg-light">
            <label class="form-label small fw-semibold mb-1">فایل لیست مهمانان (Excel / CSV)</label>
            <input type="file"
                   wire:model="guestListDocuments"
                   multiple
                   accept=".csv,.txt,.xlsx,.xls"
                   class="form-control form-control-sm">
            <div class="form-text">فایل Excel یا CSV — حداکثر ۱۰ مگابایت — پس از ثبت در جزئیات اردو قابل دانلود است.</div>
            <div wire:loading wire:target="guestListDocuments" class="small text-muted mt-1">در حال بارگذاری فایل...</div>
            @error('guestListDocuments.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            @if($guestListDocuments !== [])
            <div class="small text-success mt-2">
                <i class="bi bi-check-circle me-1"></i>{{ count($guestListDocuments) }} فایل آماده ثبت است.
            </div>
            @endif
        </div>

        <div class="alert alert-info small">
            <i class="bi bi-info-circle me-1"></i>
            می‌توانید مهمانان را دستی وارد کنید یا فایل Excel/CSV را به‌عنوان پیوست بارگذاری کنید.
        </div>

        @if($this->filledGuestCount() < $guestCount)
        <div class="alert alert-warning small py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            تعداد مهمانان ثبت‌شده ({{ $this->filledGuestCount() }}) کمتر از تعداد نفرات برنامه ({{ $guestCount }}) است.
        </div>
        @endif

        @if(count($roomLines) > 1)
        <div class="alert alert-light border small py-2 mb-3">
            <i class="bi bi-door-open me-1"></i>
            مهمانان به‌صورت خودکار بین اتاق‌های انتخاب‌شده تقسیم می‌شوند. می‌توانید اتاق هر مهمان را تغییر دهید.
        </div>
        @endif

        @php
            $programGuestsCollapseId = 'physicalRoomsCollapse-program-guests';
            $programGuestsListOpen = true;
        @endphp
        <div class="border rounded mb-0">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3 bg-light"
                 role="button"
                 data-bs-toggle="collapse"
                 data-bs-target="#{{ $programGuestsCollapseId }}"
                 aria-expanded="{{ $programGuestsListOpen ? 'true' : 'false' }}"
                 aria-controls="{{ $programGuestsCollapseId }}"
                 style="cursor:pointer;user-select:none">
                <span>
                    <i class="bi bi-people me-2"></i>فهرست ورود اطلاعات مهمانان
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-1" style="font-size:.7rem;font-weight:500">{{ count($guestRows) }} نفر</span>
                </span>
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-chevron-up text-muted physical-rooms-chevron {{ $programGuestsListOpen ? '' : 'is-collapsed' }}"
                       data-physical-rooms-chevron
                       style="transition:transform .25s"></i>
                </div>
            </div>
            <div class="collapse {{ $programGuestsListOpen ? 'show' : '' }}" id="{{ $programGuestsCollapseId }}">
                <div class="p-3 program-guests-list-scroll">
        @php $prevRoomLabel = null; @endphp
        @foreach($guestRows as $i => $guest)
        @php $roomLabel = $this->guestRoomLabel($i); @endphp
        @if($roomLabel && $roomLabel !== $prevRoomLabel && count($roomLines) > 1)
        <div class="d-flex align-items-center gap-2 mb-2 mt-1" wire:key="prog-guest-room-heading-{{ $i }}">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2">
                <i class="bi bi-door-open me-1"></i>اتاق {{ $roomLabel }}
            </span>
        </div>
        @php $prevRoomLabel = $roomLabel; @endphp
        @endif
        <div class="border rounded p-3 mb-3 bg-white" wire:key="prog-guest-{{ $i }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge rounded-pill text-bg-secondary">نفر {{ $i + 1 }}</span>
                @if($i === 0)
                <span class="small text-muted"><i class="bi bi-person-check me-1"></i>مهمان اصلی</span>
                @endif
                <button type="button" wire:click="removeGuestRow({{ $i }})" class="btn btn-sm btn-outline-danger ms-auto" @disabled(count($guestRows) <= 1)>
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label small mb-1">نام و نام خانوادگی</label>
                    <input type="text" wire:model="guestRows.{{ $i }}.full_name" class="form-control form-control-sm" placeholder="نام کامل">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">کد ملی</label>
                    <input type="text" wire:model="guestRows.{{ $i }}.national_id" class="form-control form-control-sm @error('guestRows.'.$i.'.national_id') is-invalid @enderror" placeholder="کد ملی" dir="ltr">
                    @error('guestRows.'.$i.'.national_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">موبایل</label>
                    <input type="text" wire:model="guestRows.{{ $i }}.mobile" class="form-control form-control-sm @error('guestRows.'.$i.'.mobile') is-invalid @enderror" placeholder="09xxxxxxxxx" dir="ltr">
                    @error('guestRows.'.$i.'.mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">نسبت</label>
                    @if($i === 0)
                    <select wire:model="guestRows.{{ $i }}.relation" class="form-select form-select-sm" disabled>
                        <option value="{{ \App\Models\BookingGuestDetail::RELATION_MAIN_GUEST }}">{{ \App\Models\BookingGuestDetail::RELATION_MAIN_GUEST_LABEL }}</option>
                    </select>
                    @else
                    <select wire:model="guestRows.{{ $i }}.relation" class="form-select form-select-sm">
                        <option value="">— نسبت —</option>
                        @foreach(['همسر', 'پدر', 'مادر', 'فرزند', 'خواهر', 'برادر', 'دوست', 'همکار', 'غیره'] as $rel)
                        <option value="{{ $rel }}">{{ $rel }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                @if(count($roomLines) > 0)
                <div class="col-md-3">
                    <label class="form-label small mb-1">اتاق</label>
                    <select wire:model.live="guestRows.{{ $i }}.room_line_index" class="form-select form-select-sm">
                        <option value="">— خودکار —</option>
                        @foreach($roomLines as $ri => $line)
                        <option value="{{ $ri }}">{{ $line['room_name'] ?? ('اتاق ' . ($ri + 1)) }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        @error('guestRows')<div class="alert alert-danger small mb-0 mt-2">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('styles')
<style>
.program-guests-list-scroll {
    max-height: min(60vh, 520px);
    overflow-y: auto;
}
.physical-rooms-chevron.is-collapsed {
    transform: rotate(180deg);
}
</style>
@endpush
@push('scripts')
<script data-navigate-once>
(function () {
    if (window.__bonyadPhysicalRoomsCollapseBound) return;
    window.__bonyadPhysicalRoomsCollapseBound = true;

    function syncChevron(collapseEl, isOpen) {
        const card = collapseEl.closest('.border.rounded, .card');
        const chevron = card?.querySelector('[data-physical-rooms-chevron]');
        if (!chevron) return;
        chevron.classList.toggle('is-collapsed', !isOpen);
    }

    document.addEventListener('show.bs.collapse', function (e) {
        if (!e.target?.id?.startsWith('physicalRoomsCollapse-')) return;
        syncChevron(e.target, true);
    });
    document.addEventListener('hide.bs.collapse', function (e) {
        if (!e.target?.id?.startsWith('physicalRoomsCollapse-')) return;
        syncChevron(e.target, false);
    });
})();
</script>
@endpush
@endonce
