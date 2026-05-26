<div>

{{-- Stats Row --}}
<div class="row g-3 mb-4">
    @php
    $cards = [
        ['label'=>'کل کاربران',       'value'=>$stats['users'],          'icon'=>'people-fill',        'bg'=>'bg-primary',   'text'=>'text-primary',   'href'=> route('admin.users.index')],
        ['label'=>'میزبان‌ها',          'value'=>$stats['hosts'],          'icon'=>'house-heart-fill',   'bg'=>'bg-success',   'text'=>'text-success',   'href'=> route('admin.users.index', ['role'=>'host'])],
        ['label'=>'اقامتگاه‌ها',        'value'=>$stats['accommodations'], 'icon'=>'building-fill',      'bg'=>'bg-info',      'text'=>'text-info',      'href'=> route('admin.accommodations.index')],
        ['label'=>'کل رزروها',         'value'=>$stats['bookings'],       'icon'=>'calendar-check-fill','bg'=>'bg-warning',   'text'=>'text-warning',   'href'=> route('admin.bookings.index')],
        ['label'=>'تأیید شده',          'value'=>$stats['confirmed'],      'icon'=>'check-circle-fill',  'bg'=>'bg-success',   'text'=>'text-success',   'href'=> route('admin.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'در انتظار تأیید',    'value'=>$stats['pending'],        'icon'=>'clock-fill',         'bg'=>'bg-warning',   'text'=>'text-warning',   'href'=> route('admin.bookings.index', ['status'=>'pending'])],
        ['label'=>'درآمد کل (تومان)',   'value'=>number_format($stats['revenue']), 'icon'=>'currency-exchange','bg'=>'bg-danger','text'=>'text-danger',  'href'=> route('admin.bookings.index', ['status'=>'confirmed'])],
        ['label'=>'نظرات',             'value'=>$stats['reviews'],        'icon'=>'star-fill',          'bg'=>'bg-secondary', 'text'=>'text-secondary', 'href'=> route('admin.reviews.index')],
    ];
    @endphp
    @foreach($cards as $c)
    <div class="col-6 col-md-4 col-xl-3">
        <a href="{{ $c['href'] }}" class="text-decoration-none">
        <div class="card stat-card shadow-sm h-100 border-0" style="transition:.2s" onmouseenter="this.style.transform='translateY(-3px)'" onmouseleave="this.style.transform=''">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon {{ $c['bg'] }} bg-opacity-10">
                    <i class="bi bi-{{ $c['icon'] }} {{ $c['text'] }}"></i>
                </div>
                <div>
                    <div class="fw-bold fs-5 text-dark">{{ $c['value'] }}</div>
                    <div class="text-muted small">{{ $c['label'] }}</div>
                </div>
                <div class="me-auto"><i class="bi bi-arrow-left-short text-muted"></i></div>
            </div>
        </div>
        </a>
    </div>
    @endforeach
</div>

{{-- ═══════════════════  Accommodations Sales Grid (collapsible)  ═══════════════════ --}}
<div class="card border-0 shadow-sm mb-4" id="salesGridCard">
    <div class="card-header bg-white d-flex align-items-center justify-content-between py-2 px-3"
         role="button" data-bs-toggle="collapse" data-bs-target="#salesGridCollapse"
         aria-expanded="true" aria-controls="salesGridCollapse" style="cursor:pointer;user-select:none">
        <h5 class="mb-0 fw-bold fs-6">
            <i class="bi bi-bar-chart-line-fill me-2 text-primary"></i>نمودار فروش اقامتگاه‌ها
            <span class="badge bg-primary bg-opacity-10 text-primary ms-2" style="font-size:.7rem;font-weight:500">{{ $accommodationsSales->count() }} اقامتگاه</span>
        </h5>
        <div class="d-flex align-items-center gap-2">
            <a wire:navigate href="{{ route('admin.accommodations.index') }}" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem" onclick="event.stopPropagation()">مشاهده همه</a>
            <i class="bi bi-chevron-up text-muted" id="salesChevron" style="transition:transform .25s"></i>
        </div>
    </div>
    <div class="collapse show" id="salesGridCollapse">
        <div class="card-body pt-2 pb-3 px-3">
            <div class="row g-3">
                @forelse($accommodationsSales as $acc)
                @php
                    $todayVal    = $accTodayRevenue[$acc->id]     ?? 0;
                    $weekVal     = $accWeekRevenue[$acc->id]      ?? 0;
                    $monthVal    = $accMonthRevenue[$acc->id]     ?? 0;
                    $lastMonthVal= $accLastMonthRevenue[$acc->id] ?? 0;
                    $growth      = $lastMonthVal > 0 ? round((($monthVal - $lastMonthVal) / $lastMonthVal) * 100, 1) : null;
                    $convRate    = $acc->total_bookings_count > 0 ? round(($acc->confirmed_count / $acc->total_bookings_count) * 100) : 0;
                @endphp
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;overflow:hidden">
                        <div class="card-header bg-white border-bottom-0 pb-1 pt-3 px-3 d-flex align-items-start justify-content-between">
                            <div>
                                <div class="fw-bold text-dark" style="font-size:.95rem">{{ Str::limit($acc->name, 30) }}</div>
                                <div class="text-muted small">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $acc->city->name ?? '—' }}
                                    <span class="ms-2 badge {{ $acc->is_active ? 'bg-success' : 'bg-secondary' }} bg-opacity-75" style="font-size:.65rem">{{ $acc->is_active ? 'فعال' : 'غیرفعال' }}</span>
                                </div>
                            </div>
                            <a wire:navigate href="{{ route('admin.accommodations.report', $acc) }}" class="btn btn-sm btn-primary" title="گزارش کامل" style="font-size:.78rem">
                                <i class="bi bi-graph-up-arrow me-1"></i>جزئیات بیشتر
                            </a>
                        </div>
                        <div class="px-3" id="spark-{{ $acc->id }}" style="min-height:60px"></div>
                        <div class="card-body pt-1 pb-2 px-3">
                            <div class="row g-2 text-center mb-2">
                                <div class="col-4">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted" style="font-size:.65rem">امروز</div>
                                        <div class="fw-bold text-dark" style="font-size:.8rem">{{ number_format($todayVal) }}<small class="text-muted"> ت</small></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted" style="font-size:.65rem">این هفته</div>
                                        <div class="fw-bold text-dark" style="font-size:.8rem">{{ number_format($weekVal) }}<small class="text-muted"> ت</small></div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="bg-light rounded p-2">
                                        <div class="text-muted" style="font-size:.65rem">این ماه</div>
                                        <div class="fw-bold text-primary" style="font-size:.8rem">{{ number_format($monthVal) }}<small class="text-muted"> ت</small></div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-1" style="font-size:.78rem">
                                <span class="text-muted"><i class="bi bi-calendar-check text-success me-1"></i>{{ number_format($acc->confirmed_count) }} تأیید</span>
                                <span class="text-muted"><i class="bi bi-clock text-warning me-1"></i>{{ number_format($acc->pending_count) }} انتظار</span>
                                <span class="text-muted"><i class="bi bi-x-circle text-danger me-1"></i>{{ number_format($acc->cancelled_count) }} لغو</span>
                                <span class="text-muted"><i class="bi bi-percent text-info me-1"></i>{{ $convRate }}% نرخ تبدیل</span>
                                @if($growth !== null)
                                <span class="{{ $growth >= 0 ? 'text-success' : 'text-danger' }}">
                                    <i class="bi bi-{{ $growth >= 0 ? 'arrow-up' : 'arrow-down' }}-short"></i>{{ abs($growth) }}% نرخ رشد
                                </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 text-center text-muted py-5">
                    <i class="bi bi-building fs-1 opacity-25"></i><br>هیچ اقامتگاهی ثبت نشده است.
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Recent Bookings --}}
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                <h6 class="mb-0 fw-bold"><i class="bi bi-calendar-check me-2 text-primary"></i>آخرین رزروها</h6>
                <a wire:navigate href="{{ route('admin.bookings.index') }}" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>کد رزرو</th><th>کاربر</th><th>اقامتگاه</th><th>مبلغ</th><th>وضعیت</th><th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentBookings as $b)
                            <tr>
                                <td>
                                    <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="text-decoration-none">
                                        <code class="small">{{ $b->tracking_code }}</code>
                                    </a>
                                </td>
                                <td>
                                    <a wire:navigate href="{{ route('admin.users.show', $b->user) }}" class="text-decoration-none text-dark">
                                        {{ $b->user->name ?? $b->user->mobile }}
                                    </a>
                                </td>
                                <td>
                                    <a wire:navigate href="{{ route('admin.accommodations.edit', $b->accommodation) }}" class="text-decoration-none text-dark">
                                        {{ Str::limit($b->accommodation->name, 25) }}
                                    </a>
                                </td>
                                <td class="small">{{ number_format($b->total_price) }} ت</td>
                                <td><span class="badge bg-{{ $b->statusColor() }}">{{ $b->statusLabel() }}</span></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a wire:navigate href="{{ route('admin.bookings.show', $b) }}" class="btn btn-xs btn-outline-primary" style="padding:.2rem .5rem;font-size:.75rem;" title="جزئیات"><i class="bi bi-eye"></i></a>
                                        @if($b->status === 'pending')
                                        <button wire:click="updateBookingStatus({{ $b->id }}, 'confirmed')" class="btn btn-xs btn-outline-success" style="padding:.2rem .5rem;font-size:.75rem;" title="تأیید"><i class="bi bi-check-lg"></i></button>
                                        <button wire:click="updateBookingStatus({{ $b->id }}, 'cancelled')" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">رزروی وجود ندارد</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Top Accommodations --}}
    <div class="col-12 col-xl-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-trophy me-2 text-warning"></i>برترین اقامتگاه‌ها</h6>
            </div>
            <div class="list-group list-group-flush">
                @forelse($topAccommodations as $i => $acc)
                <div class="list-group-item d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark rounded-circle" style="width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:.75rem;">{{ $i+1 }}</span>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ Str::limit($acc->name,26) }}</div>
                        <div class="text-muted" style="font-size:.75rem">{{ $acc->city->name ?? '' }}</div>
                    </div>
                    <span class="badge bg-primary">{{ $acc->bookings_count }}</span>
                    <a wire:navigate href="{{ route('admin.bookings.index', ['search'=> $acc->name]) }}" class="btn btn-xs btn-outline-primary" style="padding:.15rem .4rem;font-size:.7rem;" title="رزروها"><i class="bi bi-calendar-check"></i></a>
                    <a wire:navigate href="{{ route('admin.accommodations.edit', $acc) }}" class="btn btn-xs btn-outline-warning" style="padding:.15rem .4rem;font-size:.7rem;" title="ویرایش"><i class="bi bi-pencil"></i></a>
                </div>
                @empty
                <div class="list-group-item text-muted text-center small py-3">داده‌ای موجود نیست</div>
                @endforelse
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold"><i class="bi bi-person-plus me-2 text-success"></i>کاربران جدید</h6>
            </div>
            <div class="list-group list-group-flush">
                @foreach($recentUsers as $u)
                <div class="list-group-item d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width:36px;height:36px;flex-shrink:0">
                        <i class="bi bi-person text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="small fw-semibold">{{ $u->name ?? 'بدون نام' }}</div>
                        <div class="text-muted" style="font-size:.72rem">{{ $u->mobile }}</div>
                    </div>
                    @if($u->hasRole('super_admin'))
                        <span class="badge bg-danger">ادمین</span>
                    @elseif($u->hasRole('host'))
                        <span class="badge bg-success">میزبان</span>
                    @else
                        <span class="badge bg-secondary">کاربر</span>
                    @endif
                    <a wire:navigate href="{{ route('admin.users.show', $u) }}" class="btn btn-xs btn-outline-primary ms-1" style="padding:.15rem .4rem;font-size:.7rem;" title="مشاهده"><i class="bi bi-eye"></i></a>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>


