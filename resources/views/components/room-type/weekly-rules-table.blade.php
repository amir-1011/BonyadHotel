@props([
    'weeklyRules',
    'rateWeeklyRules' => collect(),
    'destroyRouteName',
    'rateDestroyRouteName' => null,
    'accommodation',
    'roomType',
    'panel' => null,
])

@php
    $panelKey = $panel ?? (str_starts_with($destroyRouteName ?? '', 'host.') ? 'host' : 'admin');
    $legacyCount = $weeklyRules->count();
    $rateCount = $rateWeeklyRules->count();
    $totalCount = $legacyCount + $rateCount;

    $grouped = [];

    foreach ($weeklyRules as $rule) {
        $grouped[$rule->weekday]['label'] = $rule->weekdayLabel();
        $grouped[$rule->weekday]['items'][] = [
            'type'       => 'legacy',
            'rule'       => $rule,
            'rate_name'  => 'همه تعرفه‌ها',
            'discount'   => $rule->discount_percentage,
            'custom'     => $rule->custom_price,
            'label'      => $rule->price_label,
            'reason'     => $rule->reason,
        ];
    }

    foreach ($rateWeeklyRules as $rule) {
        $grouped[$rule->weekday]['label'] = $rule->weekdayLabel();
        $grouped[$rule->weekday]['items'][] = [
            'type'       => 'rate',
            'rule'       => $rule,
            'rate_name'  => $rule->roomRate?->name ?? 'تعرفه',
            'discount'   => $rule->discount_percentage,
            'custom'     => $rule->custom_price,
            'label'      => $rule->price_label,
            'reason'     => $rule->reason,
        ];
    }

    ksort($grouped);
@endphp

<div class="card shadow-sm border-0 rounded-4 mt-3">
    <div class="card-header bg-white border-bottom rounded-top-4">
        <h6 class="mb-0 fw-bold">
            <i class="bi bi-arrow-repeat me-2 text-indigo"></i>
            قوانین دائمی هفتگی
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">{{ $totalCount }}</span>
        </h6>
    </div>
    <div class="card-body p-3">
        @if($totalCount === 0)
        <div class="text-center py-4 text-muted small">
            <i class="bi bi-calendar-week d-block fs-3 mb-2 opacity-50"></i>
            هنوز قانون هفتگی ثبت نشده است.
        </div>
        @else
        <div class="weekly-rule-list">
            @foreach($grouped as $weekday => $group)
            <div class="weekly-rule-card">
                <div class="weekly-rule-card__day">
                    <i class="bi bi-calendar-day me-1"></i>{{ $group['label'] }}
                </div>

                @foreach($group['items'] as $item)
                <div class="weekly-rule-rate-row">
                    <div class="weekly-rule-rate-row__top">
                        <span class="weekly-rule-rate-name">{{ $item['rate_name'] }}</span>
                        @if($item['type'] === 'legacy')
                        <x-host.can page="room-types.daily-availability" action="delete" :panel="$panelKey">
                        <form action="{{ route($destroyRouteName, [$accommodation, $roomType, $item['rule']]) }}"
                              method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" data-swal-confirm="این قانون هفتگی حذف شود؟"
                                    class="btn btn-sm btn-outline-danger py-0 px-1" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        </x-host.can>
                        @elseif($rateDestroyRouteName)
                        <x-host.can page="room-types.daily-availability" action="delete" :panel="$panelKey">
                        <form action="{{ route($rateDestroyRouteName, [$accommodation, $roomType, $item['rule']]) }}"
                              method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" data-swal-confirm="این قانون هفتگی حذف شود؟"
                                    class="btn btn-sm btn-outline-danger py-0 px-1" title="حذف">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        </x-host.can>
                        @endif
                    </div>

                    <div class="weekly-rule-rate-row__vals">
                        @if($item['discount'])
                            @if($item['discount'] < 0)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">{{ abs($item['discount']) }}٪ تخفیف</span>
                            @elseif($item['discount'] > 0)
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ $item['discount'] }}٪ گران‌تر</span>
                            @endif
                        @endif
                        @if($item['custom'])
                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">
                                {{ \App\Support\PdfPersian::toPersianDigits(number_format($item['custom'], 0, '.', ',')) }} ریال / تخت
                            </span>
                        @endif
                        @if(!$item['discount'] && !$item['custom'] && !$item['label'])
                            <span class="text-muted small">—</span>
                        @endif
                    </div>

                    @if($item['label'])
                    <div class="weekly-rule-rate-label">{{ $item['label'] }}</div>
                    @endif

                    @if($item['reason'])
                    <div class="weekly-rule-rate-reason">
                        <i class="bi bi-chat-left-text me-1"></i>{{ $item['reason'] }}
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@once
@push('styles')
<style>
.text-indigo { color: #4f46e5 !important; }
.weekly-rule-list {
    display: flex;
    flex-direction: column;
    gap: .75rem;
    max-height: 24rem;
    overflow-y: auto;
    padding-inline-end: 2px;
}
.weekly-rule-card {
    border: 1px solid var(--bs-border-color);
    border-radius: .75rem;
    padding: .65rem .75rem .75rem;
    background: var(--bs-body-bg);
    transition: border-color .15s, box-shadow .15s;
}
.weekly-rule-card:hover {
    border-color: rgba(79, 70, 229, .3);
    box-shadow: 0 4px 14px rgba(15, 23, 42, .06);
}
.weekly-rule-card__day {
    font-weight: 700;
    font-size: .88rem;
    color: #4f46e5;
    margin-bottom: .5rem;
    padding-bottom: .35rem;
    border-bottom: 1px dashed var(--bs-border-color);
}
.weekly-rule-rate-row {
    padding: .45rem .5rem;
    border-radius: .5rem;
    background: var(--bs-light);
    margin-bottom: .4rem;
}
.weekly-rule-rate-row:last-child { margin-bottom: 0; }
.weekly-rule-rate-row__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .35rem;
}
.weekly-rule-rate-name {
    font-size: .78rem;
    font-weight: 600;
    color: var(--bs-secondary-color);
}
.weekly-rule-rate-row__vals {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
}
.weekly-rule-rate-label {
    font-size: .72rem;
    color: var(--bs-secondary-color);
    margin-top: .3rem;
}
.weekly-rule-rate-reason {
    font-size: .7rem;
    color: var(--bs-secondary-color);
    margin-top: .3rem;
    padding-top: .3rem;
    border-top: 1px dotted var(--bs-border-color);
}
</style>
@endpush
@endonce
