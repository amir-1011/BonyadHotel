@php
    $guestTableRows = ($allGuestSlots ?? collect())->isNotEmpty()
        ? $allGuestSlots
        : $displayGuestRows;
    $relationOptions = \App\Models\BookingGuestDetail::RELATION_OPTIONS;
    $hasForeignGuests = $guestTableRows->contains(
        fn ($g) => $g instanceof \App\Models\BookingGuestDetail && $g->is_foreign_guest
    );
@endphp

@if($guestTableRows->isNotEmpty())
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th class="col-index">#</th>
                <th>نام</th>
                <th>اتاق</th>
                <th>{{ $hasForeignGuests ? 'کد ملی / پاسپورت' : 'کد ملی' }}</th>
                @if($hasForeignGuests)
                <th>محل اقامت</th>
                @endif
                <th>موبایل</th>
                <th>نسبت</th>
                <th>تخفیف اقامت</th>
                <th>تخفیف دستی اقامت</th>
                @if($canEditGuestNames ?? false)
                <th class="text-center" style="width:52px">ذخیره</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($guestTableRows as $g)
            @php
                $index = (int) ($g->sort_order ?? 0);
                $isDiscountOnly = !empty($g->discount_only);
                $roomLabel = $g instanceof \App\Models\BookingGuestDetail
                    ? $booking->guestPhysicalRoomLabel($g)
                    : null;
                $guestServiceCount = $booking->servicesForGuest($index)->count();
                $displayName = trim((string) ($g->full_name ?? ''));
                if ($displayName === '' || \App\Models\BookingGuestDetail::isGenericGuestName($displayName, $index)) {
                    $displayName = 'مهمان ' . ($index + 1);
                }
            @endphp
            <tr class="{{ $index === 0 ? 'table-light' : '' }} {{ $isDiscountOnly ? 'table-info table-info-subtle' : '' }}" wire:key="guest-row-{{ $booking->id }}-{{ $index }}">
                <td>{{ $index + 1 }}</td>
                <td class="fw-semibold">
                    @if($index === 0)
                        {{ $g->full_name }}
                        <span class="badge text-bg-primary ms-1" style="font-size:.65rem">مهمان اصلی</span>
                    @elseif($canEditGuestNames ?? false)
                        <input type="text"
                               wire:model="editableGuests.{{ $index }}.full_name"
                               class="form-control form-control-sm"
                               placeholder="مهمان {{ $index + 1 }}">
                        @error("editableGuests.{$index}.full_name")
                        <div class="text-danger mt-1" style="font-size:.72rem">{{ $message }}</div>
                        @enderror
                    @else
                        {{ $displayName }}
                    @endif
                    @if($isDiscountOnly && !($canEditGuestNames ?? false))
                    <span class="badge text-bg-secondary ms-1" style="font-size:.65rem">فقط تخفیف ثبت‌شده</span>
                    @endif
                    @if($guestServiceCount > 0)
                    <div class="text-muted fw-normal mt-1" style="font-size:.72rem">{{ $guestServiceCount }} خدمت</div>
                    @endif
                </td>
                <td>
                    @if($roomLabel)
                    <span class="badge text-bg-dark" style="font-size:.65rem">{{ $roomLabel }}</span>
                    @else
                    <span class="text-muted">—</span>
                    @endif
                </td>
                <td dir="ltr">
                    @if($index > 0 && ($canEditGuestNames ?? false))
                        <input type="text"
                               wire:model="editableGuests.{{ $index }}.national_id"
                               class="form-control form-control-sm"
                               placeholder="کد ملی"
                               dir="ltr"
                               inputmode="numeric">
                        @error("editableGuests.{$index}.national_id")
                        <div class="text-danger mt-1" style="font-size:.72rem">{{ $message }}</div>
                        @enderror
                    @else
                        {{ $g instanceof \App\Models\BookingGuestDetail ? ($g->identityNumber() ?: '—') : ($g->national_id ?? '—') }}
                    @endif
                </td>
                @if($hasForeignGuests)
                <td>
                    {{ $g instanceof \App\Models\BookingGuestDetail ? ($g->residenceLocationLabel() ?: '—') : '—' }}
                </td>
                @endif
                <td dir="ltr">
                    @if($index > 0 && ($canEditGuestNames ?? false))
                        <input type="text"
                               wire:model="editableGuests.{{ $index }}.mobile"
                               class="form-control form-control-sm"
                               placeholder="09xxxxxxxxx"
                               dir="ltr"
                               inputmode="tel">
                        @error("editableGuests.{$index}.mobile")
                        <div class="text-danger mt-1" style="font-size:.72rem">{{ $message }}</div>
                        @enderror
                    @else
                        {{ $g->mobile ?: '—' }}
                    @endif
                </td>
                <td>
                    @if($index > 0 && ($canEditGuestNames ?? false))
                        <select wire:model="editableGuests.{{ $index }}.relation" class="form-select form-select-sm">
                            <option value="">— نسبت —</option>
                            @foreach($relationOptions as $rel)
                            <option value="{{ $rel }}">{{ $rel }}</option>
                            @endforeach
                        </select>
                        @error("editableGuests.{$index}.relation")
                        <div class="text-danger mt-1" style="font-size:.72rem">{{ $message }}</div>
                        @enderror
                    @else
                        {{ $g instanceof \App\Models\BookingGuestDetail ? $g->relationLabel() : '—' }}
                    @endif
                </td>
                <td>
                    @if($g->excluded_from_veteran_discount)
                        <span class="badge text-bg-warning">نرخ عادی</span>
                        <div class="text-muted mt-1" style="font-size:.7rem">بدون سهم تخفیف ایثارگری اقامت</div>
                    @elseif($booking->veteran_type_applied)
                        <span class="badge text-bg-success">شامل ایثارگری</span>
                        <div class="text-muted mt-1" style="font-size:.7rem">{{ $booking->veteranLabelApplied() }} · {{ $booking->discount_percentage }}٪</div>
                    @else
                        <span class="badge text-bg-secondary">نرخ عادی</span>
                    @endif
                </td>
                <td>
                    @if($g->manual_discount_percentage)
                        <span class="badge text-bg-info">{{ $g->manual_discount_percentage }}٪</span>
                        @if($g->manual_discount_reason)
                        <div class="text-muted mt-1" style="font-size:.75rem">{{ $g->manual_discount_reason }}</div>
                        @endif
                    @else
                        —
                    @endif
                </td>
                @if($canEditGuestNames ?? false)
                <td class="text-center">
                    @if($index > 0)
                    <button type="button"
                            wire:click="saveGuestDetails({{ $index }})"
                            class="btn btn-sm btn-outline-primary"
                            wire:loading.attr="disabled"
                            wire:target="saveGuestDetails"
                            title="ذخیره اطلاعات مهمان">
                        <span wire:loading.remove wire:target="saveGuestDetails"><i class="bi bi-check-lg"></i></span>
                        <span wire:loading wire:target="saveGuestDetails" class="spinner-border spinner-border-sm"></span>
                    </button>
                    @else
                    <span class="text-muted">—</span>
                    @endif
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@if($canEditGuestNames ?? false)
<p class="text-muted small mt-2 mb-0">
    <i class="bi bi-pencil-square me-1"></i>
    اطلاعات مهمانان (به‌جز مهمان اصلی) قابل ویرایش است و بر قیمت یا تخفیف اثر نمی‌گذارد.
