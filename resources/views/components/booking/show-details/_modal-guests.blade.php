@if($displayGuestRows->isNotEmpty())
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>نام</th>
                <th>کد ملی</th>
                <th>موبایل</th>
                <th>نسبت</th>
                <th>وضعیت تخفیف</th>
                <th>تخفیف دستی</th>
            </tr>
        </thead>
        <tbody>
            @foreach($displayGuestRows as $g)
            @php
                $index = (int) ($g->sort_order ?? 0);
                $isDiscountOnly = !empty($g->discount_only);
            @endphp
            <tr class="{{ $index === 0 ? 'table-light' : '' }} {{ $isDiscountOnly ? 'table-info table-info-subtle' : '' }}">
                <td>{{ $index + 1 }}</td>
                <td class="fw-semibold">
                    {{ $g->full_name }}
                    @if($index === 0)
                    <span class="badge text-bg-primary ms-1" style="font-size:.65rem">رزرو‌کننده</span>
                    @endif
                    @if($isDiscountOnly)
                    <span class="badge text-bg-secondary ms-1" style="font-size:.65rem">فقط تخفیف ثبت‌شده</span>
                    @endif
                </td>
                <td dir="ltr">{{ $g->national_id ?: '—' }}</td>
                <td dir="ltr">{{ $g->mobile ?: '—' }}</td>
                <td>{{ $g->relation ?: '—' }}</td>
                <td>
                    @if($g->excluded_from_veteran_discount)
                        <span class="badge text-bg-warning">نرخ عادی</span>
                    @elseif($booking->veteran_type_applied)
                        <span class="badge text-bg-success">ایثارگری {{ $booking->discount_percentage }}٪</span>
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
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
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
