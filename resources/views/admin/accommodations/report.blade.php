@extends('layouts.admin')

@section('pageTitle')
گزارش فروش — {{ $accommodation->name }}
@endsection

@section('content')

<div>

{{-- ── Accommodation header card ───────────────────────── --}}
<div class="ta-card mb-3">
    <div class="ta-card__body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            @if($accommodation->image)
            <img src="{{ Storage::url($accommodation->image) }}" class="rounded-3 object-fit-cover" style="width:72px;height:72px;" alt="">
            @else
            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:72px;height:72px;flex-shrink:0;background:var(--ta-brand-50)">
                <i class="bi bi-building text-primary fs-2"></i>
            </div>
            @endif
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-bold">{{ $accommodation->name }}</h4>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $accommodation->city->province->name ?? '' }} &mdash; {{ $accommodation->city->name ?? '' }}</span>
                    <span class="badge bg-info">{{ $accommodation->typeLabel() }}</span>
                    <span class="badge {{ $accommodation->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $accommodation->is_active ? 'فعال' : 'غیرفعال' }}</span>
                    @if($reviewCount > 0)
                    <span class="small text-warning fw-semibold"><i class="bi bi-star-fill me-1"></i>{{ \App\Support\PdfPersian::toPersianDigits(number_format($avgRating,1)) }} ({{ $reviewCount }} نظر)</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a wire:navigate href="{{ route('admin.accommodations.edit', $accommodation) }}" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>ویرایش</a>
                <a wire:navigate href="{{ route('admin.bookings.index', ['search' => $accommodation->name]) }}" class="btn btn-sm btn-primary"><i class="bi bi-calendar-check me-1"></i>رزروها</a>
            </div>
        </div>
    </div>
</div>

{{-- ─── KPI Cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    @php
    $metrics = [
        ['label'=>'کل درآمد (ریال)', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($totalRevenue)), 'icon'=>'cash-stack',
         'pill'=> null, 'sub'=> $totalConfirmed . ' رزرو تأیید‌شده'],
        ['label'=>'درآمد این ماه', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($thisMonth)), 'icon'=>'calendar-month',
         'pill'=> $growthRate, 'sub'=> $growthRate !== null ? 'نسبت به ماه قبل' : 'ماه اول'],
        ['label'=>'درآمد این هفته', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($thisWeek)), 'icon'=>'calendar-week',
         'pill'=> null, 'sub'=> 'از ابتدای هفته جاری'],
        ['label'=>'درآمد امروز', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($today)), 'icon'=>'sun',
         'pill'=> null, 'sub'=> \Morilog\Jalali\Jalalian::fromCarbon(now())->format('Y/m/d')],
        ['label'=>'میانگین هر رزرو', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($avgRevPerBooking)), 'icon'=>'calculator',
         'pill'=> null, 'sub'=> 'ریال / رزرو'],
        ['label'=>'کل رزروها', 'value'=> \App\Support\PdfPersian::toPersianDigits(number_format($totalBookings)), 'icon'=>'calendar-check',
         'pill'=> null, 'sub'=> $totalConfirmed . ' تأیید / ' . $totalPending . ' انتظار / ' . $totalCancelled . ' لغو'],
    ];
    @endphp
    @foreach($metrics as $m)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="ta-metric">
            <div class="d-flex align-items-start justify-content-between">
                <div class="ta-metric__icon"><i class="bi bi-{{ $m['icon'] }}"></i></div>
                @if($m['pill'] !== null)
                <span class="ta-trend {{ $m['pill'] >= 0 ? 'up' : 'down' }}">
                    <i class="bi bi-arrow-{{ $m['pill'] >= 0 ? 'up' : 'down' }}"></i>{{ abs($m['pill']) }}٪
                </span>
                @endif
            </div>
            <div class="ta-metric__label">{{ $m['label'] }}</div>
            <div class="ta-metric__value" style="font-size:1.3rem">{{ $m['value'] }}</div>
            <div class="text-muted mt-1" style="font-size:.72rem">{{ $m['sub'] }}</div>
        </div>
    </div>
    @endforeach
</div>

