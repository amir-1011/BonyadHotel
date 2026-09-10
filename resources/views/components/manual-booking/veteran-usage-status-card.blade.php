{{--
    Veteran quota + service discount status for manual booking (step 2).
    @param array $usageSummary
    @param array $serviceCatalogSummary
    @param array $accommodationUsageCheck
    @param array $veteranGroups
--}}
@props([
    'usageSummary' => [],
    'serviceCatalogSummary' => [],
    'accommodationUsageCheck' => [],
    'veteranGroups' => [],
    'checkIn' => null,
    'checkOut' => null,
    'totalGuests' => 1,
])

@php
    use App\Services\AccommodationDiscountTierEngine;
    use App\Services\ServiceDiscountTierEngine;

    $usedTotal        = (int) ($usageSummary['used_total'] ?? 0);
    $totalQuota       = (int) ($usageSummary['total_quota'] ?? 0);
    $remainingTotal   = (int) ($usageSummary['remaining_total'] ?? 0);
    $usedPeriod       = (int) ($usageSummary['used_in_period'] ?? 0);
    $maxPeriod        = (int) ($usageSummary['max_nights_per_period'] ?? 3);
    $remainPeriod     = (int) ($usageSummary['remaining_period'] ?? 0);
    $periodMonths     = (int) ($usageSummary['period_months'] ?? 6);
    $accDiscount      = (int) ($usageSummary['accommodation_discount'] ?? 0);
    $nightsPerDep     = (int) ($usageSummary['nights_per_dependent'] ?? 6);
    $groupSummaries   = $usageSummary['group_summaries'] ?? [];
    $dualGroupCaps    = count($groupSummaries) > 1;
    $periodDeductions = $usageSummary['period_deductions'] ?? [];
    $periodPct  = $maxPeriod > 0 ? min(100, (int) round($usedPeriod / $maxPeriod * 100)) : 0;
    $periodColor = $periodPct >= 100 ? 'danger' : ($periodPct >= 67 ? 'warning' : 'success');
    $combinedRemain = (int) ($usageSummary['combined_remaining_discounted_nights'] ?? 0);
    $canBookNights = $combinedRemain > 0
        ? $combinedRemain
        : (($usageSummary['unlimited_total_quota'] ?? false)
            ? $remainPeriod
            : min($remainPeriod, $remainingTotal));
    $requestedNights = 0;
    if ($checkIn && $checkOut) {
        $requestedNights = (int) (new \DateTime($checkIn))->diff(new \DateTime($checkOut))->days;
    }
    $accUsage = $accommodationUsageCheck ?? [];
    if (!empty($accUsage['discounted_nights']) && $requestedNights > 0) {
        $discountedNights = (int) $accUsage['discounted_nights'];
    } else {
        $discountedNights = min($canBookNights, $requestedNights);
    }
    $fullRateNights = max(0, $requestedNights - $discountedNights);

    $serviceCatalogItems = $serviceCatalogSummary['services'] ?? [];
    $catalogGroups = $serviceCatalogSummary['veteran_groups'] ?? [];
    $multiVeteranGroups = count($catalogGroups) > 1;

    $serviceVisuals = [
        'pool' => ['icon' => 'droplet-fill', 'tone' => 'info'],
        'gym' => ['icon' => 'dumbbell', 'tone' => 'secondary'],
        'multi_purpose_hall' => ['icon' => 'grid-fill', 'tone' => 'warning'],
    ];

    $resolveAccTiers = function (array $summary): array {
        if (!empty($summary['use_tiered_accommodation_discount']) && !empty($summary['accommodation_discount_tiers'])) {
            $labels = [];
            foreach (AccommodationDiscountTierEngine::normalizeTiers($summary['accommodation_discount_tiers']) as $i => $tier) {
                $labels[] = AccommodationDiscountTierEngine::describePolicyTier($tier, $i);
            }
            return $labels;
        }
        return [];
    };
@endphp

