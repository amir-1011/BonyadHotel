<div>

{{-- ── Page header ─────────────────────────────────────────────────── --}}
<div class="ta-page-head mb-4">
    <div>
        <div class="text-muted small">نمای کلی اقامتگاه‌ها، رزروها، اشغال و فروش خدمات</div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        @if($this->showDashboardAccommodationFilter())
            @include('components.dashboard.accommodation-filter')
        @endif
        <span class="btn btn-light"><i class="bi bi-calendar3 me-2"></i>{{ \Morilog\Jalali\Jalalian::now()->format('Y/m/d') }}</span>
        @if($hostUser->hostCan('bookings.list', 'read'))
        <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-light"><i class="bi bi-calendar-check me-2"></i>رزروها</a>
        @endif
        <x-host.can page="accommodations.create" action="write">
        <a wire:navigate href="{{ route('host.accommodations.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>اقامتگاه جدید</a>
        </x-host.can>
    </div>
</div>

{{-- ── KPI cards ───────────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">
    @php
    $allMetrics = [
        ['perm'=>'accommodations', 'label'=>'اقامتگاه‌های من', 'value'=>number_format($stats['accommodations']), 'icon'=>'building',
         'sub'=>$stats['active_acc'].' فعال', 'href'=>route('host.accommodations.index')],
        ['perm'=>'bookings', 'label'=>'رزرو تأیید‌شده', 'value'=>number_format($stats['confirmed']), 'icon'=>'check-circle',
         'sub'=>$stats['pending'].' در انتظار', 'href'=>route('host.bookings.index',['status'=>'confirmed'])],
        ['perm'=>'bookings', 'label'=>'درآمد کل (تومان)', 'value'=>number_format($stats['revenue']), 'icon'=>'cash-stack',
         'sub'=>number_format($stats['today_revenue']).' ت امروز', 'href'=>route('host.bookings.index',['status'=>'confirmed'])],
        ['perm'=>'bookings', 'label'=>'درآمد این ماه', 'value'=>number_format($stats['this_month']), 'icon'=>'calendar-month',
         'sub'=>$stats['growth_rate']!==null ? ($stats['growth_rate']>=0?'+':'').$stats['growth_rate'].'٪ نسبت به ماه قبل' : 'ماه اول', 'href'=>null, 'pill'=>$stats['growth_rate']],
        ['perm'=>'bookings', 'label'=>'فروش خدمات', 'value'=>number_format($stats['services_revenue']), 'icon'=>'bag-check',
         'sub'=>'تومان از خدمات اضافی', 'href'=>null],
        ['perm'=>'reviews', 'label'=>'نظرات بی‌پاسخ', 'value'=>number_format($stats['pending_reviews']), 'icon'=>'chat-square-text',
         'sub'=>'نیاز به پاسخ', 'href'=>route('host.reviews.index',['replied'=>'0'])],
    ];
    $metrics = array_values(array_filter($allMetrics, fn($m) => $hostUser->hasHostPanelAccess($m['perm'])));
    @endphp
    @foreach($metrics as $m)
    <div class="col-6 col-md-4 col-xl-2">
        @if($m['href'])
        <a href="{{ $m['href'] }}" wire:navigate class="text-decoration-none d-block h-100">
        @endif
            <div class="ta-metric h-100">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="ta-metric__icon"><i class="bi bi-{{ $m['icon'] }}"></i></div>
                    @if(isset($m['pill']) && $m['pill'] !== null)
                    <span class="ta-trend {{ $m['pill'] >= 0 ? 'up' : 'down' }}">
                        <i class="bi bi-arrow-{{ $m['pill'] >= 0 ? 'up' : 'down' }}"></i>{{ abs($m['pill']) }}٪
                    </span>
                    @endif
                </div>
                <div class="ta-metric__label">{{ $m['label'] }}</div>
                <div class="ta-metric__value" style="font-size:1.25rem">{{ $m['value'] }}</div>
                <div class="text-muted mt-1" style="font-size:.72rem">{{ $m['sub'] }}</div>
            </div>
        @if($m['href'])
        </a>
        @endif
    </div>
    @endforeach
</div>

{{-- ── Occupancy calendar — full row ─────────────────────────────────── --}}
@if($hostUser->hasHostPanelAccess('bookings'))
<div class="row g-4 mb-4">
    <div class="col-12">
        <livewire:room-status-board
            panel="host"
            :dashboard-accommodation-ids="$effectiveAccommodationIds"
            :use-dashboard-filter="true"
            :wire:key="'host-rsb-'.$filterKey" />
    </div>
</div>
<div class="row g-4 mb-4">
    <div class="col-12">
        <livewire:occupancy-calendar
            panel="host"
            :dashboard-accommodation-ids="$effectiveAccommodationIds"
            :use-dashboard-filter="true"
            :wire:key="'host-occ-'.$filterKey" />
    </div>
</div>
@endif

{{-- ── Check-outs today / soon + active stays ────────────────────────── --}}
@if($hostUser->hasHostPanelAccess('bookings'))
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title text-danger"><i class="bi bi-box-arrow-left me-2"></i>خروج امروز</h2>
                <span class="badge bg-danger">{{ $checkoutsToday->count() }}</span>
            </div>
            <div class="ta-card__body p-0">
                <div class="list-group list-group-flush">
                    @forelse($checkoutsToday as $b)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="small">
                                <div class="fw-semibold">{{ $b->bookerName() }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ Str::limit($b->accommodation->name, 28) }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $b->roomLinesSummary() }}</div>
                            </div>
                            <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-sm btn-outline-danger" style="font-size:.72rem;padding:.2rem .45rem"><i class="bi bi-eye"></i></a>
                        </div>
                        <code class="small text-muted">{{ $b->tracking_code }}</code>
                    </div>
                    @empty
                    <div class="list-group-item text-muted small text-center py-4">امروز خروجی ثبت نشده</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title text-warning"><i class="bi bi-hourglass-split me-2"></i>نزدیک به پایان (۱–۲ روز)</h2>
                <span class="badge bg-warning text-dark">{{ $checkoutsSoon->count() }}</span>
            </div>
            <div class="ta-card__body p-0">
                <div class="list-group list-group-flush">
                    @forelse($checkoutsSoon as $b)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="small">
                                <div class="fw-semibold">{{ $b->bookerName() }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ Str::limit($b->accommodation->name, 28) }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $b->roomLinesSummary() }}</div>
                                <div class="text-warning fw-semibold" style="font-size:.72rem">خروج: @jalali($b->check_out)</div>
                            </div>
                            <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-sm btn-outline-warning" style="font-size:.72rem;padding:.2rem .45rem"><i class="bi bi-eye"></i></a>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item text-muted small text-center py-4">رزروی در شرف پایان نیست</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title text-success"><i class="bi bi-door-open me-2"></i>مهمانان فعلی</h2>
                <span class="badge bg-success">{{ $activeStays->count() }}</span>
            </div>
            <div class="ta-card__body p-0">
                <div class="list-group list-group-flush">
                    @forelse($activeStays as $b)
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="small">
                                <div class="fw-semibold">{{ $b->bookerName() }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ Str::limit($b->accommodation->name, 28) }}</div>
                                <div class="text-muted" style="font-size:.72rem">{{ $b->roomLinesSummary() }}</div>
                                <div class="text-muted" style="font-size:.72rem">تا @jalali($b->check_out)</div>
                            </div>
                            <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-sm btn-outline-success" style="font-size:.72rem;padding:.2rem .45rem"><i class="bi bi-eye"></i></a>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item text-muted small text-center py-4">مهمان فعالی نیست</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Charts ────────────────────────────────────────────────────────── --}}