{{-- ─── Charts Row 1: Daily Revenue + Status Doughnut ─────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <div>
                    <h2 class="ta-card__title">درآمد و رزرو</h2>
                    <div class="ta-card__sub">روند ۳۰ روز گذشته</div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="ta-legend"><span class="dot" style="background:var(--ta-brand-500)"></span>درآمد</span>
                    <span class="ta-legend"><span class="dot" style="background:var(--ta-success-500)"></span>تعداد رزرو</span>
                </div>
            </div>
            <div class="ta-card__body">
                <div id="chart-daily" style="min-height:300px"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title">وضعیت رزروها</h2>
            </div>
            <div class="ta-card__body d-flex flex-column align-items-center justify-content-center">
                <div id="chart-status" style="width:100%"></div>
                <div class="d-flex gap-3 mt-2 flex-wrap justify-content-center" style="font-size:.78rem">
                    @foreach($statusBreakdown as $s)
                    @php
                        $colors = ['confirmed'=>'#12b76a','pending'=>'#f79009','cancelled'=>'#f04438'];
                        $labels = ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'];
                        $c = $colors[$s->status] ?? '#98a2b3';
                        $l = $labels[$s->status] ?? $s->status;
                    @endphp
                    <div class="text-center">
                        <div style="width:10px;height:10px;border-radius:50%;background:{{ $c }};display:inline-block;margin-left:4px"></div>
                        <span>{{ $l }}</span>
                        <div class="fw-bold text-dark">{{ \App\Support\PdfPersian::toPersianDigits(number_format($s->count)) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Monthly bar + room-type breakdown ─────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <div>
                    <h2 class="ta-card__title">درآمد ماهانه</h2>
                    <div class="ta-card__sub">۱۲ ماه گذشته</div>
                </div>
            </div>
            <div class="ta-card__body">
                <div id="chart-monthly" style="min-height:280px"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title">درآمد بر اساس نوع اتاق</h2>
            </div>
            <div class="ta-card__body">
                @if($roomTypeBreakdown->isEmpty())
                <div class="text-center text-muted py-4 small">داده‌ای موجود نیست</div>
                @else
                @php $rtColors = ['#465fff','#7592ff','#12b76a','#f79009','#f04438','#0ba5ec','#9b8afb']; @endphp
                <div id="chart-roomtype" style="min-height:280px"></div>
                <div class="d-flex flex-column gap-2 mt-1">
                    @foreach($roomTypeBreakdown as $idx => $rt)
                    <div class="d-flex justify-content-between align-items-center" style="font-size:.8rem">
                        <span class="d-inline-flex align-items-center gap-2" style="max-width:60%">
                            <span style="width:10px;height:10px;border-radius:50%;background:{{ $rtColors[$idx % count($rtColors)] }};display:inline-block;flex-shrink:0"></span>
                            <span class="fw-semibold text-dark text-truncate">{{ $rt->rt_name }}</span>
                        </span>
                        <span class="text-muted" style="font-size:.78rem">{{ \App\Support\PdfPersian::toPersianDigits(number_format($rt->total)) }} ریال &mdash; {{ \App\Support\PdfPersian::toPersianDigits(number_format($rt->count)) }} رزرو</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ─── Growth Rate Visual + Occupancy Info ───────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="ta-card h-100">
            <div class="ta-card__body text-center">
                <div class="text-muted small mb-1"><i class="bi bi-graph-up me-1"></i>نرخ رشد درآمد (ماه جاری vs ماه قبل)</div>
                @if($growthRate !== null)
                <div class="fw-bold {{ $growthRate >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:2.2rem">
                    {{ $growthRate >= 0 ? '+' : '' }}{{ $growthRate }}٪
                </div>
                <div class="text-muted small mt-1">
                    ماه قبل: {{ \App\Support\PdfPersian::toPersianDigits(number_format($lastMonth)) }} ریال
                    <i class="bi bi-arrow-left-right mx-1"></i>
                    ماه جاری: {{ \App\Support\PdfPersian::toPersianDigits(number_format($thisMonth)) }} ریال
                </div>
                @else
                <div class="fw-bold text-muted" style="font-size:2.2rem">—</div>
                <div class="text-muted small">اطلاعات ماه قبل موجود نیست</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="ta-card h-100">
            <div class="ta-card__body text-center">
                <div class="text-muted small mb-1"><i class="bi bi-percent me-1"></i>نرخ تبدیل رزرو</div>
                <div class="fw-bold text-primary" style="font-size:2.2rem">
                    {{ $totalBookings > 0 ? round(($totalConfirmed / $totalBookings) * 100) : 0 }}٪
                </div>
                <div class="text-muted small mt-1">{{ $totalConfirmed }} رزرو موفق از {{ $totalBookings }} درخواست</div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="ta-card h-100">
            <div class="ta-card__body text-center">
                <div class="text-muted small mb-1"><i class="bi bi-house me-1"></i>اطلاعات ظرفیت</div>
                <div class="fw-bold fs-5 text-dark mt-2">{{ $accommodation->rooms }} اتاق / {{ $accommodation->capacity }} نفر</div>
                <div class="text-muted small mt-1">{{ $accommodation->roomTypes->count() }} نوع اتاق ثبت‌شده</div>
                @if($accommodation->price_per_night > 0)
                <div class="text-muted small">نرخ پایه: {{ \App\Support\PdfPersian::toPersianDigits(number_format($accommodation->price_per_night)) }} ریال/شب/تخت</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ─── Recent Bookings Table ──────────────────────────────────────────────── --}}
<div class="ta-card">
    <div class="ta-card__head">
        <h2 class="ta-card__title">آخرین رزروها</h2>
        <a wire:navigate href="{{ route('admin.bookings.index', ['search' => $accommodation->name]) }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
    </div>
    <div class="ta-card__body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>کد رزرو</th>
                        <th>کاربر</th>
                        <th>نوع اتاق</th>
                        <th>ورود</th>
                        <th>خروج</th>
                        <th>شب</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentBookings as $b)
                    <tr>
                        <td><code class="small">{{ $b->tracking_code }}</code></td>
                        <td>
                            <a wire:navigate href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark small">
                                {{ $b->bookerName() }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $b->roomType->name ?? '—' }}</td>
                        <td class="small">{{ $b->check_in ? \Morilog\Jalali\Jalalian::fromCarbon($b->check_in)->format('Y/m/d') : '—' }}</td>
                        <td class="small">{{ $b->check_out ? \Morilog\Jalali\Jalalian::fromCarbon($b->check_out)->format('Y/m/d') : '—' }}</td>
                        <td class="small text-center">{{ $b->nights }}</td>
                        <td class="small">{{ \App\Support\PdfPersian::toPersianDigits(number_format($b->total_price)) }} <span class="text-muted">ریال</span></td>
                        <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                        <td>
                            <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="btn btn-sm btn-outline-primary" style="padding:.15rem .45rem;font-size:.75rem"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">هیچ رزروی ثبت نشده است.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


