<div>

@php
    // ── Revenue trend (last 6 months) for the area chart ──────────────
    $revLabels = [];
    $revValues = [];
    foreach ($monthlyRevenue as $row) {
        $revLabels[] = $row->month;
        $revValues[] = (float) $row->total;
    }
    $thisMonthRev = end($revValues) ?: 0;
    $prevMonthRev = count($revValues) > 1 ? $revValues[count($revValues) - 2] : 0;
    $revGrowth    = $prevMonthRev > 0 ? round((($thisMonthRev - $prevMonthRev) / $prevMonthRev) * 100, 1) : null;
    $confirmRate  = $stats['bookings'] > 0 ? round(($stats['confirmed'] / $stats['bookings']) * 100) : 0;

    $metrics = [
        ['label'=>'کل کاربران','value'=>number_format($stats['users']),'icon'=>'people','href'=>route('admin.users.index'),
         'trend'=>$stats['hosts'],'trendLabel'=>$stats['hosts'].' میزبان','up'=>true],
        ['label'=>'اقامتگاه‌ها','value'=>number_format($stats['accommodations']),'icon'=>'building','href'=>route('admin.accommodations.index'),
         'trend'=>$stats['active_acc'],'trendLabel'=>$stats['active_acc'].' فعال','up'=>true],
        ['label'=>'کل رزروها','value'=>number_format($stats['bookings']),'icon'=>'calendar-check','href'=>route('admin.bookings.index'),
         'trend'=>$confirmRate,'trendLabel'=>$confirmRate.'٪ تأیید','up'=>$confirmRate>=50],
        ['label'=>'درآمد کل (تومان)','value'=>number_format($stats['revenue']),'icon'=>'cash-stack','href'=>route('admin.bookings.index',['status'=>'confirmed']),
         'trend'=>$revGrowth,'trendLabel'=>($revGrowth!==null?abs($revGrowth).'٪ ماهانه':'—'),'up'=>($revGrowth??0)>=0],
        ['label'=>'کیف پول کارمزد','value'=>number_format($stats['commission_wallet']),'icon'=>'wallet2','href'=>route('admin.commission-wallet'),
         'trend'=>null,'trendLabel'=>config('platform_commission.percentage').'٪ تا سقف '.number_format(config('platform_commission.cap')),'up'=>true],
    ];
@endphp

{{-- ── Page header ─────────────────────────────────────────────────── --}}
<div class="ta-page-head">
    <div>
        <h1>داشبورد فروش</h1>
        <div class="text-muted small mt-1">نمای کلی عملکرد سامانه رزرو</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="btn btn-light"><i class="bi bi-calendar3 me-2"></i>{{ \Morilog\Jalali\Jalalian::now()->format('Y/m/d') }}</span>
        <a href="{{ route('admin.bookings.index') }}" wire:navigate class="btn btn-light"><i class="bi bi-funnel me-2"></i>فیلتر</a>
        <a href="{{ route('admin.bookings.export') }}" class="btn btn-primary"><i class="bi bi-download me-2"></i>خروجی اکسل</a>
    </div>
</div>

{{-- ── Metric cards ────────────────────────────────────────────────── --}}
<div class="row g-4 mb-4">
    @foreach($metrics as $m)
    <div class="col-6 col-xl-3">
        <a href="{{ $m['href'] }}" wire:navigate class="text-decoration-none">
            <div class="ta-metric">
                <div class="d-flex align-items-start justify-content-between">
                    <div class="ta-metric__icon"><i class="bi bi-{{ $m['icon'] }}"></i></div>
                    @if($m['trend'] !== null)
                    <span class="ta-trend {{ $m['up'] ? 'up' : 'down' }}">
                        <i class="bi bi-arrow-{{ $m['up'] ? 'up' : 'down' }}"></i>{{ $m['trendLabel'] }}
                    </span>
                    @endif
                </div>
                <div class="ta-metric__label">{{ $m['label'] }}</div>
                <div class="ta-metric__value">{{ $m['value'] }}</div>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- ── Revenue statistics area chart ───────────────────────────────── --}}