@if($hostUser->hasHostPanelAccess('bookings'))
<div class="row g-4 mb-4" wire:key="host-charts-{{ $filterKey }}">
    <div class="col-12 col-xl-8">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <div>
                    <h2 class="ta-card__title">درآمد و رزرو</h2>
                    <div class="ta-card__sub">روند ۳۰ روز گذشته — بر اساس اقامتگاه‌های انتخاب‌شده</div>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="ta-legend"><span class="dot" style="background:var(--ta-brand-500)"></span>درآمد</span>
                    <span class="ta-legend"><span class="dot" style="background:var(--ta-success-500)"></span>تعداد رزرو</span>
                </div>
            </div>
            <div class="ta-card__body">
                <div id="host-chart-daily" wire:ignore style="min-height:300px"></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title">وضعیت رزروها</h2>
            </div>
            <div class="ta-card__body d-flex flex-column align-items-center justify-content-center">
                <div id="host-chart-status" wire:ignore style="width:100%"></div>
                <div class="d-flex gap-3 mt-2 flex-wrap justify-content-center" style="font-size:.78rem">
                    @foreach($statusBreakdown as $s)
                    @php
                        $colors = ['confirmed'=>'#12b76a','pending'=>'#f79009','cancelled'=>'#f04438'];
                        $labels = ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'];
                    @endphp
                    <div class="text-center">
                        <div style="width:10px;height:10px;border-radius:50%;background:{{ $colors[$s->status] ?? '#98a2b3' }};display:inline-block;margin-left:4px"></div>
                        <span>{{ $labels[$s->status] ?? $s->status }}</span>
                        <div class="fw-bold text-dark">{{ number_format($s->count) }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ═══ Accommodations performance (charts inside are wire:ignore) ═══ --}}
