<?php

namespace App\Support;

use App\Models\Accommodation;
use Illuminate\Database\Eloquent\Builder;

class HostUserFilter
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>  $accommodationIds
     */
    public function __construct(
        private array $filters = [],
        private array $accommodationIds = [],
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>  $accommodationIds
     */
    public static function make(array $filters, array $accommodationIds): self
    {
        return new self($filters, $accommodationIds);
    }

    public function apply(Builder $query): Builder
    {
        $scopedAccommodationIds = $this->scopedAccommodationIds();
        $provinceIds = $this->scopedProvinceIds();

        $query->where(function (Builder $scope) use ($scopedAccommodationIds, $provinceIds) {
            $hasScope = false;

            if ($scopedAccommodationIds !== []) {
                $scope->whereHas(
                    'bookings',
                    fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds),
                );
                $hasScope = true;
            }

            if ($provinceIds !== []) {
                $provinceMatcher = function (Builder $provinceScope) use ($provinceIds) {
                    $provinceScope
                        ->whereIn('province_id', $provinceIds)
                        ->orWhereHas(
                            'programEmployer',
                            fn (Builder $q) => $q->whereIn('province_id', $provinceIds),
                        )
                        ->orWhereHas(
                            'programBeneficiary',
                            fn (Builder $q) => $q->whereIn('province_id', $provinceIds),
                        );
                };

                if ($hasScope) {
                    $scope->orWhere($provinceMatcher);
                } else {
                    $scope->where($provinceMatcher);
                }
            }

            if (!$hasScope && $provinceIds === []) {
                $scope->whereRaw('0 = 1');
            }
        })
            ->with([
                'country',
                'residenceCity',
                'roles',
                'programEmployer.province',
                'programBeneficiary.province',
            ])
            ->withCount([
                'bookings as host_bookings_count' => fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds),
            ])
            ->withMax([
                'bookings as last_booking_at' => fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds),
            ], 'created_at');

        if (!empty($this->filters['search'])) {
            $s = trim((string) $this->filters['search']);
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")
                ->orWhere('national_id', 'like', "%{$s}%")
                ->orWhere('passport_number', 'like', "%{$s}%")
                ->orWhere('personnel_code', 'like', "%{$s}%")
                ->orWhereHas('programEmployer', fn (Builder $employer) => $employer
                    ->where('employer_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%"))
                ->orWhereHas('programBeneficiary', fn (Builder $beneficiary) => $beneficiary
                    ->where('beneficiary_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")));
        }

        if (!empty($this->filters['user_type'])) {
            UserRoleQueryFilter::apply($query, (string) $this->filters['user_type']);
        }

        if (!empty($this->filters['province_id'])) {
            $provinceId = (int) $this->filters['province_id'];
            $allowedProvinceIds = $this->allAccessibleProvinceIds();

            if (in_array($provinceId, $allowedProvinceIds, true)) {
                $query->where(function (Builder $w) use ($provinceId) {
                    $w->where('province_id', $provinceId)
                        ->orWhereHas(
                            'programEmployer',
                            fn (Builder $q) => $q->where('province_id', $provinceId),
                        )
                        ->orWhereHas(
                            'programBeneficiary',
                            fn (Builder $q) => $q->where('province_id', $provinceId),
                        );
                });
            }
        }

        if (array_key_exists('veteran_type', $this->filters)) {
            $veteranType = (string) ($this->filters['veteran_type'] ?? '');
            if ($veteranType === '__none__') {
                $query->where(fn (Builder $w) => $w
                    ->where(fn (Builder $inner) => $inner->whereNull('veteran_type')->orWhere('veteran_type', ''))
                    ->where(fn (Builder $inner) => $inner->whereNull('secondary_veteran_type')->orWhere('secondary_veteran_type', '')));
            } elseif ($veteranType !== '') {
                $query->where(fn (Builder $w) => $w
                    ->where('veteran_type', $veteranType)
                    ->orWhere('secondary_veteran_type', $veteranType));
            }
        }

        if (isset($this->filters['bookings_min']) && $this->filters['bookings_min'] !== '') {
            $min = (int) $this->filters['bookings_min'];
            $query->having('host_bookings_count', '>=', $min);
        }

        if (array_key_exists('has_bookings', $this->filters)) {
            $hasBookings = (string) ($this->filters['has_bookings'] ?? '');
            if ($hasBookings === '1') {
                $query->whereHas(
                    'bookings',
                    fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds),
                );
            } elseif ($hasBookings === '0') {
                $query->whereDoesntHave(
                    'bookings',
                    fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds),
                );
            }
        }

        return $this->applySort($query);
    }

    private function applySort(Builder $query): Builder
    {
        return match ((string) ($this->filters['sort'] ?? 'last_booking')) {
            'name' => $query->orderBy('name')->orderBy('id'),
            'bookings' => $query->orderByDesc('host_bookings_count')->orderByDesc('last_booking_at'),
            default => $query->orderByDesc('last_booking_at')->orderBy('name'),
        };
    }

    public function hasActiveFilters(): bool
    {
        foreach ($this->normalizedFilters() as $value) {
            if ($value !== '' && $value !== null && $value !== false) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function normalizedFilters(): array
    {
        return [
            'search'           => $this->filters['search'] ?? '',
            'user_type'        => $this->filters['user_type'] ?? '',
            'province_id'      => $this->filters['province_id'] ?? '',
            'veteran_type'     => $this->filters['veteran_type'] ?? '',
            'accommodation_id' => $this->filters['accommodation_id'] ?? '',
            'bookings_min'     => $this->filters['bookings_min'] ?? '',
            'has_bookings'     => $this->filters['has_bookings'] ?? '',
            'sort'             => $this->filters['sort'] ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function exportQuery(): array
    {
        return array_filter(
            $this->normalizedFilters(),
            fn ($v) => $v !== '' && $v !== null && $v !== false,
        );
    }

    /** @return array<int> */
    private function scopedAccommodationIds(): array
    {
        if (!empty($this->filters['accommodation_id'])) {
            $id = (int) $this->filters['accommodation_id'];
            if (in_array($id, $this->accommodationIds, true)) {
                return [$id];
            }
        }

        return $this->accommodationIds;
    }

    /** @return array<int> */
    private function scopedProvinceIds(): array
    {
        return self::resolveAccountingProvinceIds($this->scopedAccommodationIds());
    }

    /** @return array<int> */
    private function allAccessibleProvinceIds(): array
    {
        return self::resolveAccountingProvinceIds($this->accommodationIds);
    }

    /** @param  array<int>  $accommodationIds
     * @return array<int>
     */
    public static function resolveAccountingProvinceIds(array $accommodationIds): array
    {
        if ($accommodationIds === []) {
            return [];
        }

        return Accommodation::query()
            ->whereIn('id', $accommodationIds)
            ->with(['city.province', 'county.province'])
            ->get()
            ->map(fn (Accommodation $accommodation) => $accommodation->resolvedProvince()?->id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function userIsInScope(\App\Models\User $target, array $accommodationIds): bool
    {
        if ($target->isAdmin()) {
            return false;
        }

        if ($accommodationIds === []) {
            return false;
        }

        $query = \App\Models\User::query()->whereKey($target->id);

        return self::make([], $accommodationIds)->apply($query)->exists();
    }
}
