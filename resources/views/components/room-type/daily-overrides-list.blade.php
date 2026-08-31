@props([
    'overrideRanges',
    'roomType',
    'accommodation',
    'rangeDestroyRouteName',
    'panel' => null,
])

@php
    $panelKey = $panel ?? (str_starts_with($rangeDestroyRouteName ?? '', 'host.') ? 'host' : 'admin');
    $rangeCount = count($overrideRanges);
@endphp

<div class="card shadow-sm border-0 rounded-4 mt-3">
    <div class="card-header bg-white border-bottom rounded-top-4">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-calendar-check me-2 text-primary"></i>
            تنظیمات دستی فعال
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">{{ $rangeCount }}</span>
        </h6>
    </div>
    <div class="card-body p-3">
        @if($rangeCount === 0)
        <div class="text-center py-4 text-muted small">
            <i class="bi bi-inbox fs-3 d-block mb-2 opacity-50"></i>
            هیچ تنظیم دستی‌ای ثبت نشده است.
        </div>
        @else
        <div class="daily-ovr-list">
            @foreach($overrideRanges as $range)
            @php
                $ov = $range['override'];
                $rateRows = $range['rate_rows'];
                $fromJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($range['date_from']))->format('Y/m/d');
                $toJ = \Morilog\Jalali\Jalalian::fromCarbon(\Carbon\Carbon::parse($range['date_to']))->format('Y/m/d');
                $isSingleDay = $range['date_from'] === $range['date_to'];
                $deleteMessage = $isSingleDay
                    ? 'این تنظیم حذف شود؟'
                    : 'کل این بازه (' . $range['days_count'] . ' روز) حذف شود؟';
            @endphp
            <div class="daily-ovr-card {{ $ov->available_count === 0 ? 'daily-ovr-card--closed' : '' }}">
                <div class="daily-ovr-card__head">
                    <div>
                        @if($isSingleDay)
                        <div class="daily-ovr-card__date">{{ $fromJ }}</div>
                        <div class="daily-ovr-card__greg text-muted">{{ $range['date_from'] }}</div>
                        @else
                        <div class="daily-ovr-card__date">{{ $fromJ }} <span class="text-muted fw-normal">تا</span> {{ $toJ }}</div>
                        <div class="daily-ovr-card__greg text-muted">{{ $range['date_from'] }} — {{ $range['date_to'] }}</div>
                        <div class="daily-ovr-card__days">
                            <span class="badge bg-light text-dark border">{{ $range['days_count'] }} روز</span>
                        </div>
                        @endif
                    </div>
                    <x-host.can page="room-types.daily-availability" action="delete" :panel="$panelKey">
                    <form action="{{ route($rangeDestroyRouteName, [$accommodation, $roomType]) }}"
                          method="POST" class="ms-2">
                        @csrf @method('DELETE')
                        <input type="hidden" name="date_from" value="{{ $range['date_from'] }}">
                        <input type="hidden" name="date_to" value="{{ $range['date_to'] }}">
                        <button type="submit" data-swal-confirm="{{ $deleteMessage }}"
                                class="btn btn-sm btn-outline-danger" title="حذف بازه">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                    </x-host.can>
                </div>

                <div class="daily-ovr-card__cap">
                    <span class="badge {{ $ov->available_count === 0 ? 'bg-danger' : 'bg-primary' }}">
                        {{ $ov->available_count }} از {{ $roomType->room_count }} اتاق
                    </span>
                </div>

                @if($rateRows->isNotEmpty())
                <div class="daily-ovr-card__rates">
                    @foreach($rateRows as $rateOvr)
                    <div class="daily-ovr-rate-row">
                        <span class="daily-ovr-rate-name">{{ $rateOvr->roomRate?->name ?? 'تعرفه' }}</span>
                        <span class="daily-ovr-rate-val">
                            @if($rateOvr->discount_percentage < 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">{{ abs($rateOvr->discount_percentage) }}٪ تخفیف</span>
                            @elseif($rateOvr->discount_percentage > 0)
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ $rateOvr->discount_percentage }}٪ گران‌تر</span>
                            @elseif($rateOvr->custom_price)
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">{{ \App\Support\PdfPersian::toPersianDigits(number_format($rateOvr->custom_price, 0, '.', ',')) }} ریال</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                            @if($rateOvr->price_label)
                                <span class="daily-ovr-rate-label">{{ $rateOvr->price_label }}</span>
                            @endif
                        </span>
                    </div>
                    @endforeach
                </div>
                @elseif($ov->discount_percentage || $ov->custom_price || $ov->price_label)
                <div class="daily-ovr-card__rates">
                    <div class="daily-ovr-rate-row">
                        <span class="daily-ovr-rate-name">همه تعرفه‌ها</span>
                        <span class="daily-ovr-rate-val">
                            @if($ov->discount_percentage < 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">{{ abs($ov->discount_percentage) }}٪ تخفیف</span>
                            @elseif($ov->discount_percentage > 0)
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ $ov->discount_percentage }}٪ گران‌تر</span>
                            @elseif($ov->custom_price)
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">{{ \App\Support\PdfPersian::toPersianDigits(number_format($ov->custom_price, 0, '.', ',')) }} ریال</span>
                            @endif
                            @if($ov->price_label)
                                <span class="daily-ovr-rate-label">{{ $ov->price_label }}</span>
                            @endif
                        </span>
                    </div>
                </div>
                @endif

                @if($ov->reason)
                <div class="daily-ovr-card__reason">
                    <i class="bi bi-chat-left-text me-1"></i>{{ $ov->reason }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@once
@push('styles')
<style>
.daily-ovr-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    max-height: 28rem;
    overflow-y: auto;
    padding-inline-end: 2px;
}
.daily-ovr-card {
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: .75rem .85rem;
    background: var(--bs-body-bg);
    transition: border-color .15s, box-shadow .15s;
}
.daily-ovr-card:hover {
    border-color: rgba(var(--bs-primary-rgb), .35);
    box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
}
.daily-ovr-card--closed {
    border-color: rgba(var(--bs-danger-rgb), .35);
    background: rgba(var(--bs-danger-rgb), .03);
}
.daily-ovr-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .5rem;
}
.daily-ovr-card__date {
    font-weight: 700;
    font-size: .95rem;
    line-height: 1.35;
}
.daily-ovr-card__greg {
    font-size: .7rem;
    margin-top: 2px;
    direction: ltr;
    text-align: right;
}
.daily-ovr-card__days { margin-top: .35rem; }
.daily-ovr-card__cap { margin-bottom: .5rem; }
.daily-ovr-card__rates {
    display: flex;
    flex-direction: column;
    gap: .4rem;
    margin-bottom: .35rem;
}
.daily-ovr-rate-row {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: .4rem .5rem;
    border-radius: .5rem;
    background: var(--bs-light);
}
.daily-ovr-rate-name {
    font-size: .78rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.daily-ovr-rate-val {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .35rem;
}
.daily-ovr-rate-label {
    font-size: .72rem;
    color: var(--bs-secondary-color);
}
.daily-ovr-card__reason {
    font-size: .75rem;
    color: var(--bs-secondary-color);
    border-top: 1px dashed var(--bs-border-color);
    padding-top: .45rem;
    margin-top: .25rem;
}
</style>
@endpush
@endonce