<div class="card shadow-sm border-primary border-opacity-25 mt-3 mbf-veteran-status-card" style="font-size:.83rem;">
    <div class="card-header bg-primary bg-opacity-10 py-3">
        <div class="d-flex flex-column gap-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:36px;height:36px">
                    <i class="bi bi-shield-fill-check"></i>
                </span>
                <div>
                    <div class="fw-semibold text-primary">وضعیت سقف استفاده و تخفیف‌ها</div>
                    <div class="text-muted small">سهمیه اقامت و خدمات برای گروه انتخاب‌شده</div>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-primary">{{ $usageSummary['label'] ?? '' }}</span>
                <span class="badge bg-white text-secondary border">
                    <i class="bi bi-globe2 me-1"></i>سهمیه مشترک بین تمام اقامتگاه‌ها
                </span>
            </div>
        </div>
    </div>

    <div class="card-body p-3">

        {{-- ── Accommodation ── --}}
        <div class="rounded border bg-light bg-opacity-50 p-3 mb-3">
            <div class="mbf-vs-section-title mb-2 d-flex align-items-center gap-2">
                <i class="bi bi-house-heart text-primary"></i>
                تخفیف و سقف اقامت
            </div>

            @if($dualGroupCaps)
                @foreach($groupSummaries as $groupSummary)
                @php
                    $gUsed = (int) ($groupSummary['used_in_period'] ?? 0);
                    $gMax = (int) ($groupSummary['max_nights_per_period'] ?? 3);
                    $gRemain = (int) ($groupSummary['remaining_period'] ?? 0);
                    $gPct = $gMax > 0 ? min(100, (int) round($gUsed / $gMax * 100)) : 0;
                    $gColor = $gPct >= 100 ? 'danger' : ($gPct >= 67 ? 'warning' : 'success');
                    $accTierLabels = $resolveAccTiers($groupSummary);
                @endphp
                <div class="bg-white rounded border p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                        <span class="fw-semibold small">{{ $groupSummary['label'] ?? '' }}</span>
                        @if(!empty($accTierLabels))
                            <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">پلکانی</span>
                        @else
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                {{ (int) ($groupSummary['accommodation_discount'] ?? 0) }}٪ تخفیف اقامت
                            </span>
                        @endif
                    </div>
                    @if(!empty($accTierLabels))
                    <ul class="mbf-vs-tier-ladder mb-2">
                        @foreach($accTierLabels as $label)
                        <li>{{ $label }}</li>
                        @endforeach
                    </ul>
                    @endif
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">سقف {{ $periodMonths }} ماهه</span>
                        <span class="text-{{ $gColor }} fw-bold">{{ $gUsed }} / {{ $gMax }} شب</span>
                    </div>
                    <div class="progress" style="height:6px">
                        <div class="progress-bar bg-{{ $gColor }}" style="width:{{ $gPct }}%"></div>
                    </div>
                    <div class="small text-muted mt-1">
                        @if($gRemain > 0)
                            {{ $gRemain }} شب باقی‌مانده در این دوره
                        @else
                            <span class="text-danger">سقف این گروه در دوره جاری تکمیل شده</span>
                        @endif
                    </div>
                </div>
                @endforeach
                @if($combinedRemain > 0)
                <div class="small text-muted">
                    <i class="bi bi-layers me-1"></i>مجموع ظرفیت باقی‌مانده برای تخفیف اقامت: <strong>{{ $combinedRemain }} شب</strong>
                </div>
                @endif
            @else
                @php
                    $accTierLabels = $resolveAccTiers($usageSummary);
                    $useTieredAcc = !empty($usageSummary['use_tiered_accommodation_discount']);
                @endphp
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if($useTieredAcc && !empty($accTierLabels))
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                            <i class="bi bi-bar-chart-steps me-1"></i>تخفیف اقامت پلکانی
                        </span>
                    @else
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                            {{ $accDiscount }}٪ تخفیف اقامت
                        </span>
                    @endif
                </div>
                @if(!empty($accTierLabels))
                <ul class="mbf-vs-tier-ladder mb-3">
                    @foreach($accTierLabels as $label)
                    <li>{{ $label }}</li>
                    @endforeach
                </ul>
                <div class="small text-muted mb-2">
                    <i class="bi bi-info-circle me-1"></i>
                    پله‌ها بر اساس <strong>شب‌های مصرف‌شده در دوره {{ $periodMonths }} ماهه</strong> (رزروهای قبلی + شب‌های این رزرو) اعمال می‌شوند.
                </div>
                @endif

                <div class="d-flex justify-content-between align-items-baseline mb-1">
                    <span class="small fw-semibold text-{{ $periodColor }}">
                        <i class="bi bi-calendar-range me-1"></i>سقف دوره‌ای ({{ $periodMonths }} ماه)
                    </span>
                    <span class="text-{{ $periodColor }} fw-bold small">{{ $usedPeriod }} / {{ $maxPeriod }} شب</span>
                </div>
                <div class="progress mb-1" style="height:8px">
                    <div class="progress-bar bg-{{ $periodColor }}" style="width:{{ $periodPct }}%"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted">
                    <span>
                        @if($remainPeriod > 0)
                            <i class="bi bi-check-circle text-success me-1"></i>{{ $remainPeriod }} شب باقی‌مانده
                        @else
                            <i class="bi bi-x-circle text-danger me-1"></i>سقف دوره تکمیل شده
                        @endif
                    </span>
                    <span>تجدید هر {{ $periodMonths }} ماه</span>
                </div>
                @if(!empty($periodDeductions))
                <details class="mt-2 small">
                    <summary class="text-muted cursor-pointer">رزروهای کاهش‌دهنده سهمیه ({{ count($periodDeductions) }})</summary>
                    <div class="mt-2 ps-2 border-start border-2 border-{{ $periodColor }} border-opacity-50">
                        @foreach($periodDeductions as $deduction)
                        <div class="d-flex justify-content-between gap-2 py-1 {{ !$loop->last ? 'border-bottom border-light' : '' }}">
                            <span>
                                <span dir="ltr" class="text-muted">{{ $deduction['tracking_code'] ?? '—' }}</span>
                                · {{ $deduction['accommodation_name'] }}
                                · @jalali($deduction['check_in']) تا @jalali($deduction['check_out'])
                            </span>
                            <span class="text-{{ $periodColor }} fw-bold text-nowrap">{{ $deduction['nights'] }} شب</span>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endif
            @endif

            @if($requestedNights > 0)
                @if($discountedNights > 0 && $fullRateNights > 0)
                <div class="alert alert-warning py-2 px-2 mt-3 mb-0 small">
                    <i class="bi bi-info-circle-fill me-1"></i>
                    از <strong>{{ $requestedNights }} شب</strong> این رزرو:
                    <strong class="text-success">{{ $discountedNights }} شب</strong> با تخفیف ایثارگری و
                    <strong>{{ $fullRateNights }} شب</strong> با نرخ عادی.
                </div>
                @elseif($discountedNights > 0)
                <div class="alert alert-success py-2 px-2 mt-3 mb-0 small">
                    <i class="bi bi-moon-stars-fill me-1"></i>
                    تمام <strong>{{ $discountedNights }} شب</strong> این رزرو با تخفیف ایثارگری محاسبه می‌شود.
                </div>
                @else
                <div class="alert alert-danger py-2 px-2 mt-3 mb-0 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    سقف تخفیف تکمیل شده — تمام {{ $requestedNights }} شب با نرخ عادی.
                </div>
                @endif
            @elseif($canBookNights > 0)
            <div class="alert alert-success py-2 px-2 mt-3 mb-0 small">
                حداکثر <strong>{{ $canBookNights }} شب</strong> با تخفیف ایثارگری در دسترس است.
            </div>
            @endif
        </div>

        {{-- ── Services ── --}}
        @if(!empty($serviceCatalogItems))
        <div class="mb-2">
            <div class="mbf-vs-section-title mb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span><i class="bi bi-bag-check text-primary me-1"></i>تخفیف خدمات</span>
                @if($checkIn)
                <span class="text-muted small fw-normal">
                    <i class="bi bi-calendar-week me-1"></i>هفته مرجع: @jalali($checkIn)
                </span>
                @endif
            </div>
            <div class="row g-2">
                @foreach($serviceCatalogItems as $svc)
                @php
                    $visual = $serviceVisuals[$svc['key'] ?? ''] ?? ['icon' => 'bag-check', 'tone' => 'primary'];
                    $weekly = $svc['weekly_usage'] ?? null;
                    $svcUsed = (int) ($weekly['used'] ?? 0);
                    $svcQuota = (int) ($weekly['quota'] ?? 0);
                    $svcRemain = (int) ($weekly['remaining'] ?? 0);
                    $svcPct = $svcQuota > 0 ? min(100, (int) round($svcUsed / $svcQuota * 100)) : 0;
                    $svcColor = $svcRemain <= 0 && $svcQuota > 0 ? 'danger' : ($svcUsed > 0 ? 'warning' : 'success');
                @endphp
                <div class="col-md-6">
                    <div class="mbf-vs-service-card h-100 p-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="rounded bg-{{ $visual['tone'] }} bg-opacity-10 text-{{ $visual['tone'] }} d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                  style="width:34px;height:34px">
                                <i class="bi bi-{{ $visual['icon'] }}"></i>
                            </span>
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="fw-semibold small">{{ $svc['name'] }}</div>
                                @if($svcQuota > 0)
                                <div class="small text-muted">سقف هفتگی جلسات رایگان/پلکانی</div>
                                @endif
                            </div>
                        </div>

                        @if($svcQuota > 0)
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">مصرف هفته</span>
                                <span class="text-{{ $svcColor }} fw-bold">{{ $svcRemain }} باقی‌مانده از {{ $svcQuota }}</span>
                            </div>
                            <div class="progress" style="height:5px">
                                <div class="progress-bar bg-{{ $svcColor }}" style="width:{{ $svcPct }}%"></div>
                            </div>
                            @if(!empty($weekly['group_details']) && $multiVeteranGroups)
                            <div class="mt-1">
                                @foreach($weekly['group_details'] as $gd)
                                <div class="text-muted" style="font-size:.7rem">
                                    {{ $gd['label'] }}: {{ $gd['remaining'] }}/{{ $gd['quota'] }} جلسه
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                        @endif

                        @foreach($svc['rules_by_group'] ?? [] as $rule)
                        <div class="{{ !$loop->first ? 'border-top pt-2 mt-2' : '' }}">
                            @if($multiVeteranGroups)
                            <div class="badge bg-secondary bg-opacity-10 text-secondary border mb-1" style="font-size:.65rem">
                                {{ $rule['group_label'] }}
                            </div>
                            @endif

                            @if(!empty($rule['use_tiered_discount']))
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:.65rem">
                                    <i class="bi bi-bar-chart-steps me-1"></i>پلکانی (هفتگی)
                                </span>
                            </div>
                            <ul class="mbf-vs-tier-ladder">
                                @foreach($rule['tier_labels'] ?? [] as $tierLabel)
                                <li>{{ $tierLabel }}</li>
                                @endforeach
                            </ul>
                            @else
                            <div class="small">
                                @if((int) ($rule['discount_percentage'] ?? 0) > 0)
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                    {{ (int) $rule['discount_percentage'] }}٪ تخفیف
                                </span>
                                @endif
                                @if(!empty($rule['free_sessions_eligible']) && (int) ($rule['weekly_free_sessions'] ?? 0) > 0)
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25">
                                    {{ (int) $rule['weekly_free_sessions'] }} جلسه رایگان / هفته
                                </span>
                                @endif
                                @if((int) ($rule['discount_percentage'] ?? 0) === 0 && empty($rule['free_sessions_eligible']))
                                <span class="text-muted">بدون تخفیف ایثارگری</span>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-muted small mt-2">
                <i class="bi bi-info-circle me-1"></i>
                سهمیه جلسات رایگان بین تمام رزروهای همان هفته در همه اقامتگاه‌ها مشترک است؛ در حالت پلکانی، هر جلسهٔ بعدی طبق پله بعدی محاسبه می‌شود.
            </div>
        </div>
        @endif

        @if(!empty($usageSummary['usage_notes']))
        <div class="border-top pt-2 mt-2 text-muted small">
            <i class="bi bi-journal-text me-1"></i>{{ $usageSummary['usage_notes'] }}
        </div>
        @endif
    </div>
</div>