@if($hostUser->hasHostPanelAccess('accommodations'))
<div class="ta-card mb-4" wire:ignore>
    <div class="ta-card__head">
        <h2 class="ta-card__title"><i class="bi bi-bar-chart-line me-2 text-primary"></i>عملکرد اقامتگاه‌ها</h2>
        <a wire:navigate href="{{ route('host.accommodations.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
    </div>
    <div class="ta-card__body pt-2">
        <div class="row g-3">
            @forelse($myAccommodations as $acc)
            @php
                $todayVal = $accTodayRevenue[$acc->id] ?? 0;
                $weekVal  = $accWeekRevenue[$acc->id] ?? 0;
                $monthVal = $accMonthRevenue[$acc->id] ?? 0;
                $convRate = $acc->total_bookings_count > 0 ? round(($acc->confirmed_count / $acc->total_bookings_count) * 100) : 0;
            @endphp
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius:12px;overflow:hidden">
                    <div class="card-header bg-white border-bottom-0 pb-1 pt-3 px-3 d-flex align-items-start justify-content-between">
                        <div>
                            <div class="fw-bold text-dark" style="font-size:.95rem">{{ Str::limit($acc->name, 30) }}</div>
                            <div class="text-muted small">
                                <i class="bi bi-geo-alt me-1"></i>{{ $acc->city->name ?? '—' }}
                                <span class="badge {{ $acc->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-75 ms-1" style="font-size:.65rem">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span>
                            </div>
                        </div>
                        @if($hostUser->hasHostPanelAccess('bookings'))
                        <a wire:navigate href="{{ route('host.accommodations.report', $acc) }}" class="btn btn-sm btn-primary" style="font-size:.75rem">
                            <i class="bi bi-graph-up-arrow me-1"></i>گزارش
                        </a>
                        @endif
                    </div>
                    @if($hostUser->hasHostPanelAccess('bookings'))
                    <div class="px-3" id="host-spark-{{ $acc->id }}" wire:ignore style="min-height:56px"></div>
                    @endif
                    <div class="card-body pt-1 pb-2 px-3">
                        @if($hostUser->hasHostPanelAccess('bookings'))
                        <div class="row g-2 text-center mb-2">
                            <div class="col-4">
                                <div class="bg-light rounded p-2">
                                    <div class="text-muted" style="font-size:.65rem">امروز</div>
                                    <div class="fw-bold" style="font-size:.8rem">{{ number_format($todayVal) }}<small class="text-muted"> ت</small></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-light rounded p-2">
                                    <div class="text-muted" style="font-size:.65rem">این هفته</div>
                                    <div class="fw-bold" style="font-size:.8rem">{{ number_format($weekVal) }}<small class="text-muted"> ت</small></div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-light rounded p-2">
                                    <div class="text-muted" style="font-size:.65rem">این ماه</div>
                                    <div class="fw-bold text-primary" style="font-size:.8rem">{{ number_format($monthVal) }}<small class="text-muted"> ت</small></div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2" style="font-size:.75rem">
                            <span class="text-muted"><i class="bi bi-check-circle text-success"></i> {{ number_format($acc->confirmed_count) }}</span>
                            <span class="text-muted"><i class="bi bi-clock text-warning"></i> {{ number_format($acc->pending_count) }}</span>
                            <span class="text-muted"><i class="bi bi-percent text-info"></i> {{ $convRate }}%</span>
                        </div>
                        @endif
                        <div class="d-flex gap-1 mt-2">
                            <a wire:navigate href="{{ route('host.accommodations.edit', $acc) }}" class="btn btn-xs btn-outline-warning" style="padding:.15rem .4rem;font-size:.7rem"><i class="bi bi-pencil"></i></a>
                            @if($hostUser->hasHostPanelAccess('bookings'))
                            <a wire:navigate href="{{ route('host.bookings.index', ['accommodation_id' => $acc->id]) }}" class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.7rem"><i class="bi bi-calendar-check"></i></a>
                            @endif
                            <a href="{{ route('accommodations.show', $acc) }}" target="_blank" class="btn btn-xs btn-outline-secondary" style="padding:.15rem .4rem;font-size:.7rem"><i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-building fs-1 opacity-25 d-block mb-2"></i>
                هنوز اقامتگاهی ثبت نکرده‌اید
                <div class="mt-2"><a wire:navigate href="{{ route('host.accommodations.create') }}" class="btn btn-sm btn-success">افزودن اقامتگاه</a></div>
            </div>
            @endforelse
        </div>
    </div>