</div>

@endsection

@push('scripts')
<script src="{{ vasset('vendor/apexcharts/apexcharts.min.js') }}"></script>
<script>
(function () {
    const persianNum = v => new Intl.NumberFormat('fa-IR').format(v);

    // ── Daily chart ─────────────────────────────────────────────────────────
    const dailyData = @json($dailyRevenue);
    const dailyDates   = dailyData.map(r => r.day);
    const dailyTotals  = dailyData.map(r => r.total);
    const dailyCounts  = dailyData.map(r => r.count);

    new ApexCharts(document.querySelector('#chart-daily'), {
        series: [
            { name: 'درآمد (ریال)', type: 'area', data: dailyTotals },
            { name: 'تعداد رزرو',   type: 'line', data: dailyCounts }
        ],
        chart: { height: 300, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif' },
        colors: ['#465fff', '#12b76a'],
        fill: { type: ['gradient','solid'], gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
        stroke: { width: [2,2], curve: 'smooth' },
        dataLabels: { enabled: false },
        xaxis: { categories: dailyDates, labels: { rotate: -30, style: { fontSize: '10px', colors: '#667085' } }, tickAmount: 10, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: [
            { labels: { formatter: v => persianNum(Math.round(v)), style: { colors: '#667085' } } },
            { opposite: true, labels: { formatter: v => persianNum(v), style: { colors: '#667085' } } }
        ],
        tooltip: { shared: true, intersect: false, y: [{ formatter: v => persianNum(v) + ' ریال' }, { formatter: v => persianNum(v) + ' رزرو' }] },
        grid: { borderColor: '#f2f4f7', strokeDashArray: 4 },
        legend: { show: false }
    }).render();

    // ── Status doughnut ─────────────────────────────────────────────────────
    @php
        $stTotalCount = $statusBreakdown->sum('count');
        $statusData = $statusBreakdown->map(fn($s) => $stTotalCount > 0 ? round(($s->count / $stTotalCount) * 100) : 0)->values()->toArray();
        $statusLabelsArr = $statusBreakdown->map(fn($s) => ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'][$s->status] ?? $s->status)->values()->toArray();
        $statusColors = $statusBreakdown->map(fn($s) => ['confirmed'=>'#12b76a','pending'=>'#f79009','cancelled'=>'#f04438'][$s->status] ?? '#98a2b3')->values()->toArray();
    @endphp
    new ApexCharts(document.querySelector('#chart-status'), {
        series: @json($statusData),
        labels: @json($statusLabelsArr),
        colors: @json($statusColors),
        chart: { type: 'radialBar', height: 300, fontFamily: 'Vazirmatn, sans-serif' },
        plotOptions: { radialBar: {
            hollow: { size: '40%' },
            track: { background: 'rgba(242,244,247,0.85)', strokeWidth: '100%', margin: 6 },
            dataLabels: {
                name: { fontSize: '13px', color: '#667085', offsetY: -2 },
                value: { fontSize: '18px', fontWeight: 700, color: '#101828', offsetY: 4, formatter: v => persianNum(Math.round(v)) + '٪' },
                total: { show: true, label: 'کل رزرو', color: '#667085', formatter: () => persianNum(@json((int)$statusBreakdown->sum('count'))) }
            }
        }},
        stroke: { lineCap: 'round' },
        legend: { show: false },
        tooltip: { enabled: false }
    }).render();

    // ── Monthly bar ─────────────────────────────────────────────────────────
    const monthlyData = @json($monthlyRevenue);
    new ApexCharts(document.querySelector('#chart-monthly'), {
        series: [
            { name: 'درآمد (ریال)', type: 'bar',  data: monthlyData.map(r => r.total) },
            { name: 'تعداد رزرو',   type: 'line', data: monthlyData.map(r => r.count) }
        ],
        chart: { height: 280, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif' },
        colors: ['#465fff', '#12b76a'],
        fill: { type: ['solid', 'solid'] },
        stroke: { width: [0, 3], curve: 'smooth' },
        markers: { size: [0, 4] },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '50%' } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: monthlyData.map(r => r.month),
            labels: { rotate: -30, style: { fontSize: '10px', colors: '#667085' } },
            tickAmount: 12, axisBorder: { show: false }, axisTicks: { show: false }
        },
        yaxis: [
            { seriesName: 'درآمد (ریال)', labels: { formatter: v => persianNum(Math.round(v)), style: { colors: '#667085' } } },
            { seriesName: 'تعداد رزرو', opposite: true, labels: { formatter: v => persianNum(Math.round(v)), style: { colors: '#667085' } } }
        ],
        tooltip: { shared: true, intersect: false, y: [{ formatter: v => persianNum(v) + ' ریال' }, { formatter: v => persianNum(v) + ' رزرو' }] },
        grid: { borderColor: '#f2f4f7', strokeDashArray: 4 },
        legend: { show: true, position: 'top', horizontalAlign: 'right', fontFamily: 'Vazirmatn, sans-serif', fontSize: '12px', markers: { radius: 99 } }
    }).render();

    // ── Room-type radial ───────────────────────────────────
    @php
        $rtPalette  = ['#465fff','#7592ff','#12b76a','#f79009','#f04438','#0ba5ec','#9b8afb'];
        $rtTotalSum = $roomTypeBreakdown->sum('total');
        $rtNames    = $roomTypeBreakdown->map(fn($r) => $r->rt_name)->values()->toArray();
        $rtSeries   = $roomTypeBreakdown->map(fn($r) => $rtTotalSum > 0 ? round(($r->total / $rtTotalSum) * 100) : 0)->values()->toArray();
        $rtColorsArr = [];
        foreach ($roomTypeBreakdown as $i => $r) { $rtColorsArr[] = $rtPalette[$i % count($rtPalette)]; }
    @endphp
    const rtEl = document.querySelector('#chart-roomtype');
    if (rtEl) {
        new ApexCharts(rtEl, {
            series: @json($rtSeries),
            labels: @json($rtNames),
            colors: @json($rtColorsArr),
            chart: { type: 'radialBar', height: 300, fontFamily: 'Vazirmatn, sans-serif' },
            plotOptions: { radialBar: {
                hollow: { size: '38%' },
                track: { background: 'rgba(242,244,247,0.85)', strokeWidth: '100%', margin: 5 },
                dataLabels: {
                    name: { fontSize: '13px', color: '#667085', offsetY: -2 },
                    value: { fontSize: '18px', fontWeight: 700, color: '#101828', offsetY: 4, formatter: v => persianNum(Math.round(v)) + '٪' },
                    total: { show: true, label: 'کل رزرو', color: '#667085', formatter: () => persianNum(@json((int)$roomTypeBreakdown->sum('count'))) }
                }
            }},
            stroke: { lineCap: 'round' },
            legend: { show: false },
            tooltip: { enabled: false }
        }).render();
    }
})();
</script>
@endpush