</div>

@push('scripts')
<script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}"></script>
<script>
(function () {
    const sparklines = @json(
        collect($accommodationsSales)->mapWithKeys(fn($a) => [
            $a->id => $sparklineData[$a->id] ?? array_fill(0, 7, 0)
        ])
    );
    let chartsRendered = false;

    function renderSparklines() {
        if (chartsRendered) return;
        chartsRendered = true;
        Object.entries(sparklines).forEach(([id, data]) => {
            const el = document.querySelector('#spark-' + id);
            if (!el) return;
            new ApexCharts(el, {
                series: [{ data }],
                chart: { type: 'bar', height: 60, sparkline: { enabled: true } },
                plotOptions: { bar: { columnWidth: '75%', borderRadius: 3 } },
                colors: ['#3b82f6'],
                tooltip: {
                    fixed: { enabled: false },
                    x: { show: false },
                    y: { formatter: v => new Intl.NumberFormat('fa-IR').format(v) + ' ت' },
                    marker: { show: false }
                }
            }).render();
        });
    }

    // Chevron rotation + render on open
    const collapseEl = document.getElementById('salesGridCollapse');
    const chevron    = document.getElementById('salesChevron');
    if (collapseEl) {
        collapseEl.addEventListener('show.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(0deg)';
            // slight delay so the collapse has started opening before charts measure width
            setTimeout(renderSparklines, 50);
        });
        collapseEl.addEventListener('hide.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        });
        // already open on load
        if (collapseEl.classList.contains('show')) {
            document.addEventListener('DOMContentLoaded', renderSparklines);
        }
    }
})();
</script>
@endpush
