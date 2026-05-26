@extends('layouts.admin')


@push('styles')
<style>
.kpi-card { border-radius: 14px; border: none; transition: transform .2s, box-shadow .2s; }
.kpi-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.10)!important; }
.kpi-icon { width: 52px; height: 52px; border-radius: 12px; display:flex; align-items:center; justify-content:center; font-size: 1.4rem; flex-shrink:0; }
.chart-card { border-radius: 14px; border: none; }
.status-dot { width:10px; height:10px; border-radius:50%; display:inline-block; }
.booking-row:hover { background: #f8fafc; }
</style>
@endpush

@section('content')

<div>


{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a wire:navigate href="{{ route('admin.dashboard') }}">داشبورد</a></li>
        <li class="breadcrumb-item"><a wire:navigate href="{{ route('admin.accommodations.index') }}">اقامتگاه‌ها</a></li>
        <li class="breadcrumb-item active">گزارش فروش</li>
    </ol>
</nav>

{{-- Accommodation Header --}}
<div class="card chart-card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3">
            @if($accommodation->image)
            <img src="{{ Storage::url($accommodation->image) }}" class="rounded-3 object-fit-cover" style="width:72px;height:72px;" alt="">
            @else
            <div class="rounded-3 bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:72px;height:72px;flex-shrink:0">
                <i class="bi bi-building text-primary fs-2"></i>
            </div>
            @endif
            <div class="flex-grow-1">
                <h4 class="mb-1 fw-bold">{{ $accommodation->name }}</h4>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>{{ $accommodation->city->province->name ?? '' }} &mdash; {{ $accommodation->city->name ?? '' }}</span>
                    <span class="badge bg-info bg-opacity-80">{{ $accommodation->typeLabel() }}</span>
                    <span class="badge {{ $accommodation->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $accommodation->is_active ? 'فعال' : 'غیرفعال' }}</span>
                    @if($reviewCount > 0)
                    <span class="small text-warning fw-semibold"><i class="bi bi-star-fill me-1"></i>{{ number_format($avgRating,1) }} ({{ $reviewCount }} نظر)</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <a wire:navigate href="{{ route('admin.accommodations.edit', $accommodation) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil me-1"></i>ویرایش</a>
                <a wire:navigate href="{{ route('admin.bookings.index', ['search' => $accommodation->name]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-check me-1"></i>رزروها</a>
            </div>
        </div>
    </div>
</div>

{{-- ─── KPI Cards ─────────────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['label'=>'کل درآمد (تومان)',    'value'=> number_format($totalRevenue),      'icon'=>'currency-exchange', 'bg'=>'bg-primary',   'text'=>'text-primary',   'sub'=> $totalConfirmed . ' رزرو تأیید‌شده'],
        ['label'=>'درآمد این ماه',        'value'=> number_format($thisMonth),          'icon'=>'calendar-month',    'bg'=>'bg-success',   'text'=>'text-success',   'sub'=> $growthRate !== null ? ($growthRate >= 0 ? '↑' : '↓') . abs($growthRate) . '% نسبت به ماه قبل' : 'ماه اول'],
        ['label'=>'درآمد این هفته',       'value'=> number_format($thisWeek),           'icon'=>'calendar-week',     'bg'=>'bg-info',      'text'=>'text-info',      'sub'=> 'از ابتدای هفته جاری'],
        ['label'=>'درآمد امروز',          'value'=> number_format($today),              'icon'=>'sun-fill',          'bg'=>'bg-warning',   'text'=>'text-warning',   'sub'=> \Morilog\Jalali\Jalalian::fromCarbon(now())->format('Y/m/d')],
        ['label'=>'میانگین هر رزرو',      'value'=> number_format($avgRevPerBooking),   'icon'=>'calculator-fill',   'bg'=>'bg-secondary', 'text'=>'text-secondary', 'sub'=> 'تومان / رزرو'],
        ['label'=>'کل رزروها',            'value'=> number_format($totalBookings),      'icon'=>'calendar-check-fill','bg'=>'bg-primary',  'text'=>'text-primary',   'sub'=> $totalConfirmed . ' تأیید / ' . $totalPending . ' انتظار / ' . $totalCancelled . ' لغو'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card shadow-sm h-100">
            <div class="card-body d-flex flex-column align-items-start gap-2 p-3">
                <div class="kpi-icon {{ $k['bg'] }} bg-opacity-10">
                    <i class="bi bi-{{ $k['icon'] }} {{ $k['text'] }}"></i>
                </div>
                <div>
                    <div class="fw-bold fs-6 text-dark">{{ $k['value'] }}</div>
                    <div class="text-muted small">{{ $k['label'] }}</div>
                    <div class="text-muted" style="font-size:.7rem">{{ $k['sub'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- ─── Charts Row 1: Daily Revenue + Status Doughnut ─────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-activity me-2 text-primary"></i>درآمد و رزرو – ۳۰ روز گذشته</h6>
                <div class="d-flex gap-2 small text-muted">
                    <span><span class="status-dot bg-primary me-1"></span>درآمد</span>
                    <span><span class="status-dot bg-success me-1"></span>تعداد رزرو</span>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="chart-daily"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-pie-chart-fill me-2 text-warning"></i>وضعیت رزروها</h6>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <div id="chart-status" style="width:100%"></div>
                <div class="d-flex gap-3 mt-2 flex-wrap justify-content-center" style="font-size:.78rem">
                    @foreach($statusBreakdown as $s)
                    @php
                        $colors = ['confirmed'=>'#22c55e','pending'=>'#f59e0b','cancelled'=>'#ef4444'];
                        $labels = ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'];
                        $c = $colors[$s->status] ?? '#94a3b8';
                        $l = $labels[$s->status] ?? $s->status;
                    @endphp
                    <div class="text-center">
                        <div style="width:10px;height:10px;border-radius:50%;background:{{ $c }};display:inline-block;margin-left:4px"></div>
                        <span>{{ $l }}</span>
                        <div class="fw-bold text-dark">{{ number_format($s->count) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ─── Charts Row 2: Monthly Bar + Room-type Horizontal Bar ──────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-bar-chart-fill me-2 text-success"></i>درآمد ماهانه – ۱۲ ماه گذشته</h6>
            </div>
            <div class="card-body p-2">
                <div id="chart-monthly"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card chart-card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-list-ol me-2 text-info"></i>درآمد بر اساس نوع اتاق</h6>
            </div>
            <div class="card-body px-3 py-2">
                @if($roomTypeBreakdown->isEmpty())
                <div class="text-center text-muted py-4 small">داده‌ای موجود نیست</div>
                @else
                @php $maxTotal = $roomTypeBreakdown->max('total') ?: 1; @endphp
                <div class="d-flex flex-column gap-3 pt-1">
                    @foreach($roomTypeBreakdown as $idx => $rt)
                    @php
                        $pct   = round(($rt->total / $maxTotal) * 100);
                        $colors = ['#0ea5e9','#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
                        $color  = $colors[$idx % count($colors)];
                    @endphp
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-semibold text-dark" style="font-size:.85rem;max-width:65%;line-height:1.3">{{ $rt->rt_name }}</span>
                            <span class="text-muted" style="font-size:.78rem">{{ number_format($rt->total) }} ت &mdash; {{ number_format($rt->count) }} رزرو</span>
                        </div>
                        <div class="progress" style="height:10px;border-radius:6px;background:#e9ecef">
                            <div class="progress-bar" role="progressbar"
                                 style="width:{{ $pct }}%;background:{{ $color }};border-radius:6px;"
                                 aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
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
        <div class="card chart-card shadow-sm h-100 text-center p-3">
            <div class="text-muted small mb-1"><i class="bi bi-graph-up me-1"></i>نرخ رشد درآمد (ماه جاری vs ماه قبل)</div>
            @if($growthRate !== null)
            <div class="display-5 fw-bold {{ $growthRate >= 0 ? 'text-success' : 'text-danger' }}">
                {{ $growthRate >= 0 ? '+' : '' }}{{ $growthRate }}%
            </div>
            <div class="text-muted small mt-1">
                ماه قبل: {{ number_format($lastMonth) }} ت
                <i class="bi bi-arrow-left-right mx-1"></i>
                ماه جاری: {{ number_format($thisMonth) }} ت
            </div>
            @else
            <div class="display-5 fw-bold text-muted">—</div>
            <div class="text-muted small">اطلاعات ماه قبل موجود نیست</div>
            @endif
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card chart-card shadow-sm h-100 text-center p-3">
            <div class="text-muted small mb-1"><i class="bi bi-percent me-1"></i>نرخ تبدیل رزرو</div>
            <div class="display-5 fw-bold text-primary">
                {{ $totalBookings > 0 ? round(($totalConfirmed / $totalBookings) * 100) : 0 }}%
            </div>
            <div class="text-muted small mt-1">{{ $totalConfirmed }} رزرو موفق از {{ $totalBookings }} درخواست</div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card chart-card shadow-sm h-100 text-center p-3">
            <div class="text-muted small mb-1"><i class="bi bi-house me-1"></i>اطلاعات ظرفیت</div>
            <div class="fw-bold fs-5 text-dark mt-2">{{ $accommodation->rooms }} اتاق / {{ $accommodation->capacity }} نفر</div>
            <div class="text-muted small mt-1">{{ $accommodation->roomTypes->count() }} نوع اتاق ثبت‌شده</div>
            @if($accommodation->price_per_night > 0)
            <div class="text-muted small">نرخ پایه: {{ number_format($accommodation->price_per_night) }} ت/شب</div>
            @endif
        </div>
    </div>
</div>

{{-- ─── Recent Bookings Table ──────────────────────────────────────────────── --}}
<div class="card chart-card shadow-sm">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <h6 class="mb-0 fw-bold"><i class="bi bi-table me-2 text-secondary"></i>آخرین رزروها</h6>
        <a wire:navigate href="{{ route('admin.bookings.index', ['search' => $accommodation->name]) }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
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
                    <tr class="booking-row">
                        <td><code class="small">{{ $b->tracking_code }}</code></td>
                        <td>
                            <a wire:navigate href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark small">
                                {{ $b->user->name ?? $b->user->mobile }}
                            </a>
                        </td>
                        <td class="small text-muted">{{ $b->roomType->name ?? '—' }}</td>
                        <td class="small">{{ $b->check_in ? \Morilog\Jalali\Jalalian::fromCarbon($b->check_in)->format('Y/m/d') : '—' }}</td>
                        <td class="small">{{ $b->check_out ? \Morilog\Jalali\Jalalian::fromCarbon($b->check_out)->format('Y/m/d') : '—' }}</td>
                        <td class="small text-center">{{ $b->nights }}</td>
                        <td class="small">{{ number_format($b->total_price) }} <span class="text-muted">ت</span></td>
                        <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                        <td>
                            <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.7rem"><i class="bi bi-eye"></i></a>
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
<script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}"></script>
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
            { name: 'درآمد (تومان)', type: 'area', data: dailyTotals },
            { name: 'تعداد رزرو',   type: 'line', data: dailyCounts }
        ],
        chart: { height: 280, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif' },
        colors: ['#3b82f6', '#22c55e'],
        fill: { type: ['gradient','solid'], gradient: { shadeIntensity: 1, opacityFrom: .4, opacityTo: .05 } },
        stroke: { width: [2,2], curve: 'smooth' },
        xaxis: { categories: dailyDates, labels: { rotate: -30, style: { fontSize: '10px' } }, tickAmount: 10 },
        yaxis: [
            { title: { text: 'درآمد (ت)' }, labels: { formatter: v => persianNum(Math.round(v)) } },
            { opposite: true, title: { text: 'تعداد' }, labels: { formatter: v => persianNum(v) } }
        ],
        tooltip: { shared: true, intersect: false, y: [{ formatter: v => persianNum(v) + ' ت' }, { formatter: v => persianNum(v) + ' رزرو' }] },
        grid: { borderColor: '#f1f5f9' },
        legend: { show: false }
    }).render();

    // ── Status doughnut ─────────────────────────────────────────────────────
    @php
        $statusData = $statusBreakdown->map(fn($s) => (int)$s->count)->values()->toArray();
        $statusLabelsArr = $statusBreakdown->map(fn($s) => ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'][$s->status] ?? $s->status)->values()->toArray();
        $statusColors = $statusBreakdown->map(fn($s) => ['confirmed'=>'#22c55e','pending'=>'#f59e0b','cancelled'=>'#ef4444'][$s->status] ?? '#94a3b8')->values()->toArray();
    @endphp
    new ApexCharts(document.querySelector('#chart-status'), {
        series: @json($statusData),
        labels: @json($statusLabelsArr),
        colors: @json($statusColors),
        chart: { type: 'donut', height: 220, fontFamily: 'Vazirmatn, sans-serif' },
        plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'کل', formatter: w => persianNum(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
        dataLabels: { enabled: false },
        legend: { show: false },
        tooltip: { y: { formatter: v => persianNum(v) + ' رزرو' } }
    }).render();

    // ── Monthly bar ─────────────────────────────────────────────────────────
    const monthlyData = @json($monthlyRevenue);
    new ApexCharts(document.querySelector('#chart-monthly'), {
        series: [
            { name: 'درآمد (تومان)', type: 'bar',  data: monthlyData.map(r => r.total) },
            { name: 'تعداد رزرو',   type: 'line', data: monthlyData.map(r => r.count) }
        ],
        chart: { height: 270, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif' },
        colors: ['#10b981', '#6366f1'],
        fill: { type: ['solid', 'solid'] },
        stroke: { width: [0, 3], curve: 'smooth' },
        markers: { size: [0, 4] },
        plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: monthlyData.map(r => r.month),
            labels: { rotate: -30, style: { fontSize: '10px' } },
            tickAmount: 12
        },
        yaxis: [
            { seriesName: 'درآمد (تومان)', labels: { formatter: v => persianNum(Math.round(v)) }, title: { text: 'درآمد (ت)', style: { fontSize: '11px' } } },
            { seriesName: 'تعداد رزرو', opposite: true, labels: { formatter: v => persianNum(Math.round(v)) }, title: { text: 'تعداد', style: { fontSize: '11px' } } }
        ],
        tooltip: { shared: true, intersect: false, y: [{ formatter: v => persianNum(v) + ' ت' }, { formatter: v => persianNum(v) + ' رزرو' }] },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        legend: { show: true, position: 'top', fontFamily: 'Vazirmatn, sans-serif', fontSize: '12px' }
    }).render();

    // ── Room-type: rendered as HTML progress bars in the template (no JS needed)
})();
</script>
@endpush