</div>
@endif

{{-- ── Services sold ───────────────────────────────────────────────────── --}}
@if($hostUser->hasHostPanelAccess('bookings'))
<div class="row g-4 mb-4">
    <div class="col-12 col-lg-4">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title"><i class="bi bi-pie-chart me-2"></i>خلاصه فروش خدمات</h2>
            </div>
            <div class="ta-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr><th>خدمت</th><th>تعداد</th><th>درآمد</th></tr>
                        </thead>
                        <tbody>
                            @forelse($serviceSummary as $svc)
                            <tr>
                                <td class="small fw-semibold">{{ Str::limit($svc->name, 22) }}</td>
                                <td class="small">{{ number_format($svc->total_qty) }}</td>
                                <td class="small">{{ number_format($svc->total_revenue) }} ت</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-4 small">خدمتی فروخته نشده</td></tr>
                            @endforelse
                        </tbody>
                        @if($serviceSummary->isNotEmpty())
                        <tfoot class="table-light">
                            <tr>
                                <td class="fw-semibold small">جمع</td>
                                <td class="small">{{ number_format($serviceSummary->sum('total_qty')) }}</td>
                                <td class="fw-semibold small">{{ number_format($stats['services_revenue']) }} ت</td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-8">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title"><i class="bi bi-bag-check me-2"></i>جزئیات خدمات فروخته‌شده</h2>
            </div>
            <div class="ta-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>خدمت</th>
                                <th>رزرو</th>
                                <th>اقامتگاه</th>
                                <th>قیمت واحد</th>
                                <th>تعداد</th>
                                <th>رایگان</th>
                                <th>تخفیف</th>
                                <th>نهایی</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($soldServices as $svc)
                            <tr>
                                <td>
                                    <strong class="small">{{ $svc->name }}</strong>
                                    @if($svc->serviceCatalog)
                                    <div class="text-muted" style="font-size:.68rem">{{ $svc->serviceCatalog->name }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($svc->booking)
                                    <a wire:navigate href="{{ route('host.bookings.show', $svc->booking) }}" class="text-decoration-none">
                                        <code>{{ $svc->booking->tracking_code }}</code>
                                    </a>
                                    @else — @endif
                                </td>
                                <td class="small text-muted">{{ Str::limit($svc->booking->accommodation->name ?? '—', 18) }}</td>
                                <td class="small">{{ number_format($svc->unit_price) }} ت</td>
                                <td class="small text-center">{{ $svc->quantity }}</td>
                                <td class="small text-center">{{ ($svc->free_units ?? 0) > 0 ? $svc->free_units : '—' }}</td>
                                <td class="small text-danger">
                                    @if($svc->discount_amount > 0)
                                        −{{ number_format($svc->discount_amount) }} ت
                                    @else — @endif
                                </td>
                                <td class="small fw-semibold">{{ number_format($svc->total) }} ت</td>
                            </tr>
                            @empty
                            <tr><td colspan="8" class="text-center text-muted py-4 small">هنوز خدمتی فروخته نشده است</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ── Recent bookings — full width table ──────────────────────────────── --}}
