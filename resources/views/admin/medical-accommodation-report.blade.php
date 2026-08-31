<div id="medicalReportRoot" data-report-id="{{ $componentId }}" wire:key="med-report-{{ $filterKey }}-{{ $period }}-{{ $jalaliYear }}-{{ $jalaliMonth }}">

@php
    $panel = $panel ?? 'admin';
    $faDigits = ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'];
    $fa = fn ($n) => strtr(\App\Support\PdfPersian::toPersianDigits(number_format((int) $n)), $faDigits);
    $geoCounts = $provinces->mapWithKeys(fn ($r) => [(string) $r->province => (int) $r->bookings]);
    $geoMax = (int) ($provinces->max('bookings') ?: 0);
    $cityMax = (int) ($cities->max('bookings') ?: 1);
    $geoTotal = (int) $provinces->sum('bookings');
    $topCities = $cities->take(8);
    $mapPayload = [
        'geoCounts' => $geoCounts->all(),
        'geoIds'    => $provinces->mapWithKeys(fn ($r) => [(string) $r->province => (int) $r->province_id])->all(),
        'geoMax'    => $geoMax,
    ];
    $compactToman = function (int $n) use ($faDigits): array {
        $format = function (float $v) use ($faDigits): string {
            $text = abs($v - round($v)) < 0.05
                ? (string) (int) round($v)
                : rtrim(rtrim(\App\Support\PdfPersian::toPersianDigits(number_format($v, 1, '.', '')), '0'), '.');

            return strtr($text, $faDigits);
        };

        if ($n >= 1_000_000_000) {
            return ['num' => $format($n / 1_000_000_000), 'unit' => 'میلیارد ریال'];
        }
        if ($n >= 1_000_000) {
            return ['num' => $format($n / 1_000_000), 'unit' => 'میلیون ریال'];
        }

        return ['num' => strtr(\App\Support\PdfPersian::toPersianDigits(number_format($n)), $faDigits), 'unit' => 'ریال'];
    };
    $debtDisplay = $compactToman((int) $kpis['debt']);
    $avgDebtDisplay = $compactToman((int) $kpis['avg_debt']);
    $avgNights = $kpis['avg_nights'] == (int) $kpis['avg_nights']
        ? $fa((int) $kpis['avg_nights'])
        : strtr((string) $kpis['avg_nights'], $faDigits);
@endphp

<div class="card shadow-sm mb-3">
    <div class="ta-list-chrome">
        <div class="btn-group" role="group" aria-label="بازه گزارش">
            <button type="button" wire:click="setPeriod('all')" class="btn btn-sm {{ $period === 'all' ? 'btn-primary' : 'btn-light' }}">همه</button>
            <button type="button" wire:click="setPeriod('month')" class="btn btn-sm {{ $period === 'month' ? 'btn-primary' : 'btn-light' }}">ماه</button>
            <button type="button" wire:click="setPeriod('year')" class="btn btn-sm {{ $period === 'year' ? 'btn-primary' : 'btn-light' }}">سال</button>
        </div>
        @if($period !== 'all')
        <div class="btn-group occ-cal__month-nav">
            <button type="button" wire:click="prevPeriod" class="btn btn-sm btn-light" title="قبلی" aria-label="قبلی">
                <i class="bi bi-chevron-right"></i>
            </button>
            <span class="btn btn-sm btn-light disabled fw-semibold occ-cal__month-label">{{ $period_label }}</span>
            <button type="button" wire:click="nextPeriod" class="btn btn-sm btn-light" title="بعدی" aria-label="بعدی">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        @unless($isCurrentPeriod)
        <button type="button" wire:click="goToCurrentPeriod" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calendar-event me-1"></i>{{ $period === 'year' ? 'سال جاری' : 'ماه جاری' }}
        </button>
        @endunless
        @endif
        @if($this->showDashboardAccommodationFilter())
            @include('components.dashboard.accommodation-filter', ['hint' => 'تغییرات پس از «اعمال فیلتر» روی این گزارش اعمال می‌شود'])
        @endif
    </div>
</div>

