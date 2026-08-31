@props([
    'stats',
    'monthlyRevenue',
    'groups',
    'totals',
])

@php
    use App\Support\AdminStatSparkline;
    use App\Support\PdfPersian;

    $chartTypes = ['line', 'area', 'bar', 'smooth'];

    $revValues = [];
    foreach ($monthlyRevenue as $row) {
        $revValues[] = (float) $row->total;
    }
    $thisMonthRev = end($revValues) ?: 0;
    $prevMonthRev = count($revValues) > 1 ? $revValues[count($revValues) - 2] : 0;
    $revGrowth = $prevMonthRev > 0 ? round((($thisMonthRev - $prevMonthRev) / $prevMonthRev) * 100, 1) : null;
    $confirmRate = $stats['bookings'] > 0 ? round(($stats['confirmed'] / $stats['bookings']) * 100) : 0;

    $metrics = [
        [
            'label' => 'کل کاربران',
            'value' => PdfPersian::toPersianDigits(number_format($stats['users'])),
            'href' => route('admin.users.index'),
            'trend' => PdfPersian::toPersianDigits(number_format($stats['hosts'])).' میزبان',
            'up' => true,
            'spark' => AdminStatSparkline::points((float) $stats['users']),
        ],
        [
            'label' => 'اقامتگاه‌ها',
            'value' => PdfPersian::toPersianDigits(number_format($stats['accommodations'])),
            'href' => route('admin.accommodations.index'),
            'trend' => PdfPersian::toPersianDigits(number_format($stats['active_acc'])).' فعال',
            'up' => true,
            'spark' => AdminStatSparkline::points((float) $stats['active_acc'] + ($stats['accommodations'] * 0.3)),
        ],
        [
            'label' => 'کل رزروها',
            'value' => PdfPersian::toPersianDigits(number_format($stats['bookings'])),
            'href' => route('admin.bookings.index'),
            'trend' => PdfPersian::toPersianDigits((string) $confirmRate).'٪ تأیید',
            'up' => $confirmRate >= 50,
            'spark' => AdminStatSparkline::points((float) $stats['confirmed'] + ($stats['pending'] * 0.4)),
        ],
        [
            'label' => 'درآمد کل (ریال)',
            'value' => PdfPersian::toPersianDigits(number_format($stats['revenue'])),
            'href' => route('admin.bookings.index', ['status' => 'confirmed']),
            'trend' => $revGrowth !== null
                ? PdfPersian::toPersianDigits((string) abs($revGrowth)).'٪ ماهانه'
                : '—',
            'up' => ($revGrowth ?? 0) >= 0,
            'spark' => count($revValues) >= 2 ? $revValues : AdminStatSparkline::points((float) $stats['revenue']),
        ],
    ];

    $icons = [
        'veteran_70_spouses'          => 'award-fill',
        'veteran_50_69_dependents'    => 'patch-check-fill',
        'veteran_25_49_dependents'    => 'shield-fill',
        'martyr_children'             => 'heart-fill',
        'martyr_parents_dependents'   => 'people-fill',
        'martyr_spouse_dependents'    => 'person-heart',
        'freed_prisoner_dependents'   => 'flag-fill',
    ];

    $cards = [];
    foreach ($metrics as $index => $metric) {
        $cards[] = [
            'href' => $metric['href'],
            'label' => $metric['label'],
            'value' => $metric['value'],
            'value_suffix' => null,
            'trend' => $metric['trend'],
            'up' => $metric['up'],
            'sub' => null,
            'chart' => $chartTypes[$index % count($chartTypes)],
            'spark' => $metric['spark'],
        ];
    }

    foreach ($groups as $index => $group) {
        $active = (int) $group['nights'] > 0 || (int) $group['discount_amount'] > 0;
        $cards[] = [
            'href' => route('admin.bookings.index', ['status' => 'confirmed', 'veteran_type' => $group['key']]),
            'label' => $group['label'],
            'value' => $active
                ? PdfPersian::toPersianDigits(number_format($group['discount_amount']))
                : '—',
            'value_suffix' => $active ? 'تخفیف' : null,
            'trend' => PdfPersian::toPersianDigits((string) $group['discount_pct']).'٪ اقامت',
            'up' => true,
            'sub' => $active
                ? PdfPersian::toPersianDigits(number_format($group['nights'])).' شب'
                    .((int) $group['bookings_count'] > 0
                        ? ' · '.PdfPersian::toPersianDigits(number_format($group['bookings_count'])).' رزرو'
                        : '')
                : null,
            'chart' => $chartTypes[($index + 4) % count($chartTypes)],
            'spark' => AdminStatSparkline::points(
                (float) max(1, (int) $group['discount_amount'] + ((int) $group['nights'] * 1000))
            ),
        ];
    }

    $visibleCount = 4;
    $hiddenCount = max(count($cards) - $visibleCount, 0);
@endphp

<div class="admin-overview-stats" data-overview-stats>
    <div class="row g-3 mb-3">
        @foreach($cards as $index => $card)
            <div class="col-6 col-xl-3 admin-overview-stats__col {{ $index >= $visibleCount ? 'admin-overview-stats__col--hidden' : '' }}"
                 @if($index >= $visibleCount) hidden @endif>
                <a href="{{ $card['href'] }}" wire:navigate class="ta-stat-card text-decoration-none">
                    <div class="ta-stat-card__body">
                        <div class="ta-stat-card__top">
                            <div class="ta-stat-card__metric">
                                <span class="ta-stat-card__value">
                                    {{ $card['value'] }}
                                    @if($card['value_suffix'])
                                        <small class="ta-stat-card__value-suffix">{{ $card['value_suffix'] }}</small>
                                    @endif
                                </span>
                                @if($card['trend'])
                                    <span class="ta-stat-card__delta {{ $card['up'] ? 'is-up' : 'is-down' }}">
                                        ({{ $card['trend'] }}
                                        <i class="bi bi-arrow-{{ $card['up'] ? 'up' : 'down' }}-short"></i>)
                                    </span>
                                @endif
                            </div>
                            <button type="button"
                                    class="ta-stat-card__menu"
                                    tabindex="-1"
                                    aria-hidden="true"
                                    onclick="event.preventDefault(); event.stopPropagation();">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                        </div>
                        <div class="ta-stat-card__label">{{ $card['label'] }}</div>
                        @if($card['sub'])
                            <div class="ta-stat-card__sub">{{ $card['sub'] }}</div>
                        @endif
                    </div>
                    <div class="ta-stat-card__chart">
                        {!! AdminStatSparkline::svg($card['spark'], $card['chart']) !!}
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    @if($hiddenCount > 0)
        <div class="text-center mb-4">
            <button type="button"
                    class="btn btn-light btn-sm px-4 admin-overview-stats__toggle"
                    data-overview-stats-toggle
                    aria-expanded="false">
                <span class="admin-overview-stats__toggle-label" data-label-collapsed>
                    مشاهده بیشتر ({{ PdfPersian::toPersianDigits((string) $hiddenCount) }})
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </span>
                <span class="admin-overview-stats__toggle-label" data-label-expanded hidden>
                    بستن
                    <i class="bi bi-chevron-up" aria-hidden="true"></i>
                </span>
            </button>
        </div>
    @endif
</div>