@if($hostUser->hasHostPanelAccess('bookings'))
<div class="ta-card">
    <div class="ta-card__head">
        <h2 class="ta-card__title">آخرین رزروها</h2>
        <a wire:navigate href="{{ route('host.bookings.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
    </div>
    <div class="ta-card__body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>کد</th>
                        <th>مهمان</th>
                        <th>اقامتگاه</th>
                        <th>نوع اتاق</th>
                        <th>ورود</th>
                        <th>خروج</th>
                        <th>مبلغ</th>
                        <th>وضعیت</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentBookings as $b)
                    <tr>
                        <td><code class="small">{{ $b->tracking_code }}</code></td>
                        <td class="small">{{ $b->bookerName() }}</td>
                        <td class="small">
                            @if($hostUser->hostCanAny('accommodations.edit', ['read', 'edit']))
                            <a wire:navigate href="{{ route('host.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">{{ Str::limit($b->accommodation->name, 22) }}</a>
                            @else
                            {{ Str::limit($b->accommodation->name, 22) }}
                            @endif
                        </td>
                        <td class="small text-muted">{{ $b->roomLinesSummary() }}</td>
                        <td class="small">@jalali($b->check_in)</td>
                        <td class="small">@jalali($b->check_out)</td>
                        <td class="small">{{ number_format($b->total_price) }} ت</td>
                        <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                        <td>
                            <div class="d-flex gap-1">
                                <a wire:navigate href="{{ route('host.bookings.show', $b) }}" class="btn btn-sm btn-outline-primary" style="padding:.15rem .45rem;font-size:.75rem"><i class="bi bi-eye"></i></a>
                                @if($b->status === 'pending' && $b->canEditBookingDetails() && $hostUser->hostCan('dashboard', 'edit'))
                                <button wire:click="confirm({{ $b->id }})" class="btn btn-sm btn-outline-success" style="padding:.15rem .45rem;font-size:.75rem"><i class="bi bi-check-lg"></i></button>
                                <button wire:click="cancel({{ $b->id }})" data-swal-confirm="لغو شود؟" class="btn btn-sm btn-outline-danger" style="padding:.15rem .45rem;font-size:.75rem"><i class="bi bi-x-lg"></i></button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">رزروی موجود نیست</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

</div>

@php
    $stTotalCount = ($statusBreakdown ?? collect())->sum('count');
    $hostStatusCounts = ($statusBreakdown ?? collect())->map(fn ($s) => (int) $s->count)->values()->toArray();
    $hostStatusLabels = ($statusBreakdown ?? collect())->map(fn ($s) => ['confirmed'=>'تأیید‌شده','pending'=>'در انتظار','cancelled'=>'لغو‌شده'][$s->status] ?? $s->status)->values()->toArray();
    $hostStatusColors = ($statusBreakdown ?? collect())->map(fn ($s) => ['confirmed'=>'#12b76a','pending'=>'#f79009','cancelled'=>'#f04438'][$s->status] ?? '#98a2b3')->values()->toArray();
    $hostChartPayload = [
        'daily' => $dailyRevenue ?? [],
        'statusCounts' => $hostStatusCounts,
        'statusLabels' => $hostStatusLabels,
        'statusColors' => $hostStatusColors,
        'statusTotal' => (int) $stTotalCount,
        'sparks' => $sparklineData ?? [],
        'hasSparks' => $hostUser->hasHostPanelAccess('accommodations') && $hostUser->hasHostPanelAccess('bookings'),
    ];