<div class="ta-card med-kpi mb-3">
    <div class="med-kpi__grid">
        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--primary" aria-hidden="true"><i class="bi bi-heart-pulse-fill"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">رزرو تأییدشده</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $fa($kpis['confirmed']) }}</strong>
                    <span class="med-kpi__unit">از {{ $fa($kpis['total']) }} رزرو</span>
                </div>
                <div class="med-kpi__chips">
                    <span class="med-kpi__chip med-kpi__chip--warn">{{ $fa($kpis['pending']) }} در انتظار</span>
                    <span class="med-kpi__chip med-kpi__chip--danger">{{ $fa($kpis['cancelled']) }} کنسل</span>
                </div>
            </div>
        </article>

        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--info" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">بدهی بیمه دی</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $debtDisplay['num'] }}</strong>
                    <span class="med-kpi__unit">{{ $debtDisplay['unit'] }}</span>
                </div>
                <div class="med-kpi__meta">میانگین {{ $avgDebtDisplay['num'] }} {{ $avgDebtDisplay['unit'] }}</div>
            </div>
        </article>

        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--warning" aria-hidden="true"><i class="bi bi-moon-stars-fill"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">شب اقامت</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $fa($kpis['nights']) }}</strong>
                    <span class="med-kpi__unit">شب</span>
                </div>
                <div class="med-kpi__meta">میانگین {{ $avgNights }} شب</div>
            </div>
        </article>

        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--success" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">مهمان و همراه</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $fa($kpis['guests']) }}</strong>
                    <span class="med-kpi__unit">مهمان</span>
                </div>
                <div class="med-kpi__meta">{{ $fa($kpis['companions']) }} همراه</div>
            </div>
        </article>

        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--secondary" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">پوشش جغرافیایی</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $fa($kpis['provinces']) }}</strong>
                    <span class="med-kpi__unit">استان</span>
                </div>
                <div class="med-kpi__meta">{{ $fa($kpis['cities']) }} شهر · {{ $fa($kpis['accommodations']) }} اقامتگاه</div>
            </div>
        </article>

        <article class="med-kpi__cell">
            <span class="med-kpi__icon med-kpi__icon--violet" aria-hidden="true"><i class="bi bi-file-earmark-text-fill"></i></span>
            <div class="med-kpi__copy">
                <div class="med-kpi__label">تعداد قرارداد</div>
                <div class="med-kpi__value-row">
                    <strong class="med-kpi__value">{{ $fa($kpis['contracts']) }}</strong>
                    <span class="med-kpi__unit">قرارداد فعال</span>
                </div>
                <div class="med-kpi__meta">فقط رزروهای تأییدشده همین بازه</div>
            </div>
        </article>

        @php
            $groupIcons = [
                \App\Support\MedicalAccommodationTariffs::KEY_NECK_INJURY => ['class' => 'med-kpi__icon--rose', 'icon' => 'bi-award-fill'],
                \App\Support\MedicalAccommodationTariffs::KEY_SPINAL_AMPUTEE => ['class' => 'med-kpi__icon--teal', 'icon' => 'bi-person-wheelchair'],
                \App\Support\MedicalAccommodationTariffs::KEY_OTHER_VETERAN => ['class' => 'med-kpi__icon--amber', 'icon' => 'bi-people-fill'],
            ];
        @endphp
        @foreach(($kpis['shared_groups'] ?? []) as $group)
            @php
                $visual = $groupIcons[$group['key']] ?? ['class' => 'med-kpi__icon--violet', 'icon' => 'bi-heart-pulse-fill'];
                $groupDebt = $compactToman((int) $group['debt']);
            @endphp
            <article class="med-kpi__cell">
                <span class="med-kpi__icon {{ $visual['class'] }}" aria-hidden="true"><i class="bi {{ $visual['icon'] }}"></i></span>
                <div class="med-kpi__copy">
                    <div class="med-kpi__label" title="{{ $group['label'] }}">{{ $group['label'] }}</div>
                    <div class="med-kpi__value-row">
                        <strong class="med-kpi__value">{{ $fa($group['bookings']) }}</strong>
                        <span class="med-kpi__unit">رزرو</span>
                    </div>
                    <div class="med-kpi__meta">{{ $fa($group['nights']) }} شب · {{ $groupDebt['num'] }} {{ $groupDebt['unit'] }}</div>
                    <div class="med-kpi__meta">{{ $fa($group['guests']) }} مهمان · {{ $fa($group['companions']) }} همراه</div>
                </div>
            </article>
        @endforeach
    </div>
