<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\ServiceCatalog;
use App\Models\VeteranGroup;
use App\Models\VeteranGroupServiceDiscount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class VeteranPolicyService
{
    private const CACHE_KEY = 'veteran_policy_data';
    private const CACHE_TTL = 300;

    /** @var array<string, string> Legacy keys from older bookings/users */
    public const LEGACY_KEY_MAP = [
        'veteran_70_plus'       => 'veteran_70_spouses',
        'veteran_50_69'         => 'veteran_50_69_dependents',
        'veteran_25_49'         => 'veteran_25_49_dependents',
        'martyr_family'         => 'martyr_spouse_dependents',
        'freed_prisoner_family' => 'freed_prisoner_dependents',
    ];

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
     *   service_catalog_id:?int
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

        return [
            'discount_percentage'    => $pct,
            'free_sessions_eligible' => (bool) ($rule?->free_sessions_eligible ?? false),
            'min_discount'           => $min,
            'max_discount'           => $max,
            'service_catalog_id'     => $service->id,
            'weekly_free_sessions'   => (int) ($rule?->weekly_free_sessions ?? 0),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @return array<int, array<string, mixed>>
     */
    public function enrichServicesWithDiscounts(?string $veteranKey, array $services): array
    {
        return array_map(function (array $service) use ($veteranKey) {
            $catalogId = isset($service['service_catalog_id']) ? (int) $service['service_catalog_id'] : null;
            $override = isset($service['discount_override']) && $service['discount_override'] !== ''
                ? (int) $service['discount_override']
                : null;

            $rule = $this->serviceDiscountRule($veteranKey, $catalogId, $override);
            $service['discount_percentage'] = $rule['discount_percentage'];
            $service['free_sessions_eligible'] = $rule['free_sessions_eligible'];
            $service['weekly_free_sessions'] = $rule['weekly_free_sessions'] ?? 0;

            return $service;
        }, $services);
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

    public function usageSummary(
        ?string $veteranKey,
        int $guests,
        ?string $nationalId = null,
        ?int $userId = null,
        ?string $referenceDate = null,
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

    /**
     * Free sport sessions already used in the calendar week containing $referenceDate.
     * Quota is tracked per service catalog (pool, gym, hall each have their own cap).
     */
    public function usedFreeSessionsInWeek(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        int $serviceCatalogId,
        string $referenceDate,
        ?int $excludeBookingId = null,
    ): int {
        $rule = $this->serviceDiscountRule($veteranKey, $serviceCatalogId);
        if (!$rule['free_sessions_eligible'] || $rule['weekly_free_sessions'] <= 0) {
            return 0;
        }

        [$weekStart, $weekEnd] = $this->weekBoundsFor($referenceDate);
        $used = 0;

        foreach ($this->bookingsInWeek($nationalId, $userId, $weekStart, $weekEnd, $excludeBookingId) as $booking) {
            foreach ($booking->services as $service) {
                if ((int) $service->service_catalog_id !== $serviceCatalogId) {
                    continue;
                }
                $used += $this->inferFreeUnitsFromService($service, $booking->veteran_type_applied);
            }
        }

        return $used;
    }

    /**
     * @return array<string, array{used:int, quota:int, remaining:int}>
     */
    public function weeklyFreeUsageByService(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        ?string $referenceDate = null,
        ?int $excludeBookingId = null,
    ): array {
        $referenceDate ??= now()->format('Y-m-d');
        $usage = [];

        foreach ($this->activeServices()->where('supports_free_sessions', true) as $service) {
            $rule = $this->serviceDiscountRule($veteranKey, $service->id);
            if (!$rule['free_sessions_eligible'] || $rule['weekly_free_sessions'] <= 0) {
                continue;
            }

            $quota = $rule['weekly_free_sessions'];
            $used = $this->usedFreeSessionsInWeek(
                $veteranKey,
                $nationalId,
                $userId,
                $service->id,
                $referenceDate,
                $excludeBookingId,
            );

            $usage[$service->key] = [
                'name'      => $service->name,
                'used'      => $used,
                'quota'     => $quota,
                'remaining' => max(0, $quota - $used),
            ];
        }

        return $usage;
    }

    /** @return array{0:Carbon,1:Carbon} */
    public function weekBoundsFor(string $referenceDate): array
    {
        $start = Carbon::parse($referenceDate)->startOfWeek(Carbon::SATURDAY)->startOfDay();
        $end = $start->copy()->addDays(6)->endOfDay();

        return [$start, $end];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
        return [
            ['key' => 'veteran_70_spouses', 'label' => 'جانبازان ۷۰ درصد و همسران', 'accommodation_discount' => 70],
            ['key' => 'veteran_50_69_dependents', 'label' => 'جانبازان ۵۰ الی ۶۹ درصد به همراه افراد تحت تکفل', 'accommodation_discount' => 50],
            ['key' => 'veteran_25_49_dependents', 'label' => 'جانبازان ۵ الی ۴۹ درصد به همراه افراد تحت تکفل', 'accommodation_discount' => 40],
            ['key' => 'martyr_children', 'label' => 'فرزندان شهدا و فرزندان جانبازان ۷۰ درصد', 'accommodation_discount' => 50],
            ['key' => 'martyr_parents_dependents', 'label' => 'والدین شهدا به همراه افراد تحت تکفل', 'accommodation_discount' => 70],
            ['key' => 'martyr_spouse_dependents', 'label' => 'همسر شهید به همراه افراد تحت تکفل', 'accommodation_discount' => 50],
            ['key' => 'freed_prisoner_dependents', 'label' => 'آزادگان سرافراز به همراه افراد تحت تکفل', 'accommodation_discount' => 50],
        ];
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
            ->sum('nights');
    }

    private function usedNightsTotal(
        ?string $veteranKey,
        ?string $nationalId,
        ?int $userId,
        ?int $excludeBookingId = null,
    ): int {
        return (int) $this->bookingsQuery($veteranKey, $nationalId, $userId, $excludeBookingId)
            ->sum('nights');
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
            ->whereIn('veteran_type_applied', $keys)
            ->where('status', '!=', 'cancelled');

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
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $groups = VeteranGroup::active()->ordered()->get();
                $services = ServiceCatalog::active()->ordered()->get();

                if ($groups->isEmpty()) {
                    return $this->emptyCacheStructure();
                }

                $matrixRows = VeteranGroupServiceDiscount::all();
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