@endphp

<script type="application/json" id="host-dashboard-chart-payload" wire:ignore wire:key="host-chart-payload-{{ $filterKey }}">{!! json_encode($hostChartPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

@push('scripts')
@vite(['resources/js/rsb-layout-sort.js', 'resources/js/rsb-datepicker.js', 'resources/js/occupancy-calendar.js'])
<script>
(function () {
    function normalizeChartPayload(raw) {
        const base = {
            daily: [],
            statusCounts: [],
            statusLabels: [],
            statusColors: [],
            statusTotal: 0,
            sparks: {},
            hasSparks: false,
        };

        if (!raw || typeof raw !== 'object') {
            return base;
        }

        return {
            ...base,
            ...raw,
            daily: Array.isArray(raw.daily) ? raw.daily : [],
            statusCounts: Array.isArray(raw.statusCounts) ? raw.statusCounts : [],
            statusLabels: Array.isArray(raw.statusLabels) ? raw.statusLabels : [],
            statusColors: Array.isArray(raw.statusColors) ? raw.statusColors : [],
            statusTotal: Number(raw.statusTotal) || 0,
            sparks: raw.sparks && typeof raw.sparks === 'object' ? raw.sparks : {},
            hasSparks: !!raw.hasSparks,
        };
    }

    function readChartPayload() {
        const el = document.getElementById('host-dashboard-chart-payload');
        if (!el) return null;
        try {
            return normalizeChartPayload(JSON.parse(el.textContent || '{}'));
        } catch (e) {
            return null;
        }
    }

    let CHART_DATA = normalizeChartPayload(readChartPayload());

    const persianNum = v => new Intl.NumberFormat('fa-IR').format(v);
    const NS = window.HostDashboardCharts = window.HostDashboardCharts || { ready: false, charts: [] };

    function loadApex(cb) {
        if (window.ApexCharts) return cb();
        let tag = document.getElementById('apexcharts-sdk');
        if (tag) {
            tag.addEventListener('load', cb, { once: true });
            return;
        }
        tag = document.createElement('script');
        tag.id = 'apexcharts-sdk';
        tag.src = @json(asset('vendor/apexcharts/apexcharts.min.js'));
        tag.onload = cb;
        document.head.appendChild(tag);
    }

    function chartTargets() {
        return document.querySelectorAll('#host-chart-daily, #host-chart-status, [id^="host-spark-"]');
    }

    function destroyCharts() {
        NS.charts.forEach(ch => { try { ch.destroy(); } catch (e) {} });
        NS.charts = [];
        chartTargets().forEach(el => { el.innerHTML = ''; el._chart = null; });
        NS.ready = false;
    }

    function canRender(el) {
        return el && el.isConnected && el.offsetWidth > 0;
    }

    function renderCharts() {
        if (NS.ready || typeof ApexCharts === 'undefined') return;
        if (!document.getElementById('host-chart-daily')) return;

        const dailyEl = document.querySelector('#host-chart-daily');
        if (dailyEl && canRender(dailyEl) && (CHART_DATA.daily || []).length > 0) {
            const chart = new ApexCharts(dailyEl, {
                series: [
                    { name: 'درآمد (تومان)', type: 'area', data: CHART_DATA.daily.map(r => Number(r.total) || 0) },
                    { name: 'تعداد رزرو', type: 'line', data: CHART_DATA.daily.map(r => Number(r.count) || 0) }
                ],
                chart: { height: 300, toolbar: { show: false }, fontFamily: 'Vazirmatn, sans-serif', animations: { enabled: true } },
                colors: ['#465fff', '#12b76a'],
                fill: { type: ['gradient','solid'], gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
                stroke: { width: [2,2], curve: 'smooth' },
                dataLabels: { enabled: false },
                xaxis: { categories: CHART_DATA.daily.map(r => r.day), labels: { rotate: -30, style: { fontSize: '10px', colors: '#667085' } }, tickAmount: 10, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: [
                    { labels: { formatter: v => persianNum(Math.round(v || 0)), style: { colors: '#667085' } } },
                    { opposite: true, labels: { formatter: v => persianNum(v || 0), style: { colors: '#667085' } } }
                ],
                tooltip: { shared: true, intersect: false, y: [{ formatter: v => persianNum(v || 0) + ' ت' }, { formatter: v => persianNum(v || 0) + ' رزرو' }] },
                grid: { borderColor: '#f2f4f7', strokeDashArray: 4 },
                legend: { show: false }
            });
            chart.render();
            dailyEl._chart = chart;
            NS.charts.push(chart);
        }

        const statusEl = document.querySelector('#host-chart-status');
        const hasStatus = CHART_DATA.statusTotal > 0 && (CHART_DATA.statusCounts || []).length > 0;
        if (statusEl && canRender(statusEl)) {
            if (hasStatus) {
                const chart = new ApexCharts(statusEl, {
                    series: CHART_DATA.statusCounts.map(v => Number(v) || 0),
                    labels: CHART_DATA.statusLabels,
                    colors: CHART_DATA.statusColors,
                    chart: { type: 'donut', height: 280, fontFamily: 'Vazirmatn, sans-serif' },
                    dataLabels: { enabled: false },
                    stroke: { width: 0 },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '62%',
                                labels: {
                                    show: true,
                                    name: { fontSize: '13px', color: '#667085' },
                                    value: { fontSize: '18px', fontWeight: 700, color: '#101828', formatter: v => persianNum(Math.round(v || 0)) },
                                    total: { show: true, label: 'کل رزرو', color: '#667085', formatter: () => persianNum(CHART_DATA.statusTotal) }
                                }
                            }
                        }
                    },
                    legend: { show: false },
                    tooltip: { y: { formatter: v => persianNum(v || 0) + ' رزرو' } }
                });
                chart.render();
                statusEl._chart = chart;
                NS.charts.push(chart);
            } else {
                statusEl.innerHTML = '<div class="text-center text-muted py-5 small">هنوز رزروی ثبت نشده است</div>';
            }
        }

        if (CHART_DATA.hasSparks) {
            Object.keys(CHART_DATA.sparks).forEach(id => {
                const el = document.querySelector('#host-spark-' + id);
                if (!el || !canRender(el)) return;
                const data = (CHART_DATA.sparks[id] || []).map(v => Number(v) || 0);
                const chart = new ApexCharts(el, {
                    series: [{ data: data.length ? data : [0,0,0,0,0,0,0] }],
                    chart: { type: 'bar', height: 56, width: '100%', sparkline: { enabled: true }, fontFamily: 'Vazirmatn, sans-serif' },
                    plotOptions: { bar: { columnWidth: '75%', borderRadius: 3 } },
                    colors: ['#465fff'],
                    tooltip: { enabled: false }
                });
                chart.render();
                el._chart = chart;
                NS.charts.push(chart);
            });
        }

        NS.ready = true;
    }

    function boot() {
        CHART_DATA = normalizeChartPayload(readChartPayload() || CHART_DATA);
        loadApex(() => requestAnimationFrame(renderCharts));
    }

    if (!window._hostDashboardChartBoot) {
        window._hostDashboardChartBoot = true;
        document.addEventListener('livewire:navigating', destroyCharts);
        document.addEventListener('livewire:navigated', boot);
        document.addEventListener('dashboard-accommodation-filter-changed', () => {
            destroyCharts();
            boot();
        });
        if (window.Livewire) {
            Livewire.on('dashboard-accommodation-filter-changed', () => {
                destroyCharts();
                boot();
            });
        }
        boot();
    }
})();
</script>
@endpush
