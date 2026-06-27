<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use App\Support\VeteranGroups;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class VeteranPolicyService
{
    private const CACHE_PREFIX = 'veteran_policy_data';
    private const CACHE_TTL = 300;

    private ?int $accommodationId = null;

    /** @var array<string, string> Legacy keys from older bookings/users */
    public const LEGACY_KEY_MAP = [
        'veteran_70_plus'       => 'veteran_70_spouses',
        'veteran_50_69'         => 'veteran_50_69_dependents',
        'veteran_25_49'         => 'veteran_25_49_dependents',
        'martyr_family'         => 'martyr_spouse_dependents',
        'freed_prisoner_family' => 'freed_prisoner_dependents',
    ];

    public function forAccommodation(?int $accommodationId): self
    {
        $clone = clone $this;
        $clone->accommodationId = $accommodationId;

        return $clone;
    }

    public function accommodationId(): ?int
    {
        return $this->accommodationId;
    }

    public function normalizeKey(?string $key): ?string
    {
        return $key ? (self::LEGACY_KEY_MAP[$key] ?? $key) : null;
    }

    /** @return Collection<int, VeteranGroup> */
    public function activeGroups(): Collection
    {
        return $this->cached()['groups'];
    }

    /** @return Collection<int, ServiceCatalog> */
    public function activeServices(): Collection
    {
        return $this->cached()['services'];
    }

    public function groupByKey(?string $key): ?VeteranGroup
    {
        $key = $this->normalizeKey($key);
        if (!$key) {
            return null;
        }

        return $this->cached()['groups_by_key'][$key] ?? null;
    }

    public function serviceById(?int $id): ?ServiceCatalog
    {
        if (!$id) {
            return null;
        }

        return $this->cached()['services_by_id'][$id] ?? null;
    }

    public function accommodationDiscount(?string $veteranKey): int
    {
        return $this->accommodationDiscountForTypes(
            $veteranKey ? [$veteranKey] : [],
        );
    }

    /**
     * @param  array<int, string|null>  $veteranKeys
     */
    public function accommodationDiscountForTypes(array $veteranKeys): int
    {
        $keys = $this->normalizeVeteranTypes($veteranKeys);
        if (empty($keys)) {
            return 0;
        }

        return (int) collect($keys)
            ->map(fn (string $key) => $this->singleAccommodationDiscount($key))
            ->max();
    }

    /**
     * @param  array<int, string|null>|string|null  $primary
     * @return array<int, string>
     */
    public function normalizeVeteranTypes(array|string|null $primary, ?string $secondary = null): array
    {
        $raw = [];

        if (is_string($primary)) {
            $raw[] = $primary;
            if ($secondary) {
                $raw[] = $secondary;
            }
        } elseif (is_array($primary)) {
            $raw = $primary;
        }

        $keys = collect($raw)
            ->map(fn ($key) => $this->normalizeKey(is_string($key) ? $key : null))
            ->filter()
            ->unique()
            ->take(2)
            ->values()
            ->all();

        if (count($keys) <= 1) {
            return $keys;
        }

        usort($keys, fn (string $a, string $b) => $this->groupPriority($b) <=> $this->groupPriority($a));

        return array_values($keys);
    }

    /**
     * @param  array<int, string|null>|string|null  $primary
     * @return array{0:?string, 1:?string}
     */
    public function splitVeteranTypes(array|string|null $primary, ?string $secondary = null): array
    {
        $keys = $this->normalizeVeteranTypes($primary, $secondary);

        return [
            $keys[0] ?? null,
            $keys[1] ?? null,
        ];
    }

    public function groupPriority(?string $veteranKey): int
    {
        return $this->singleAccommodationDiscount($veteranKey);
    }

    private function singleAccommodationDiscount(?string $veteranKey): int
    {
        $group = $this->groupByKey($veteranKey);
        if ($group) {
            return $group->accommodation_discount;
        }

        foreach ($this->defaultGroupDefinitions() as $def) {
            if ($def['key'] === $this->normalizeKey($veteranKey)) {
                return $def['accommodation_discount'];
            }
        }

        return 0;
    }

    /**
     * @return array{
     *   discount_percentage:int,
     *   free_sessions_eligible:bool,
     *   min_discount:?int,
     *   max_discount:?int,
     *   service_catalog_id:?int,
     *   weekly_free_sessions:int,
     *   use_tiered_discount:bool,
     *   discount_tiers:array<int, array<string, mixed>>
     * }
     */
    public function serviceDiscountRule(?string $veteranKey, ?int $serviceCatalogId, ?int $overridePct = null): array
    {
        $group = $this->groupByKey($veteranKey);
        $service = $this->serviceById($serviceCatalogId);

        if (!$group || !$service) {
            return [
                'discount_percentage'    => 0,
                'free_sessions_eligible' => false,
                'min_discount'           => null,
                'max_discount'           => null,
                'service_catalog_id'     => $serviceCatalogId,
                'weekly_free_sessions'   => 0,
                'use_tiered_discount'    => false,
                'discount_tiers'         => [],
            ];
        }

        $matrix = $this->cached()['discount_matrix'];
        $rule = $matrix[$group->id][$service->id] ?? null;

        $pct = $rule?->discount_percentage ?? $service->default_discount;
        $min = $service->min_discount;
        $max = $service->max_discount;

        if ($overridePct !== null && $min !== null && $max !== null) {
            $pct = max($min, min($max, $overridePct));
        }

        $useTiered = (bool) ($rule?->use_tiered_discount ?? false);
        $tiers = $useTiered
            ? ServiceDiscountTierEngine::normalizeTiers($rule?->discount_tiers ?? [])
            : [];

        return [
            'discount_percentage'    => $pct,
            'free_sessions_eligible' => (bool) ($rule?->free_sessions_eligible ?? false),
            'min_discount'           => $min,
            'max_discount'           => $max,
            'service_catalog_id'     => $service->id,
            'weekly_free_sessions'   => (int) ($rule?->weekly_free_sessions ?? 0),
            'use_tiered_discount'    => $useTiered && !empty($tiers),
            'discount_tiers'         => $tiers,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array<string, mixed>>
     */
    public function enrichServicesWithDiscounts(?string $veteranKey, array $services): array
    {
        return $this->enrichServicesWithDiscountsForTypes(
            $veteranKey ? [$veteranKey] : [],
            $services,
        );
    }

    /**
     * @param  array<int, string|null>|string|null  $veteranKeys
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array<string, mixed>>
     */
    public function enrichServicesWithDiscountsForTypes(array|string|null $veteranKeys, array $services): array
    {
        $keys = $this->normalizeVeteranTypes($veteranKeys);

        return array_map(function (array $service) use ($keys) {
            $catalogId = isset($service['service_catalog_id']) ? (int) $service['service_catalog_id'] : null;
            $override = isset($service['discount_override']) && $service['discount_override'] !== ''
                ? (int) $service['discount_override']
                : null;

            if (count($keys) <= 1) {
                $rule = $this->serviceDiscountRule($keys[0] ?? null, $catalogId, $override);
            } else {
                $rule = $this->mergedServiceDiscountRule($keys, $catalogId, $override);
            }

            $service['discount_percentage'] = $rule['discount_percentage'];
            $service['free_sessions_eligible'] = $rule['free_sessions_eligible'];
            $service['weekly_free_sessions'] = $rule['weekly_free_sessions'] ?? 0;
            $service['use_tiered_discount'] = $rule['use_tiered_discount'];
            $service['discount_tiers'] = $rule['discount_tiers'];
            $service['multi_group_rules'] = $rule['multi_group_rules'] ?? [];

            return $service;
        }, $services);
    }

    /**
     * @param  array<int, string>  $veteranKeys
     * @return array<string, mixed>
     */
    public function mergedServiceDiscountRule(array $veteranKeys, ?int $serviceCatalogId, ?int $overridePct = null): array
    {
        $groupRules = [];
        foreach ($this->normalizeVeteranTypes($veteranKeys) as $key) {
            $groupRules[] = array_merge(
                $this->serviceDiscountRule($key, $serviceCatalogId, $overridePct),
                [
                    'key'      => $key,
                    'priority' => $this->groupPriority($key),
                ],
            );
        }

        $primary = $groupRules[0] ?? $this->serviceDiscountRule(null, $serviceCatalogId, $overridePct);
        $primary['multi_group_rules'] = $groupRules;

        return $primary;
    }

    /**
     * @param  array<int, string>  $veteranKeys
     * @return array<int, array<string, mixed>>
     */
    public function groupDiscountConfigsForService(array $veteranKeys, ?int $serviceCatalogId): array
    {
        return collect($this->normalizeVeteranTypes($veteranKeys))
            ->map(function (string $key) use ($serviceCatalogId) {
                $rule = $this->serviceDiscountRule($key, $serviceCatalogId);

                return [
                    'key'                    => $key,
                    'priority'               => $this->groupPriority($key),
                'use_tiered_discount'    => $rule['use_tiered_discount'],
                'tiers'                  => $rule['discount_tiers'],
                'discount_tiers'         => $rule['discount_tiers'],
                    'free_sessions_eligible' => $rule['free_sessions_eligible'],
                    'weekly_free_sessions'   => $rule['weekly_free_sessions'],
                    'discount_percentage'    => $rule['discount_percentage'],
                ];
            })
            ->sortByDesc('priority')
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   allowed:bool,
     *   message:?string,
     *   total_quota:int,
     *   used_in_period:int,
     *   remaining_period:int,
     *   remaining_total:int,
     *   requested_nights:int,
     *   discounted_nights:int
     * }
     */
    public function checkAccommodationUsage(
        ?string $veteranKey,
        int $guests,
        int $requestedNights,
        ?string $nationalId = null,
        ?int $userId = null,
        ?int $excludeBookingId = null,
    ): array {
        $group = $this->groupByKey($veteranKey);
        if (!$group || $requestedNights <= 0) {
            return $this->usageResult(true, null, 0, 0, 0, 0, $requestedNights, max(0, $requestedNights));
        }

        $dependents = max(1, $guests);
        $totalQuota = $group->nights_per_dependent * $dependents;
        $usedInPeriod = $this->usedNightsInPeriod($veteranKey, $nationalId, $userId, $group->period_months, $excludeBookingId);
        $usedTotal = $this->usedNightsTotal($veteranKey, $nationalId, $userId, $excludeBookingId);
        $remainingPeriod = max(0, $group->max_nights_per_period - $usedInPeriod);
        $remainingTotal = max(0, $totalQuota - $usedTotal);

        $discountedNights = min($requestedNights, $remainingPeriod, $remainingTotal);

        $message = null;
        if ($discountedNights < $requestedNights) {
            $fullRateNights = $requestedNights - $discountedNights;
            if ($discountedNights === 0) {
                $message = "سقف تخفیف ایثارگری تکمیل شده؛ {$fullRateNights} شب با نرخ عادی (بدون تخفیف ایثارگری) محاسبه می‌شود.";
            } else {
                $message = "{$discountedNights} شب با تخفیف ایثارگری و {$fullRateNights} شب با نرخ عادی محاسبه می‌شود.";
            }
        }

        return $this->usageResult(
            true,
            $message,
            $totalQuota,
            $usedInPeriod,
            $remainingPeriod,
            $remainingTotal,
            $requestedNights,
            $discountedNights,
        );
    }

    /**
     * @param  array<int, string|null>|string|null  $veteranKeys
     * @return array{
     *   allowed:bool,
     *   message:?string,
     *   total_quota:int,
     *   used_in_period:int,
     *   remaining_period:int,
     *   remaining_total:int,
     *   requested_nights:int,
     *   discounted_nights:int,
     *   night_discounts: array<int, int>,
     *   group_usage: array<string, int>
     * }
     */
    public function checkAccommodationUsageForTypes(
        array|string|null $veteranKeys,
        int $guests,
        int $requestedNights,
        ?string $nationalId = null,
        ?int $userId = null,
        ?int $excludeBookingId = null,
    ): array {
        $keys = $this->normalizeVeteranTypes($veteranKeys);
        if (empty($keys) || $requestedNights <= 0) {
            return array_merge(
                $this->usageResult(true, null, 0, 0, 0, 0, $requestedNights, 0),
                ['night_discounts' => array_fill(0, max(0, $requestedNights), 0), 'night_group_keys' => array_fill(0, max(0, $requestedNights), null), 'group_usage' => []],
            );
        }

        if (count($keys) === 1) {
            $single = $this->checkAccommodationUsage(
                $keys[0],
                $guests,
                $requestedNights,
                $nationalId,
                $userId,
                $excludeBookingId,
            );
            $plan = $this->accommodationNightPlan(
                $keys,
                $guests,
                $requestedNights,
                $nationalId,
                $userId,
                $excludeBookingId,
            );

            return array_merge($single, [
                'night_discounts' => $plan['night_discounts'],
                'group_usage'     => $plan['group_usage'],
            ]);
        }

        $plan = $this->accommodationNightPlan(
            $keys,
            $guests,
            $requestedNights,
            $nationalId,
            $userId,
            $excludeBookingId,
        );
        $discountedNights = count(array_filter(
            $plan['night_discounts'],
            fn (int $pct) => $pct > 0,
        ));

        $message = null;
        if ($discountedNights < $requestedNights) {
            $fullRateNights = $requestedNights - $discountedNights;
            if ($discountedNights === 0) {
                $message = "سقف تخفیف ایثارگری تکمیل شده؛ {$fullRateNights} شب با نرخ عادی (بدون تخفیف ایثارگری) محاسبه می‌شود.";
            } else {
                $message = "{$discountedNights} شب با تخفیف ایثارگری و {$fullRateNights} شب با نرخ عادی محاسبه می‌شود.";
            }
        }

        $combinedRemaining = $this->combinedRemainingDiscountedNights($keys, $guests, $nationalId, $userId, $excludeBookingId);
        $primarySummary = $this->singleGroupUsageSummary($keys[0], $guests, $nationalId, $userId, null);

        return array_merge(
            $this->usageResult(
                true,
                $message,
                (int) ($primarySummary['total_quota'] ?? 0),
                (int) ($primarySummary['used_in_period'] ?? 0),
                $combinedRemaining,
                (int) ($primarySummary['remaining_total'] ?? 0),
                $requestedNights,
                $discountedNights,
            ),
            [
                'night_discounts' => $plan['night_discounts'],
                'group_usage'     => $plan['group_usage'],
                'combined_remaining_discounted_nights' => $combinedRemaining,
            ],
        );
    }

    /**
     * @param  array<int, string>  $veteranKeys
     * @return array{
     *   night_discounts: array<int, int>,
     *   night_group_keys: array<int, string|null>,
     *   group_usage: array<string, int>
     * }
     */
    public function accommodationNightPlan(
        array $veteranKeys,
        int $guests,
        int $requestedNights,
        ?string $nationalId = null,
        ?int $userId = null,
        ?int $excludeBookingId = null,
    ): array {
        $keys = $this->normalizeVeteranTypes($veteranKeys);
        $requestedNights = max(0, $requestedNights);

        if (empty($keys) || $requestedNights === 0) {
            return [
                'night_discounts'  => array_fill(0, $requestedNights, 0),
                'night_group_keys' => array_fill(0, $requestedNights, null),
                'group_usage'      => [],
            ];
        }

        if (count($keys) === 1) {
            $key = $keys[0];
            $usage = $this->checkAccommodationUsage(
                $key,
                $guests,
                $requestedNights,
                $nationalId,
                $userId,
                $excludeBookingId,
            );
            $pct = $this->singleAccommodationDiscount($key);
            $discounted = min($requestedNights, max(0, (int) ($usage['discounted_nights'] ?? 0)));
            $nightDiscounts = [];
            $nightGroupKeys = [];
            for ($i = 0; $i < $requestedNights; $i++) {
                $nightDiscounts[] = $i < $discounted ? $pct : 0;
                $nightGroupKeys[] = $i < $discounted ? $key : null;
            }

            return [
                'night_discounts'  => $nightDiscounts,
                'night_group_keys' => $nightGroupKeys,
                'group_usage'      => $discounted > 0 ? [$key => $discounted] : [],
            ];
        }

        $groups = [];
        foreach ($keys as $key) {
            $group = $this->groupByKey($key);
            if (!$group) {
                continue;
            }

            $dependents = max(1, $guests);
            $totalQuota = $group->nights_per_dependent * $dependents;
            $usedInPeriod = $this->usedNightsInPeriod($key, $nationalId, $userId, $group->period_months, $excludeBookingId);
            $usedTotal = $this->usedNightsTotal($key, $nationalId, $userId, $excludeBookingId);

            $groups[] = [
                'key'                    => $key,
                'accommodation_discount' => $group->accommodation_discount,
                'remaining_period'       => max(0, $group->max_nights_per_period - $usedInPeriod),
                'remaining_total'        => max(0, $totalQuota - $usedTotal),
            ];
        }

        return MultiGroupAccommodationEngine::allocateNights($requestedNights, $groups);
    }

    /**
     * @param  array<int, string>  $veteranKeys
     */
    public function combinedRemainingDiscountedNights(
        array $veteranKeys,
        int $guests,
        ?string $nationalId = null,
        ?int $userId = null,
        ?int $excludeBookingId = null,
    ): int {
        $keys = $this->normalizeVeteranTypes($veteranKeys);
        if (empty($keys)) {
            return 0;
        }

        $total = 0;
        foreach ($keys as $key) {
            $group = $this->groupByKey($key);
            if (!$group) {
                continue;
            }

            $dependents = max(1, $guests);
            $totalQuota = $group->nights_per_dependent * $dependents;
            $usedInPeriod = $this->usedNightsInPeriod($key, $nationalId, $userId, $group->period_months, $excludeBookingId);
            $usedTotal = $this->usedNightsTotal($key, $nationalId, $userId, $excludeBookingId);
            $remainingPeriod = max(0, $group->max_nights_per_period - $usedInPeriod);
            $remainingTotal = max(0, $totalQuota - $usedTotal);

            $total += min($remainingPeriod, $remainingTotal);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public static function describeAccommodationBreakdownItem(array $item): string
    {
        $units = (int) ($item['units'] ?? 0);
        $pct = (int) ($item['discount_percentage'] ?? 0);
        $label = trim((string) ($item['veteran_group_label'] ?? $item['veteran_group_key'] ?? ''));

        $nightText = $units === 1 ? '۱ شب' : "{$units} شب";
        $line = "{$nightText} با {$pct}٪ تخفیف اقامت";
        if ($label !== '') {
            $line .= " ({$label})";
        }

        $amount = (int) ($item['discount_amount'] ?? 0);
        if ($amount > 0) {
            $line .= ' (تخفیف ' . number_format($amount) . ' ت)';
        }

        return $line;
    }

    public function usageSummary(
        ?string $veteranKey,
        int $guests,
        ?string $nationalId = null,
        ?int $userId = null,
        ?string $referenceDate = null,
        ?string $secondaryVeteranKey = null,
    ): array {
        $keys = $this->normalizeVeteranTypes($veteranKey, $secondaryVeteranKey);
        if (empty($keys)) {
            return [];
        }

        if (count($keys) === 1) {
            return $this->singleGroupUsageSummary($keys[0], $guests, $nationalId, $userId, $referenceDate);
        }

        $summaries = collect($keys)
            ->map(fn (string $key) => $this->singleGroupUsageSummary($key, $guests, $nationalId, $userId, $referenceDate))
            ->filter()
            ->values();

        if ($summaries->isEmpty()) {
            return [];
        }

        $primary = $summaries->first();
        $secondary = $summaries->get(1);

        return array_merge($primary, [
            'label'                  => VeteranGroups::labelsForTypes($keys, $this->accommodationId),
            'accommodation_discount' => $this->accommodationDiscountForTypes($keys),
            'secondary_label'        => $secondary['label'] ?? null,
            'secondary_group_key'    => $keys[1] ?? null,
            'group_summaries'        => $summaries->all(),
            'combined_remaining_discounted_nights' => $this->combinedRemainingDiscountedNights(
                $keys,
                $guests,
                $nationalId,
                $userId,
            ),
            'weekly_free_usage'      => $this->weeklyFreeUsageByServiceForTypes(
                $keys,
                $nationalId,
                $userId,
                $referenceDate,
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function singleGroupUsageSummary(
        string $veteranKey,
        int $guests,
        ?string $nationalId,
        ?int $userId,
        ?string $referenceDate,
    ): array {
        $group = $this->groupByKey($veteranKey);
        if (!$group) {
            return [];
        }

        $referenceDate ??= now()->format('Y-m-d');
        $dependents = max(1, $guests);
        $totalQuota = $group->nights_per_dependent * $dependents;
        $usedInPeriod = $this->usedNightsInPeriod($veteranKey, $nationalId, $userId, $group->period_months);
        $usedTotal = $this->usedNightsTotal($veteranKey, $nationalId, $userId);

        return [
            'group_key'              => $veteranKey,
            'label'                  => $group->label,
            'accommodation_discount' => $group->accommodation_discount,
            'nights_per_dependent'   => $group->nights_per_dependent,
            'total_quota'            => $totalQuota,
            'used_total'             => $usedTotal,
            'remaining_total'        => max(0, $totalQuota - $usedTotal),
            'period_months'          => $group->period_months,
            'max_nights_per_period'  => $group->max_nights_per_period,
            'used_in_period'         => $usedInPeriod,
            'remaining_period'       => max(0, $group->max_nights_per_period - $usedInPeriod),
            'usage_notes'            => $group->usage_notes,
            'weekly_free_usage'      => $this->weeklyFreeUsageByService($veteranKey, $nationalId, $userId, $referenceDate),
        ];
    }

    public function usedFreeSessionsInWeek(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): int {
        $rule = $this->serviceDiscountRule($veteranKey, $serviceCatalogId);

        if ($rule['use_tiered_discount']) {
            $freeQuota = ServiceDiscountTierEngine::freeTierQuota($rule['discount_tiers']);
            if ($freeQuota <= 0) {
                return 0;
            }

            return min($freeQuota, $this->sumFreeUnitsInWeek(
                $nationalId,
                $userId,
                $serviceCatalogId,
                $referenceDate,
                $excludeBookingId,
            ));
        }

        if (!$rule['free_sessions_eligible'] || $rule['weekly_free_sessions'] <= 0) {
            return 0;
        }

        return $this->sumFreeUnitsInWeek(
            $nationalId,
            $userId,
            $serviceCatalogId,
            $referenceDate,
            $excludeBookingId,
            $veteranKey,
        );
    }

    public function usedServiceSessionsInWeek(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): int {
        $rule = $this->serviceDiscountRule($veteranKey, $serviceCatalogId);
        if (!$rule['use_tiered_discount']) {
            return 0;
        }

        return $this->usedGroupSessionsInWeek(
            $veteranKey,
            $nationalId,
            $userId,
            $serviceCatalogId,
            $referenceDate,
            $excludeBookingId,
        );
    }

    /**
     * @param  array<int, string>  $veteranKeys
     * @return array<string, int>
     */
    public function weeklyConsumedPerGroupForService(
        array $veteranKeys,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): array {
        $usage = [];
        foreach ($this->normalizeVeteranTypes($veteranKeys) as $key) {
            $usage[$key] = $this->usedGroupSessionsInWeek(
                $key,
                $nationalId,
                $userId,
                $serviceCatalogId,
                $referenceDate,
                $excludeBookingId,
            );
        }

        return $usage;
    }

    public function usedGroupSessionsInWeek(
        ?string $groupKey,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): int {
        $groupKey = $this->normalizeKey($groupKey);
        if (!$groupKey) {
            return 0;
        }

        [$weekStart, $weekEnd] = $this->weekBoundsFor($referenceDate);
        $used = 0;

        foreach ($this->bookingsInWeek($nationalId, $userId, $weekStart, $weekEnd, $excludeBookingId) as $booking) {
            foreach ($booking->services as $service) {
                if ((int) $service->service_catalog_id !== $serviceCatalogId) {
                    continue;
                }

                $usage = $service->veteran_group_usage;
                if (is_array($usage) && array_key_exists($groupKey, $usage)) {
                    $used += (int) $usage[$groupKey];
                    continue;
                }

                if ($booking->secondary_veteran_type_applied) {
                    continue;
                }

                if ($this->normalizeKey($booking->veteran_type_applied) === $groupKey) {
                    $used += (int) $service->quantity;
                }
            }
        }

        return $used;
    }

    public function usedGroupFreeSessionsInWeek(
        ?string $groupKey,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): int {
        $groupKey = $this->normalizeKey($groupKey);
        if (!$groupKey) {
            return 0;
        }

        $rule = $this->serviceDiscountRule($groupKey, $serviceCatalogId);
        $freeQuota = $rule['use_tiered_discount']
            ? ServiceDiscountTierEngine::freeTierQuota($rule['discount_tiers'])
            : (($rule['free_sessions_eligible'] ?? false) ? (int) $rule['weekly_free_sessions'] : 0);

        if ($freeQuota <= 0) {
            return 0;
        }

        [$weekStart, $weekEnd] = $this->weekBoundsFor($referenceDate);
        $used = 0;

        foreach ($this->bookingsInWeek($nationalId, $userId, $weekStart, $weekEnd, $excludeBookingId) as $booking) {
            foreach ($booking->services as $service) {
                if ((int) $service->service_catalog_id !== $serviceCatalogId) {
                    continue;
                }

                $usage = $service->veteran_group_usage;
                if (is_array($usage) && array_key_exists($groupKey, $usage)) {
                    $used += min((int) $usage[$groupKey], max(0, (int) $service->free_units));
                    continue;
                }

                if ($booking->secondary_veteran_type_applied) {
                    continue;
                }

                if ($this->normalizeKey($booking->veteran_type_applied) === $groupKey) {
                    $used += $this->inferFreeUnitsFromService($service, $groupKey);
                }
            }
        }

        return min($freeQuota, $used);
    }

    private function sumFreeUnitsInWeek(
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
        ?string $veteranKey = null,
    ): int {
        [$weekStart, $weekEnd] = $this->weekBoundsFor($referenceDate);
        $used = 0;

        foreach ($this->bookingsInWeek($nationalId, $userId, $weekStart, $weekEnd, $excludeBookingId) as $booking) {
            foreach ($booking->services as $service) {
                if ((int) $service->service_catalog_id !== $serviceCatalogId) {
                    continue;
                }

                $used += $this->inferFreeUnitsFromService($service, $veteranKey ?? $booking->veteran_type_applied);
            }
        }

        return $used;
    }

    /**
     * @param  array<int, string>  $veteranKeys
     * @return array<string, array{used:int, quota:int, remaining:int, name:string, group_key:string}>
     */
    public function weeklyFreeUsageByServiceForTypes(
        array $veteranKeys,
        ?string $nationalId,
        ?int $userId,
        ?string $referenceDate = null,
        ?int $excludeBookingId = null,
    ): array {
        $referenceDate ??= now()->format('Y-m-d');
        $usage = [];

        foreach ($this->activeServices()->where('supports_free_sessions', true) as $service) {
            $combinedQuota = 0;
            $combinedUsed = 0;
            $groupDetails = [];

            foreach ($this->normalizeVeteranTypes($veteranKeys) as $groupKey) {
                $rule = $this->serviceDiscountRule($groupKey, $service->id);
                $freeQuota = $rule['use_tiered_discount']
                    ? ServiceDiscountTierEngine::freeTierQuota($rule['discount_tiers'])
                    : (($rule['free_sessions_eligible'] ?? false) ? (int) $rule['weekly_free_sessions'] : 0);

                if ($freeQuota <= 0) {
                    continue;
                }

                $used = $this->usedGroupFreeSessionsInWeek(
                    $groupKey,
                    $nationalId,
                    $userId,
                    $service->id,
                    $referenceDate,
                    $excludeBookingId,
                );

                $combinedQuota += $freeQuota;
                $combinedUsed += $used;
                $groupDetails[] = [
                    'group_key' => $groupKey,
                    'label'     => $this->groupByKey($groupKey)?->label ?? $groupKey,
                    'used'      => $used,
                    'quota'     => $freeQuota,
                    'remaining' => max(0, $freeQuota - $used),
                ];
            }

            if ($combinedQuota <= 0) {
                continue;
            }

            $usage[$service->key] = [
                'name'          => $service->name,
                'used'          => $combinedUsed,
                'quota'         => $combinedQuota,
                'remaining'     => max(0, $combinedQuota - $combinedUsed),
                'group_details' => $groupDetails,
            ];
        }

        return $usage;
    }

    /**
     * @return array<string, array{used:int, quota:int, remaining:int, name:string}>
     */
    public function weeklyFreeUsageByService(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        ?string $referenceDate = null,
        ?int $excludeBookingId = null,
    ): array {
        return $this->weeklyFreeUsageByServiceForTypes(
            $veteranKey ? [$veteranKey] : [],
            $nationalId,
            $userId,
            $referenceDate,
            $excludeBookingId,
        );
    }

    /** @return array{0:Carbon,1:Carbon} */
    public function weekBoundsFor(string $referenceDate): array
    {
        $start = Carbon::parse($referenceDate)->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        return [$start, $end];
    }

    public function clearCache(?int $accommodationId = null): void
    {
        if ($accommodationId !== null) {
            Cache::forget($this->cacheKey($accommodationId));

            return;
        }

        if ($this->accommodationId !== null) {
            Cache::forget($this->cacheKey($this->accommodationId));

            return;
        }

        Cache::flush();
    }

    public function optionsForUi(): array
    {
        $options = ['' => ['label' => 'بدون تخفیف (عادی)', 'discount' => 0]];

        $groups = $this->activeGroups();
        if ($groups->isEmpty()) {
            foreach ($this->defaultGroupDefinitions() as $def) {
                $options[$def['key']] = [
                    'label'    => $def['label'],
                    'discount' => $def['accommodation_discount'],
                ];
            }

            return $options;
        }

        foreach ($groups as $group) {
            $options[$group->key] = [
                'label'    => $group->label,
                'discount' => $group->accommodation_discount,
            ];
        }

        return $options;
    }

    /** @return array<int, array<string, mixed>> */
    private function defaultGroupDefinitions(): array
    {
        return app(VeteranPolicyProvisioner::class)->groupDefinitions();
    }

    private function usedNightsInPeriod(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        int $periodMonths,
        ?int $excludeBookingId = null,
    ): int {
        return (int) $this->bookingsQuery($veteranKey, $nationalId, $userId, $excludeBookingId)
            ->where('check_in', '>=', now()->subMonths($periodMonths))
            ->get()
            ->sum(fn (Booking $booking) => $this->accommodationNightsForGroup($booking, $veteranKey));
    }

    private function usedNightsTotal(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        ?int $excludeBookingId = null,
    ): int {
        return (int) $this->bookingsQuery($veteranKey, $nationalId, $userId, $excludeBookingId)
            ->get()
            ->sum(fn (Booking $booking) => $this->accommodationNightsForGroup($booking, $veteranKey));
    }

    private function accommodationNightsForGroup(Booking $booking, ?string $groupKey): int
    {
        $groupKey = $this->normalizeKey($groupKey);
        if (!$groupKey) {
            return 0;
        }

        $usage = $booking->veteran_accommodation_group_usage;
        if (is_array($usage)) {
            return max(0, (int) ($usage[$groupKey] ?? 0));
        }

        if ($this->bookingMatchesVeteranGroup($booking, $groupKey)) {
            return max(0, (int) $booking->nights);
        }

        return 0;
    }

    /**
     * @return Collection<int, Booking>
     */
    private function bookingsInWeek(
        ?string $nationalId,
        ?int $userId,
        Carbon $weekStart,
        Carbon $weekEnd,
        ?int $excludeBookingId = null,
    ): Collection {
        $query = Booking::query()
            ->with('services')
            ->where('booking_source', 'manual')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('check_in', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        if ($this->accommodationId !== null) {
            $query->where('accommodation_id', $this->accommodationId);
        }

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($nationalId) {
            $query->where(function ($q) use ($nationalId, $userId) {
                $q->whereHas('guestDetails', fn ($gd) => $gd->where('national_id', $nationalId));
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            });
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } else {
            return collect();
        }

        return $query->get();
    }

    private function inferFreeUnitsFromService(BookingService $service, ?string $veteranKey): int
    {
        if ($service->quantity <= 0) {
            return 0;
        }

        $rule = $this->serviceDiscountRule($veteranKey, $service->service_catalog_id);
        if ($rule['use_tiered_discount']) {
            return min($service->quantity, max(0, (int) $service->free_units));
        }

        if (!$rule['free_sessions_eligible']) {
            return 0;
        }

        if ($service->free_units > 0) {
            return min($service->quantity, $service->free_units);
        }

        if ($service->unit_price <= 0) {
            return 0;
        }

        return min(
            $service->quantity,
            (int) floor($service->discount_amount / $service->unit_price),
        );
    }

    private function bookingsQuery(?string $veteranKey, ?string $nationalId, ?int $userId, ?int $excludeBookingId)
    {
        $normalized = $this->normalizeKey($veteranKey);
        $legacyKeys = array_keys(array_filter(self::LEGACY_KEY_MAP, fn ($v) => $v === $normalized));
        $keys = array_unique(array_merge([$normalized], $legacyKeys));

        $query = Booking::query()
            ->where('booking_source', 'manual')
            ->where(function ($q) use ($keys) {
                $q->whereIn('veteran_type_applied', $keys)
                    ->orWhereIn('secondary_veteran_type_applied', $keys);
            })
            ->where('status', '!=', 'cancelled');

        if ($this->accommodationId !== null) {
            $query->where('accommodation_id', $this->accommodationId);
        }

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        if ($nationalId) {
            $query->where(function ($q) use ($nationalId, $userId) {
                $q->whereHas('guestDetails', fn ($gd) => $gd->where('national_id', $nationalId));
                if ($userId) {
                    $q->orWhere('user_id', $userId);
                }
            });
        } elseif ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    private function bookingMatchesVeteranGroup(Booking $booking, ?string $groupKey): bool
    {
        $groupKey = $this->normalizeKey($groupKey);
        if (!$groupKey) {
            return false;
        }

        $primary = $this->normalizeKey($booking->veteran_type_applied);
        $secondary = $this->normalizeKey($booking->secondary_veteran_type_applied);

        return $primary === $groupKey || $secondary === $groupKey;
    }

    private function usageResult(
        bool $allowed,
        ?string $message,
        int $totalQuota,
        int $usedInPeriod,
        int $remainingPeriod,
        int $remainingTotal,
        int $requestedNights,
        int $discountedNights,
    ): array {
        return [
            'allowed'           => $allowed,
            'message'           => $message,
            'total_quota'       => $totalQuota,
            'used_in_period'    => $usedInPeriod,
            'remaining_period'  => $remainingPeriod,
            'remaining_total'   => $remainingTotal,
            'requested_nights'  => $requestedNights,
            'discounted_nights' => $discountedNights,
        ];
    }

    /** @return array<string, mixed> */
    private function cached(): array
    {
        if ($this->accommodationId === null) {
            return $this->emptyCacheStructure();
        }

        try {
            return Cache::remember($this->cacheKey($this->accommodationId), self::CACHE_TTL, function () {
                $groups = VeteranGroup::query()
                    ->forAccommodation($this->accommodationId)
                    ->active()
                    ->ordered()
                    ->get();
                $services = ServiceCatalog::query()
                    ->forAccommodation($this->accommodationId)
                    ->active()
                    ->ordered()
                    ->with(['variants' => fn ($q) => $q->active()->ordered()])
                    ->get();

                if ($groups->isEmpty()) {
                    return $this->emptyCacheStructure();
                }

                $groupIds = $groups->pluck('id');
                $matrixRows = VeteranGroupServiceDiscount::query()
                    ->whereIn('veteran_group_id', $groupIds)
                    ->get();
                $matrix = [];
                foreach ($matrixRows as $row) {
                    $matrix[$row->veteran_group_id][$row->service_catalog_id] = $row;
                }

                return [
                    'groups'          => $groups,
                    'services'        => $services,
                    'groups_by_key'   => $groups->keyBy('key')->all(),
                    'services_by_id'  => $services->keyBy('id')->all(),
                    'discount_matrix' => $matrix,
                ];
            });
        } catch (\Throwable) {
            return $this->emptyCacheStructure();
        }
    }

    private function cacheKey(int $accommodationId): string
    {
        return self::CACHE_PREFIX . ':' . $accommodationId;
    }

    /** @return array<string, mixed> */
    private function emptyCacheStructure(): array
    {
        return [
            'groups'          => collect(),
            'services'        => collect(),
            'groups_by_key'   => [],
            'services_by_id'  => [],
            'discount_matrix' => [],
        ];
    }
}
