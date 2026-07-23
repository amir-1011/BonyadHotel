<?php

namespace App\Support;

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

        $query->whereHas('bookings', fn (Builder $q) => $q->whereIn('accommodation_id', $scopedAccommodationIds))
            ->with(['country', 'residenceCity'])
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
                ->orWhere('passport_number', 'like', "%{$s}%"));
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

        return $query->orderByDesc('last_booking_at');
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
            'veteran_type'     => $this->filters['veteran_type'] ?? '',
            'accommodation_id' => $this->filters['accommodation_id'] ?? '',
            'bookings_min'     => $this->filters['bookings_min'] ?? '',
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
}
