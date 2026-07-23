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

        @error('guestRows')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    </div>
</div>