@php
    $faDigits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];
    $geoData  = $geoProvince->mapWithKeys(fn($r) => [$r->province => (int) $r->bookings]);
    $geoMax   = (int) ($geoProvince->max('bookings') ?: 0);
    $cityMax  = (int) ($topCities->max('bookings') ?: 1);
    $geoTotal = (int) $geoProvince->sum('bookings');
    $cityAccommodations = collect($accommodationsSales)
        ->filter(fn($a) => $a->city)
        ->groupBy(fn($a) => $a->city->name)
        ->map(fn($items) => $items->map(fn($a) => [
            'name'      => $a->name,
            'confirmed' => (int) $a->confirmed_count,
            'bookings'  => (int) $a->total_bookings_count,
            'revenue'   => (float) ($a->total_revenue ?? 0),
        ])->values());
    $provinceAccommodations = collect($accommodationsSales)
        ->filter(fn($a) => $a->city && $a->city->province)
        ->groupBy(fn($a) => $a->city->province->name)
        ->map(fn($items) => $items->map(fn($a) => [
            'name'      => $a->name,
            'city'      => $a->city->name,
            'confirmed' => (int) $a->confirmed_count,
            'bookings'  => (int) $a->total_bookings_count,
            'revenue'   => (float) ($a->total_revenue ?? 0),
        ])->values());
