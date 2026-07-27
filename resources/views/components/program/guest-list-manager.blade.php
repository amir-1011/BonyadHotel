@props([
    'program',
    'panel' => 'host',
])

@php
    $booking = $program->booking;
    $roomLines = $this->programRoomLines();
    $relationOptions = \App\Models\BookingGuestDetail::RELATION_OPTIONS;
    $canEdit = $this->canEditProgramGuests();
    $registeredCount = $booking
        ? $booking->guestDetails->filter(fn ($g) => !\App\Models\BookingGuestDetail::isGenericGuestName($g->full_name, (int) $g->sort_order))->count()
        : 0;
    $displayCount = $guestEditMode
        ? $this->filledProgramGuestCount()
        : ($booking?->guestDetails->count() ?? 0);
    $hasForeignGuests = $booking?->guestDetails->contains(fn ($guest) => $guest->is_foreign_guest) ?? false;
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold py-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-person-lines-fill me-1"></i>مهمانان اردو ({{ $displayCount }} / {{ $program->guest_count }} نفر)</span>
        <div class="d-flex align-items-center gap-2">
            @if($canEdit)
                @if($guestEditMode)
                <button type="button" wire:click="saveProgramGuests" class="btn btn-sm btn-success" wire:loading.attr="disabled" wire:target="saveProgramGuests">
                    <span wire:loading.remove wire:target="saveProgramGuests"><i class="bi bi-check-lg me-1"></i>ذخیره</span>
                    <span wire:loading wire:target="saveProgramGuests" class="spinner-border spinner-border-sm"></span>
                </button>
                <button type="button" wire:click="toggleGuestEditMode" class="btn btn-sm btn-outline-secondary">انصراف</button>
                @else
                <button type="button" wire:click="toggleGuestEditMode" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i>ویرایش مهمانان
                </button>
                @endif
            @endif
        </div>
    </div>
    <div class="card-body">
        @if($registeredCount < $program->guest_count)
        <div class="alert alert-warning small py-2">
            <i class="bi bi-exclamation-triangle me-1"></i>
            تعداد مهمانان ثبت‌شده ({{ $registeredCount }}) کمتر از تعداد نفرات برنامه ({{ $program->guest_count }}) است.
        </div>
        @endif

        @if($guestEditMode)
        <div class="alert alert-info small py-2">
            <i class="bi bi-info-circle me-1"></i>
            نام، کد ملی، موبایل، نسبت و اتاق هر مهمان را ویرایش کنید. ردیف‌های خالی هنگام ذخیره نادیده گرفته می‌شوند.
        </div>

        @foreach($guestRows as $i => $guest)
        <div class="border rounded p-3 mb-3 bg-light" wire:key="program-show-guest-{{ $program->id }}-{{ $i }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge rounded-pill text-bg-secondary">نفر {{ $i + 1 }}</span>
                @if($i === 0)
                <span class="small text-muted"><i class="bi bi-person-check me-1"></i>مهمان اصلی</span>
                @endif
                <button type="button" wire:click="removeProgramGuestRow({{ $i }})" class="btn btn-sm btn-outline-danger ms-auto" @disabled(count($guestRows) <= 1)>
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
                        @foreach($relationOptions as $rel)
                        <option value="{{ $rel }}">{{ $rel }}</option>
                        @endforeach
                    </select>
                    @endif
                </div>
                @if(count($roomLines) > 0)
                <div class="col-md-3">
                    <label class="form-label small mb-1">اتاق</label>
                    <select wire:model="guestRows.{{ $i }}.room_line_index" class="form-select form-select-sm">
                        <option value="">— بدون اتاق —</option>
                        @foreach($roomLines as $ri => $line)
                        <option value="{{ $ri }}">{{ $line['room_name'] }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
            </div>
        </div>
        @endforeach

        @error('guestRows')<div class="alert alert-danger small">{{ $message }}</div>@enderror

        <div class="d-flex gap-2 flex-wrap">
            <button type="button" wire:click="addProgramGuestRow" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i>افزودن مهمان
            </button>
            <button type="button" wire:click="saveProgramGuests" class="btn btn-sm btn-success" wire:loading.attr="disabled" wire:target="saveProgramGuests">
                <i class="bi bi-check-lg me-1"></i>ذخیره لیست مهمانان
            </button>
        </div>

        @elseif($booking && $booking->guestDetails->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>نام</th>
                        <th>{{ $hasForeignGuests ? 'کد ملی / پاسپورت' : 'کد ملی' }}</th>
                        @if($hasForeignGuests)
                        <th>محل اقامت</th>
                        @endif
                        <th>موبایل</th>
                        <th>نسبت</th>
                        @if($booking->bookingRooms->count() > 1)
                        <th>اتاق</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach($booking->guestDetails->sortBy('sort_order') as $i => $guest)
                    @php
                        $displayName = trim((string) $guest->full_name);
                        if (\App\Models\BookingGuestDetail::isGenericGuestName($displayName, (int) $guest->sort_order)) {
                            $displayName = 'مهمان ' . ((int) $guest->sort_order + 1);
                        }
                    @endphp
                    <tr class="{{ (int) $guest->sort_order === 0 ? 'table-light' : '' }}">
                        <td>{{ $i + 1 }}</td>
                        <td class="fw-semibold">
                            {{ $displayName }}
                            @if((int) $guest->sort_order === 0)
                            <span class="badge text-bg-primary ms-1" style="font-size:.65rem">مهمان اصلی</span>
                            @endif
                        </td>
                        <td dir="ltr">{{ $guest->identityNumber() ?: '—' }}</td>
                        @if($hasForeignGuests)
                        <td>{{ $guest->is_foreign_guest ? ($guest->residenceLocationLabel() ?: '—') : '—' }}</td>
                        @endif
                        <td dir="ltr">{{ $guest->mobile ?: '—' }}</td>
                        <td>{{ $guest->relationLabel() ?: '—' }}</td>
                        @if($booking->bookingRooms->count() > 1)
                        <td>
                            @if($guest->bookingRoom?->room)
                            <span class="badge bg-info-subtle text-info border">{{ $guest->bookingRoom->room->name }}</span>
                            @else
                            —
                            @endif
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="bi bi-people fs-3 d-block mb-2"></i>
            هنوز مهمانی ثبت نشده است.
            @if($canEdit)
            <div class="mt-2">
                <button type="button" wire:click="toggleGuestEditMode" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-plus-lg me-1"></i>افزودن مهمانان
                </button>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