</div>

<script type="application/json" id="medical-report-map-payload" wire:ignore>{!! json_encode($mapPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<div class="ta-card mb-4 med-map-card">
    <div class="ta-card__head">
        <div>
            <h2 class="ta-card__title">پراکندگی رزروهای اسکان درمانی در ایران</h2>
            <div class="ta-card__sub">تعداد رزروهای تأییدشده اسکان درمانی بر اساس استان و شهر</div>
        </div>
        <span class="ta-legend"><span class="dot" style="background:var(--ta-brand-500)"></span>{{ $fa($geoTotal) }} رزرو تأییدشده</span>
    </div>
    <div class="ta-card__body">
        <div class="row g-3 align-items-stretch">
            <div class="col-12 col-lg-7">
                <div id="medicalIranMap" class="med-iran-map" wire:ignore></div>
            </div>
            <div class="col-12 col-lg-5">
                <div class="d-flex align-items-baseline justify-content-between mb-3">
                    <span class="fw-semibold" style="font-size:.9rem;color:#101828">شهرهای پرتقاضا</span>
                    <span class="text-muted" style="font-size:.75rem">{{ $fa($geoTotal) }} رزرو تأییدشده</span>
                </div>
                <div class="d-flex flex-column gap-2" id="medicalCityList">
                    @forelse($topCities as $c)
                        @php $pct = $cityMax > 0 ? round(($c->bookings / $cityMax) * 100) : 0; @endphp
                        <button type="button"
                                class="city-row btn p-2 text-end w-100 border-0"
                                wire:click="openCity({{ (int) $c->city_id }})"
                                data-city-id="{{ (int) $c->city_id }}"
                                data-city="{{ $c->city }}"
                                data-province="{{ $c->province }}"
                                style="background:transparent;border-radius:10px;transition:background .15s">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold text-truncate" style="font-size:.85rem;color:#101828;max-width:62%">{{ $c->city }}
                                    <span class="text-muted fw-normal" style="font-size:.72rem">({{ $c->province }})</span>
                                </span>
                                <span class="text-muted" style="font-size:.75rem">{{ $fa($c->bookings) }} رزرو</span>
                            </div>
                            <div class="progress" style="height:8px;background:#f2f4f7;border-radius:99px">
                                <div class="progress-bar" role="progressbar" style="width:{{ $pct }}%;background:var(--ta-brand-500);border-radius:99px" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </button>
                    @empty
                        <div class="text-muted text-center py-5">در این بازه رزرو تأییدشده اسکان درمانی ثبت نشده است</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div class="ta-card mb-4">
    <div class="ta-card__head">
        <div>
            <h2 class="ta-card__title">گزارش استان‌ها</h2>
            <div class="ta-card__sub">جزئیات رزرو تأییدشده اسکان درمانی در هر استان — کلیک روی ردیف، رزروهای همان استان را نشان می‌دهد</div>
        </div>
    </div>
    <div class="ta-card__body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>استان</th>
                        <th>تأییدشده</th>
                        <th>در انتظار</th>
                        <th>کنسل</th>
                        <th>شب</th>
                        <th>مهمان</th>
                        <th>همراه</th>
                        <th>شهر</th>
                        <th>اقامتگاه</th>
                        <th>بدهی کارفرما</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($provinces as $row)
                    <tr role="button" wire:click="openProvince({{ (int) $row->province_id }})" style="cursor:pointer">
                        <td class="fw-semibold">{{ $row->province }}</td>
                        <td>{{ $fa($row->bookings) }}</td>
                        <td>{{ $fa($row->pending) }}</td>
                        <td>{{ $fa($row->cancelled) }}</td>
                        <td>{{ $fa($row->nights) }}</td>
                        <td>{{ $fa($row->guests) }}</td>
                        <td>{{ $fa($row->companions) }}</td>
                        <td>{{ $fa($row->cities) }}</td>
                        <td>{{ $fa($row->accommodations) }}</td>
                        <td class="fw-semibold">{{ $fa($row->debt) }} ریال</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">داده‌ای برای این فیلتر نیست</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="ta-card mb-4">
    <div class="ta-card__head">
        <div>
            <h2 class="ta-card__title">گزارش شهرها</h2>
            <div class="ta-card__sub">رزرو تأییدشده اسکان درمانی به تفکیک شهر — کلیک، مودال رزروهای همان شهر را باز می‌کند</div>
        </div>
    </div>
    <div class="ta-card__body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>شهر</th>
                        <th>استان</th>
                        <th>رزرو تأییدشده</th>
                        <th>شب</th>
                        <th>بدهی کارفرما</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cities as $c)
                    <tr role="button" wire:click="openCity({{ (int) $c->city_id }})" style="cursor:pointer">
                        <td class="fw-semibold">{{ $c->city }}</td>
                        <td>{{ $c->province }}</td>
                        <td>{{ $fa($c->bookings) }}</td>
                        <td>{{ $fa($c->nights) }}</td>
                        <td class="fw-semibold">{{ $fa($c->debt) }} ریال</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">داده‌ای برای این فیلتر نیست</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([
        ['title' => 'کارفرما', 'rows' => $employers, 'icon' => 'bi-building'],
        ['title' => 'اقامتگاه', 'rows' => $accommodations, 'icon' => 'bi-houses'],
    ] as $block)
    <div class="col-12 col-lg-6">
        <div class="ta-card h-100">
            <div class="ta-card__head">
                <h2 class="ta-card__title mb-0"><i class="bi {{ $block['icon'] }} me-1"></i>{{ $block['title'] }}</h2>
            </div>
            <div class="ta-card__body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ $block['title'] }}</th>
                                <th>رزرو</th>
                                <th>شب</th>
                                <th>بدهی</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($block['rows'] as $row)
                            <tr>
                                <td class="small">{{ $row->label }}</td>
                                <td>{{ $fa($row->bookings) }}</td>
                                <td>{{ $fa($row->nights) }}</td>
                                <td>{{ $fa($row->debt) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">—</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($showCityModal)
<div class="modal-backdrop fade show" style="z-index:1080;" wire:click="closeModal"></div>
<div class="modal fade show" style="display:block;z-index:1085;" tabindex="-1" role="dialog" wire:keydown.escape="closeModal">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <i class="bi bi-heart-pulse text-info"></i>
                    رزروهای اسکان درمانی — {{ $modalTitle }}
                </h5>
                <button type="button" class="btn-close" wire:click="closeModal" aria-label="بستن"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>کد</th>
                                <th>مهمان</th>
                                @if($selectedProvinceId)
                                <th>شهر</th>
                                @endif
                                <th>اقامتگاه</th>
                                <th>ورود / خروج</th>
                                <th>شب</th>
                                <th>همراه</th>
                                <th>تعرفه</th>
                                <th>قرارداد</th>
                                <th>کارفرما</th>
                                <th>بدهی</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($modalBookings as $booking)
                            <tr>
                                <td>
                                    <a wire:navigate href="{{ route($panel . '.bookings.show', $booking) }}" class="text-decoration-none">
                                        <code dir="ltr">{{ $booking->tracking_code }}</code>
                                    </a>
                                </td>
                                <td class="small">{{ $booking->guest_contact_name ?? $booking->user?->name ?? '—' }}</td>
                                @if($selectedProvinceId)
                                <td class="small">{{ $booking->accommodation?->city?->name ?? '—' }}</td>
                                @endif
                                <td class="small">{{ $booking->accommodation?->name ?? '—' }}</td>
                                <td class="small" dir="ltr">@jalali($booking->check_in) — @jalali($booking->check_out)</td>
                                <td>{{ $booking->nights }}</td>
                                <td>{{ $booking->medical_companion_count }}</td>
                                <td class="small">{{ $booking->medicalTariffLabel() ?: '—' }}</td>
                                <td class="small" dir="ltr">{{ $booking->medicalContractNumber() ?: '—' }}</td>
                                <td class="small">{{ $booking->employer?->name ?? 'بیمه دی' }}</td>
                                <td class="small fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->employerDebtAmount() ?: $booking->total_price)) }}</td>
                                <td><span class="badge bg-{{ $booking->statusColor() }}">{{ $booking->statusLabel() }}</span></td>
                            </tr>
                            @empty
                            <tr><td colspan="{{ $selectedProvinceId ? 12 : 11 }}" class="text-center text-muted py-4">رزروی برای این محدوده در بازه انتخابی نیست</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($modalBookings->count() >= 80)
                <div class="text-muted small px-3 py-2">نمایش حداکثر ۸۰ رزرو. برای فهرست کامل از فیلتر رزروها استفاده کنید.</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeModal">بستن</button>
            </div>
        </div>
    </div>
</div>
@endif

</div>

@push('styles')
<link rel="stylesheet" href="{{ vasset('vendor/leaflet/leaflet.css') }}">
<style>
    .occ-cal__month-label {
        min-width: 7.5rem;
        opacity: 1;
        color: inherit;
    }
    .med-kpi { overflow: hidden; }
    .med-kpi__grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .med-kpi__cell {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
        padding: 18px 20px;
        background: transparent;
    }
    .med-kpi__grid > .med-kpi__cell {
        border-inline-end: 1px solid var(--bs-border-color, #eaecf0);
        border-bottom: 1px solid var(--bs-border-color, #eaecf0);
    }
    .med-kpi__grid > .med-kpi__cell:nth-child(3n) { border-inline-end: none; }
    .med-kpi__grid > .med-kpi__cell:nth-last-child(-n+3) { border-bottom: none; }
    .med-kpi__icon {
        flex: 0 0 42px;
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .med-kpi__icon--primary { color: #465fff; background: rgba(70, 95, 255, .12); }
    .med-kpi__icon--info { color: #0ba5ec; background: rgba(11, 165, 236, .12); }
    .med-kpi__icon--warning { color: #f79009; background: rgba(247, 144, 9, .12); }
    .med-kpi__icon--success { color: #12b76a; background: rgba(18, 183, 106, .12); }
    .med-kpi__icon--secondary { color: #667085; background: rgba(152, 162, 179, .16); }
    .med-kpi__icon--violet { color: #7a5af8; background: rgba(122, 90, 248, .12); }
    .med-kpi__icon--rose { color: #e31b54; background: rgba(227, 27, 84, .1); }
    .med-kpi__icon--teal { color: #0e9384; background: rgba(14, 147, 132, .12); }
    .med-kpi__icon--amber { color: #b54708; background: rgba(247, 144, 9, .14); }
    .med-kpi__copy { min-width: 0; flex: 1; }
    .med-kpi__label {
        color: #667085;
        font-size: .78rem;
        font-weight: 600;
        margin-bottom: 4px;
        line-height: 1.3;
    }
    .med-kpi__value-row {
        display: flex;
        align-items: baseline;
        gap: 8px;
        min-width: 0;
        flex-wrap: wrap;
    }
    .med-kpi__value {
        font-size: 1.45rem;
        font-weight: 700;
        line-height: 1.15;
        color: #101828;
        letter-spacing: -.02em;
        font-variant-numeric: tabular-nums;
    }
    .med-kpi__unit {
        color: #667085;
        font-size: .8rem;
        font-weight: 500;
        white-space: nowrap;
    }
    .med-kpi__meta {
        margin-top: 6px;
        color: #98a2b3;
        font-size: .75rem;
        line-height: 1.45;
    }
    .med-kpi__chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    .med-kpi__chip {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
    }
    .med-kpi__chip--warn { color: #b54708; background: #fffaeb; }
    .med-kpi__chip--danger { color: #b42318; background: #fef3f2; }
    @media (max-width: 1199.98px) {
        .med-kpi__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .med-kpi__grid > .med-kpi__cell { border-inline-end: 1px solid var(--bs-border-color, #eaecf0); border-bottom: 1px solid var(--bs-border-color, #eaecf0); }
        .med-kpi__grid > .med-kpi__cell:nth-child(3n) { border-inline-end: 1px solid var(--bs-border-color, #eaecf0); }
        .med-kpi__grid > .med-kpi__cell:nth-child(2n) { border-inline-end: none; }
        .med-kpi__grid > .med-kpi__cell:nth-last-child(-n+3) { border-bottom: 1px solid var(--bs-border-color, #eaecf0); }
        .med-kpi__grid > .med-kpi__cell:nth-last-child(-n+2) { border-bottom: none; }
    }
    @media (max-width: 575.98px) {
        .med-kpi__grid { grid-template-columns: minmax(0, 1fr); }
        .med-kpi__grid > .med-kpi__cell,
        .med-kpi__grid > .med-kpi__cell:nth-child(2n),
        .med-kpi__grid > .med-kpi__cell:nth-child(3n),
        .med-kpi__grid > .med-kpi__cell:nth-last-child(-n+3),
        .med-kpi__grid > .med-kpi__cell:nth-last-child(-n+2) {
            border-inline-end: none;
            border-bottom: 1px solid var(--bs-border-color, #eaecf0);
        }
        .med-kpi__grid > .med-kpi__cell:last-child { border-bottom: none; }
    }
    [data-bs-theme="dark"] .med-kpi__label,
    [data-bs-theme="dark"] .med-kpi__unit { color: #98a2b3; }
    [data-bs-theme="dark"] .med-kpi__value { color: #f2f4f7; }
    [data-bs-theme="dark"] .med-kpi__meta { color: #667085; }
    [data-bs-theme="dark"] .med-kpi__chip--warn { color: #fdb022; background: rgba(247, 144, 9, .16); }
    [data-bs-theme="dark"] .med-kpi__chip--danger { color: #f97066; background: rgba(240, 68, 56, .16); }

    .med-map-card { overflow: hidden; }
    .med-iran-map,
    #medicalIranMap {
        height: 430px;
        border-radius: 12px;
        overflow: hidden;
        background: #f9fafb;
        border: 1px solid #e4e7ec;
    }
    #medicalIranMap .leaflet-container {
        background: #f9fafb;
        cursor: default;
    }
    #medicalIranMap .leaflet-control-zoom,
    #medicalIranMap .leaflet-control-attribution {
        display: none !important;
    }
    #medicalIranMap .leaflet-grab,
    #medicalIranMap .leaflet-dragging .leaflet-grab {
        cursor: default;
    }
    #medicalCityList .city-row:hover {
        background: #f9fafb !important;
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const VENDOR_LEAFLET = @json(vasset('vendor/leaflet/leaflet.js'));
    const GEOJSON_URL = @json(vasset('vendor/iran-map/provinces.min.geojson'));
    const ns = window.__taMedicalReportMap = window.__taMedicalReportMap || {};
    const faNum = n => new Intl.NumberFormat('fa-IR').format(n || 0);

    function readPayload() {
        const el = document.getElementById('medical-report-map-payload');
        if (!el) return { geoCounts: {}, geoIds: {}, geoMax: 0 };
        try { return JSON.parse(el.textContent || '{}'); } catch (e) { return { geoCounts: {}, geoIds: {}, geoMax: 0 }; }
    }

    function heatColor(v, geoMax) {
        if (!v) return '#eef1f6';
        const t = geoMax > 0 ? v / geoMax : 0;
        if (t > 0.8) return '#1d39c4';
        if (t > 0.6) return '#465fff';
        if (t > 0.4) return '#7592ff';
        if (t > 0.2) return '#a4b6ff';
        return '#cdd8ff';
    }

    function reportComponent() {
        const id = document.getElementById('medicalReportRoot')?.dataset.reportId;
        return id && window.Livewire ? window.Livewire.find(id) : null;
    }

    function countFor(geoCounts, name) {
        if (!name) return 0;
        if (geoCounts[name]) return geoCounts[name];
        const stripped = String(name).replace(/^استان\s+/, '');
        return geoCounts[stripped] || geoCounts['استان ' + stripped] || 0;
    }

    function lookup(map, name) {
        if (!name || !map) return undefined;
        if (map[name] != null) return map[name];
        const stripped = String(name).replace(/^استان\s+/, '');
        return map[stripped] ?? map['استان ' + stripped];
    }

    function destroyMap() {
        ns.generation = (ns.generation || 0) + 1;
        if (ns.abort) { ns.abort.abort(); ns.abort = null; }
        const el = document.getElementById('medicalIranMap');
        const map = ns.instance || el?._leafletMap;
        if (map) {
            try { map.off(); map.remove(); } catch (e) {}
        }
        ns.instance = null;
        if (el) {
            el._leafletMap = null;
            delete el.dataset.iranMapReady;
            el.replaceChildren();
        }
    }

    function ensureLeaflet(cb) {
        if (window.L && document.getElementById('vendor-leaflet-sdk')?.dataset.ready === '1') {
            cb(); return;
        }
        if (window.L && !document.getElementById('vendor-leaflet-sdk')) {
            const marker = document.createElement('script');
            marker.id = 'vendor-leaflet-sdk';
            marker.dataset.ready = '1';
            document.body.appendChild(marker);
            cb(); return;
        }
        if (ns.loading) return;
        ns.loading = true;
        const script = document.createElement('script');
        script.id = 'vendor-leaflet-sdk';
        script.src = VENDOR_LEAFLET;
        script.onload = function () { script.dataset.ready = '1'; ns.loading = false; cb(); };
        script.onerror = function () { ns.loading = false; };
        document.body.appendChild(script);
    }

    function initMap() {
        const mapEl = document.getElementById('medicalIranMap');
        if (!mapEl || !window.L || !mapEl.isConnected) return;
        if (!mapEl.offsetWidth) {
            requestAnimationFrame(initMap);
            return;
        }
        destroyMap();
        const payload = readPayload();
        const geoCounts = payload.geoCounts || {};
        const geoIds = payload.geoIds || {};
        const geoMax = payload.geoMax || 0;
        const generation = ns.generation || 0;

        const map = L.map(mapEl, {
            zoomControl: false,
            attributionControl: false,
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            zoomSnap: 0.1,
        });
        map.dragging.disable();
        map.touchZoom.disable();
        map.doubleClickZoom.disable();
        map.scrollWheelZoom.disable();
        map.boxZoom.disable();
        map.keyboard.disable();
        if (map.tap) map.tap.disable();
        mapEl._leafletMap = map;
        ns.instance = map;
        let geoLayer = null;

        ns.abort = new AbortController();
        fetch(GEOJSON_URL, { signal: ns.abort.signal })
            .then(r => { if (!r.ok) throw new Error('geojson'); return r.json(); })
            .then(geo => {
                if (generation !== ns.generation || !mapEl.isConnected) return;
                geoLayer = L.geoJSON(geo, {
                    style: f => ({
                        fillColor: heatColor(countFor(geoCounts, f.properties['name:fa']), geoMax),
                        weight: 1, color: '#ffffff', fillOpacity: 0.9
                    }),
                    onEachFeature: (f, lyr) => {
                        const name = f.properties['name:fa'];
                        const v = countFor(geoCounts, name);
                        lyr.bindTooltip(
                            `<div style="font-family:Vazirmatn,sans-serif;font-size:12px;text-align:right"><b>${name}</b><br>${faNum(v)} رزرو اسکان درمانی</div>`,
                            { sticky: true, direction: 'top' }
                        );
                        lyr.on({
                            mouseover: e => e.target.setStyle({ weight: 2, color: '#465fff', fillOpacity: 1 }),
                            mouseout: e => { if (geoLayer) geoLayer.resetStyle(e.target); },
                            click: () => {
                                const id = Number(lookup(geoIds, name) || 0);
                                if (id) reportComponent()?.call('openProvince', id);
                            },
                        });
                    }
                }).addTo(map);

                function lockCamera() {
                    map.fitBounds(geoLayer.getBounds(), { padding: [14, 14], animate: false });
                    const z = map.getZoom();
                    map.setMinZoom(z);
                    map.setMaxZoom(z);
                }
                function freezeCamera() {
                    lockCamera();
                    ['panBy', 'panTo', 'flyTo', 'flyToBounds', 'setZoom', 'zoomIn', 'zoomOut'].forEach(fn => {
                        map[fn] = function () { return this; };
                    });
                }
                lockCamera();
                mapEl.dataset.iranMapReady = '1';
                setTimeout(() => {
                    try {
                        map.invalidateSize();
                        freezeCamera();
                    } catch (e) {}
                }, 80);
            })
            .catch(err => { if (err?.name === 'AbortError') return; })
            .finally(() => { if (!ns.abort?.signal.aborted) ns.abort = null; });
    }

    function boot() {
        if (!document.getElementById('medicalIranMap')) return;
        ensureLeaflet(() => requestAnimationFrame(() => requestAnimationFrame(initMap)));
    }

    if (!ns.bound) {
        ns.bound = true;
        document.addEventListener('livewire:navigating', destroyMap);
        document.addEventListener('livewire:navigated', boot);
        document.addEventListener('medical-report-map-refresh', boot);
        if (window.Livewire) {
            Livewire.on('medical-report-map-refresh', boot);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