@endphp
<div class="ta-card mb-4">
    <div class="ta-card__head">
        <div>
            <h2 class="ta-card__title">پراکندگی رزروها در ایران</h2>
            <div class="ta-card__sub">تعداد رزروهای تأییدشده بر اساس استان و شهر</div>
        </div>
        <span class="ta-legend"><span class="dot" style="background:var(--ta-brand-500)"></span>تعداد رزرو</span>
    </div>
    <div class="ta-card__body">
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-lg-7">
                <div id="iranMap" wire:ignore style="height:430px;border-radius:12px;overflow:hidden;background:#f9fafb;border:1px solid #e4e7ec"></div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="d-flex align-items-baseline justify-content-between mb-3">
                    <span class="fw-semibold" style="font-size:.9rem;color:#101828">شهرهای پرتقاضا</span>
                    <span class="text-muted" style="font-size:.75rem">{{ strtr(number_format($geoTotal), $faDigits) }} رزرو تأییدشده</span>
                </div>
                <div class="d-flex flex-column gap-2" id="cityList">
                    @forelse($topCities as $c)
                        @php $pct = $cityMax > 0 ? round(($c->bookings / $cityMax) * 100) : 0; @endphp
                        <button type="button" class="city-row btn p-2 text-end w-100 border-0"
                                data-city="{{ $c->city }}" data-province="{{ $c->province }}"
                                style="background:transparent;border-radius:10px;transition:background .15s">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-truncate" style="font-size:.85rem;color:#101828;max-width:60%">{{ $c->city }}
                                    <span class="text-muted fw-normal" style="font-size:.72rem">({{ $c->province }})</span>
                                </span>
                                <span class="text-muted" style="font-size:.75rem">{{ strtr(number_format($c->bookings), $faDigits) }} رزرو</span>
                            </div>
                            <div class="progress" style="height:8px;background:#f2f4f7;border-radius:99px">
                                <div class="progress-bar" role="progressbar" style="width:{{ $pct }}%;background:var(--ta-brand-500);border-radius:99px" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </button>
                    @empty
                        <div class="text-muted text-center py-5">هنوز رزرو تأییدشده‌ای ثبت نشده است</div>
                    @endforelse
                </div>
                <div id="cityDetail" class="mt-3 pt-3" style="display:none;border-top:1px solid #e4e7ec">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="fw-bold" style="font-size:.9rem;color:#101828"><i class="bi bi-geo-alt-fill text-primary me-1"></i><span id="cityDetailTitle"></span></span>
                        <button type="button" id="cityDetailClose" class="btn btn-sm btn-light" style="font-size:.72rem">بستن</button>
                    </div>
                    <div id="cityDetailBody" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
        </div>
    </div>
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
                                        {{ $b->bookerName() }}
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
                                        <button wire:click="updateBookingStatus({{ $b->id }}, 'cancelled')" data-swal-confirm="لغو شود؟" class="btn btn-xs btn-outline-danger" style="padding:.2rem .5rem;font-size:.75rem;" title="لغو"><i class="bi bi-x-lg"></i></button>
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

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('vendor/apexcharts/apexcharts.min.js') }}" id="apexcharts-sdk"></script>
<script>
(function () {
    const VENDOR_LEAFLET = @json(asset('vendor/leaflet/leaflet.js'));
    const GEOJSON_URL = @json(asset('vendor/iran-map/provinces.min.geojson'));
    let _iranMapInstance = null;
    let _vendorLeafletLoading = false;

    // Iran bookings choropleth (province heatmap)
    const geoCounts = @json($geoData);
    const cityAccom = @json($cityAccommodations);
    const provinceAccom = @json($provinceAccommodations);
    const geoMax = {{ $geoMax }};
    const faNum = n => new Intl.NumberFormat('fa-IR').format(n);

    function heatColor(v) {
        if (!v) return '#eef1f6';
        const t = geoMax > 0 ? v / geoMax : 0;
        if (t > 0.8) return '#1d39c4';
        if (t > 0.6) return '#465fff';
        if (t > 0.4) return '#7592ff';
        if (t > 0.2) return '#a4b6ff';
        return '#cdd8ff';
    }

    function mapErrorHtml(retryFn) {
        const wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:#98a2b3;font-size:.85rem';
        wrap.innerHTML = '<div>خطا در بارگذاری نقشه</div>';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'تلاش دوباره';
        btn.style.cssText = 'padding:6px 20px;border:1px solid #d0d5dd;border-radius:8px;background:#fff;color:#344054;font-size:.82rem;font-family:Vazirmatn,sans-serif;cursor:pointer;transition:background .15s';
        btn.onmouseover = () => btn.style.background = '#f9fafb';
        btn.onmouseout  = () => btn.style.background = '#fff';
        btn.onclick = retryFn;
        wrap.appendChild(btn);
        return wrap;
    }

    function destroyIranMap() {
        if (_iranMapInstance) {
            try { _iranMapInstance.remove(); } catch (e) {}
            _iranMapInstance = null;
        }
        const el = document.getElementById('iranMap');
        if (el) {
            el._leafletMap = null;
            delete el.dataset.iranMapReady;
        }
    }

    function ensureVendorLeaflet(callback) {
        if (!document.getElementById('iranMap')) return;

        const vendorSdk = document.getElementById('vendor-leaflet-sdk');
        if (vendorSdk?.dataset.ready === '1' && window.L) {
            callback();
            return;
        }

        if (window.L && document.getElementById('neshan-sdk-accommodation')?.dataset.ready === '1') {
            window.__neshanLeafletBackup = window.L;
            delete window.L;
        } else if (window.L && !vendorSdk) {
            const marker = document.createElement('script');
            marker.id = 'vendor-leaflet-sdk';
            marker.dataset.ready = '1';
            document.body.appendChild(marker);
            callback();
            return;
        }

        if (_vendorLeafletLoading) return;
        _vendorLeafletLoading = true;

        if (vendorSdk) vendorSdk.remove();

        const script = document.createElement('script');
        script.id = 'vendor-leaflet-sdk';
        script.src = VENDOR_LEAFLET;
        script.onload = function () {
            script.dataset.ready = '1';
            _vendorLeafletLoading = false;
            callback();
        };
        script.onerror = function () { _vendorLeafletLoading = false; };
        document.body.appendChild(script);
    }

    function initIranMapCore() {
        const mapEl = document.getElementById('iranMap');
        if (!mapEl || !window.L || !mapEl.isConnected || !mapEl.offsetWidth) return;
        if (mapEl.dataset.iranMapReady === '1' && mapEl._leafletMap) {
            _iranMapInstance = mapEl._leafletMap;
            _iranMapInstance.invalidateSize();
            return;
        }

        destroyIranMap();

        const map = L.map(mapEl, { zoomControl: true, attributionControl: false, scrollWheelZoom: false, zoomSnap: 0.1, zoomDelta: 0.5 });
        mapEl._leafletMap = map;
        _iranMapInstance = map;
        const provinceLayers = {};
        let geoLayer = null, homeBounds = null, selectedLayer = null;

        const CloseControl = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                const btn = L.DomUtil.create('button', 'ta-map-reset');
                btn.type = 'button';
                btn.innerHTML = '✕';
                btn.title = 'بازگشت به نمای کلی';
                btn.style.cssText = 'display:none;width:30px;height:30px;border:none;border-radius:8px;background:#fff;box-shadow:0 1px 4px rgba(16,24,40,.18);color:#475467;font-size:15px;font-weight:700;cursor:pointer;line-height:30px;text-align:center;padding:0';
                L.DomEvent.disableClickPropagation(btn);
                L.DomEvent.on(btn, 'click', function (e) { L.DomEvent.preventDefault(e); resetView(); });
                this._btn = btn;
                return btn;
            }
        });
        const closeControl = new CloseControl();
        map.addControl(closeControl);
        function showCloseControl() { if (closeControl._btn) closeControl._btn.style.display = 'block'; }
        function hideCloseControl() { if (closeControl._btn) closeControl._btn.style.display = 'none'; }
        function resetView() {
            const detail = document.getElementById('cityDetail');
            if (detail) detail.style.display = 'none';
            document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
            resetSelection();
            hideCloseControl();
            if (homeBounds) map.flyToBounds(homeBounds, { padding: [14, 14], duration: 0.8 });
        }

        const baseStyle = f => ({
            fillColor: heatColor(geoCounts[f.properties['name:fa']] || 0),
            weight: 1, color: '#ffffff', fillOpacity: 0.9
        });

        function resetSelection() {
            if (geoLayer) geoLayer.eachLayer(l => geoLayer.resetStyle(l));
            selectedLayer = null;
            map.closePopup();
        }
        function highlight(lyr) {
            if (!geoLayer) return;
            geoLayer.eachLayer(l => {
                if (l === lyr) {
                    l.setStyle({ fillColor: '#465fff', fillOpacity: 0.95, weight: 2.5, color: '#ffffff' });
                    l.bringToFront();
                } else {
                    l.setStyle({ fillOpacity: 0.18, weight: 1, color: '#ffffff' });
                }
            });
            selectedLayer = lyr;
        }

        function renderDetail(title, list) {
            const detail = document.getElementById('cityDetail');
            const titleEl = document.getElementById('cityDetailTitle');
            const body   = document.getElementById('cityDetailBody');
            if (!detail) return;
            titleEl.textContent = title;
            if (!list.length) {
                body.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.8rem">اقامتگاهی برای این محدوده ثبت نشده است</div>';
            } else {
                body.innerHTML = list.map(a => `
                    <div class="d-flex align-items-center justify-content-between p-2" style="background:#f9fafb;border:1px solid #f2f4f7;border-radius:10px">
                        <div class="text-truncate" style="max-width:55%">
                            <div class="fw-semibold text-truncate" style="font-size:.82rem;color:#101828">${a.name}</div>
                            <div class="text-muted" style="font-size:.7rem">${a.city ? a.city + ' · ' : ''}${faNum(a.confirmed)} تأییدشده از ${faNum(a.bookings)} رزرو</div>
                        </div>
                        <div class="text-start">
                            <div class="fw-bold" style="font-size:.8rem;color:#465fff">${faNum(a.revenue)}</div>
                            <div class="text-muted" style="font-size:.68rem">تومان</div>
                        </div>
                    </div>`).join('');
            }
            detail.style.display = 'block';
        }

        function showCityDetail(city, province) {
            renderDetail(city + ' (' + province + ')', cityAccom[city] || []);
        }

        function showProvinceDetail(province) {
            renderDetail('استان ' + province, provinceAccom[province] || []);
        }

        function focusProvince(city, province) {
            const lyr = provinceLayers[province];
            if (lyr) {
                highlight(lyr);
                const v = geoCounts[province] || 0;
                map.flyToBounds(lyr.getBounds(), { padding: [50, 50], maxZoom: 6, duration: 1.0 });
                L.popup({ closeButton: false, autoClose: false, closeOnClick: false, className: 'ta-city-pop' })
                    .setLatLng(lyr.getBounds().getCenter())
                    .setContent(`<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:center;line-height:1.6"><b style="font-size:13px;color:#101828">${city}</b><br><span style="color:#465fff">استان ${province} · ${faNum(v)} رزرو</span></div>`)
                    .openOn(map);
            }
            showCityDetail(city, province);
            showCloseControl();
        }

        function focusProvinceByName(province) {
            const lyr = provinceLayers[province];
            if (lyr) {
                highlight(lyr);
                const v = geoCounts[province] || 0;
                map.flyToBounds(lyr.getBounds(), { padding: [50, 50], maxZoom: 6, duration: 1.0 });
                L.popup({ closeButton: false, autoClose: false, closeOnClick: false, className: 'ta-city-pop' })
                    .setLatLng(lyr.getBounds().getCenter())
                    .setContent(`<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:center;line-height:1.6"><b style="font-size:13px;color:#101828">استان ${province}</b><br><span style="color:#465fff">${faNum(v)} رزرو</span></div>`)
                    .openOn(map);
            }
            document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
            showProvinceDetail(province);
            showCloseControl();
        }

        fetch(GEOJSON_URL)
            .then(r => r.json())
            .then(geo => {
                geoLayer = L.geoJSON(geo, {
                    style: baseStyle,
                    onEachFeature: (f, lyr) => {
                        const name = f.properties['name:fa'];
                        provinceLayers[name] = lyr;
                        const v = geoCounts[name] || 0;
                        lyr.bindTooltip(
                            `<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:right"><b>${name}</b><br>${faNum(v)} رزرو</div>`,
                            { sticky: true, direction: 'top' }
                        );
                        lyr.on({
                            mouseover: e => { if (e.target !== selectedLayer) e.target.setStyle({ weight: 2, color: '#465fff', fillOpacity: 1 }); },
                            mouseout:  e => { if (e.target !== selectedLayer) geoLayer.resetStyle(e.target); },
                            click:     () => focusProvinceByName(name),
                        });
                    }
                }).addTo(map);
                homeBounds = geoLayer.getBounds();
                map.fitBounds(homeBounds, { padding: [14, 14] });
                mapEl.dataset.iranMapReady = '1';

                document.querySelectorAll('.city-row').forEach(row => {
                    row.addEventListener('click', () => {
                        document.querySelectorAll('.city-row').forEach(r => r.style.background = 'transparent');
                        row.style.background = '#eef2ff';
                        focusProvince(row.dataset.city, row.dataset.province);
                    });
                });
                const closeBtn = document.getElementById('cityDetailClose');
                if (closeBtn) closeBtn.addEventListener('click', resetView);
            })
            .catch(() => {
                mapEl.innerHTML = '';
                mapEl.appendChild(mapErrorHtml(() => initIranMap()));
            });
    }

    function initIranMap() {
        ensureVendorLeaflet(function () {
            requestAnimationFrame(function () { requestAnimationFrame(initIranMapCore); });
        });
    }

    document.addEventListener('livewire:navigating', function () {
        destroyIranMap();
        _vendorLeafletLoading = false;
    });
    document.addEventListener('livewire:navigated', initIranMap);
    document.addEventListener('DOMContentLoaded', initIranMap);

    // ── Per-accommodation sparklines ──────────────────────────────────
    const sparklines = @json(
        collect($accommodationsSales)->mapWithKeys(fn($a) => [
            $a->id => $sparklineData[$a->id] ?? array_fill(0, 7, 0)
        ])
    );
    let chartsRendered = false;
    function renderSparklines() {
        if (chartsRendered || !window.ApexCharts) return;
        chartsRendered = true;
        Object.entries(sparklines).forEach(([id, data]) => {
            const el = document.querySelector('#spark-' + id);
            if (!el) return;
            new ApexCharts(el, {
                series: [{ data }],
                chart: { type: 'bar', height: 60, sparkline: { enabled: true } },
                plotOptions: { bar: { columnWidth: '75%', borderRadius: 3 } },
                colors: ['#465fff'],
                tooltip: {
                    fixed: { enabled: false },
                    x: { show: false },
                    y: { formatter: v => new Intl.NumberFormat('fa-IR').format(v) + ' ت' },
                    marker: { show: false }
                }
            }).render();
        });
    }

    const collapseEl = document.getElementById('salesGridCollapse');
    const chevron    = document.getElementById('salesChevron');
    if (collapseEl) {
        collapseEl.addEventListener('show.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(0deg)';
            setTimeout(renderSparklines, 50);
        });
        collapseEl.addEventListener('hide.bs.collapse', () => {
            if (chevron) chevron.style.transform = 'rotate(180deg)';
        });
        if (collapseEl.classList.contains('show')) {
            document.addEventListener('DOMContentLoaded', renderSparklines);
            renderSparklines();
        }
    }
    document.getElementById('apexcharts-sdk')?.addEventListener('load', renderSparklines);
    document.addEventListener('livewire:navigated', renderSparklines);
})();
</script>
@endpush