</p>
@endif
<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle me-1"></i>
    <strong>تخفیف اقامت</strong> (ستون بالا) با <strong>سهمیه/تخفیف خدمات</strong> (در بخش خدمات هر مهمان) متفاوت است.
</p>
@elseif($manualDiscountGuests->isNotEmpty() || $excludedGuests->isNotEmpty())
<ul class="list-group list-group-flush">
    @foreach($manualDiscountGuests as $g)
    <li class="list-group-item d-flex justify-content-between align-items-start gap-2 px-0">
        <div>
            <strong>{{ $g->full_name }}</strong>
            @if($g->manual_discount_reason)
            <div class="text-muted mt-1" style="font-size:.78rem">{{ $g->manual_discount_reason }}</div>
            @endif
        </div>
        <span class="badge text-bg-info flex-shrink-0">{{ $g->manual_discount_percentage }}٪</span>
    </li>
    @endforeach
    @foreach($excludedGuests->filter(fn ($g) => (int) ($g->manual_discount_percentage ?? 0) === 0) as $g)
    <li class="list-group-item px-0">
        <strong>{{ $g->full_name }}</strong>
        <span class="badge text-bg-warning ms-1">نرخ عادی</span>
    </li>
    @endforeach
</ul>
@else
<p class="text-muted mb-0">مشخصات مهمان ثبت نشده است.</p>
@endif
