<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

class AdminBookingFilter
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>|null  $scopedAccommodationIds
     */
    public function __construct(
        private array $filters = [],
        private ?array $scopedAccommodationIds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<int>|null  $scopedAccommodationIds
     */
    public static function make(array $filters, ?array $scopedAccommodationIds = null): self
    {
        return new self($filters, $scopedAccommodationIds);
    }

    public function apply(Builder $query, bool $withSort = true): Builder
    {
        if ($this->scopedAccommodationIds !== null) {
            $query->whereIn('accommodation_id', $this->scopedAccommodationIds);
        }

        if (!empty($this->filters['search'])) {
            $s = trim((string) $this->filters['search']);
            $query->where(function ($w) use ($s) {
                $w->where('tracking_code', 'like', "%{$s}%")
                    ->orWhere('guest_contact_name', 'like', "%{$s}%")
                    ->orWhere('guest_contact_mobile', 'like', "%{$s}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('mobile', 'like', "%{$s}%"))
                    ->orWhereHas('accommodation', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['accommodation_id'])) {
            $accommodationId = (int) $this->filters['accommodation_id'];
            if ($this->scopedAccommodationIds === null || in_array($accommodationId, $this->scopedAccommodationIds, true)) {
                $query->where('accommodation_id', $accommodationId);
            }
        }

        if (!empty($this->filters['host_id'])) {
            $hostId = (int) $this->filters['host_id'];
            $query->whereHas('accommodation.hosts', fn ($q) => $q->where('users.id', $hostId));
        }

        if (!empty($this->filters['reserver_id'])) {
            $reserverId = (int) $this->filters['reserver_id'];
            $query->withReserver()->where(function ($w) use ($reserverId) {
                $w->where('created_by', $reserverId)
                    ->orWhere(function ($q) use ($reserverId) {
                        $q->whereNull('created_by')->where('user_id', $reserverId);
                    });
            });
        }

        if (!empty($this->filters['province_id'])) {
            $query->whereHas('accommodation.city', fn ($q) => $q->where('province_id', (int) $this->filters['province_id']));
        }

        if (!empty($this->filters['city_id'])) {
            $query->whereHas('accommodation', fn ($q) => $q->where('city_id', (int) $this->filters['city_id']));
        }

        if (!empty($this->filters['county_id'])) {
            $query->whereHas('accommodation', fn ($q) => $q->where('county_id', (int) $this->filters['county_id']));
        }

        if (!empty($this->filters['service_catalog_variant_id'])) {
            $variantId = (int) $this->filters['service_catalog_variant_id'];
            $query->whereHas('services', function ($q) use ($variantId) {
                $q->where('service_catalog_variant_id', $variantId);
                if (!empty($this->filters['service_catalog_id'])) {
                    $q->where('service_catalog_id', (int) $this->filters['service_catalog_id']);
                }
            });
        } elseif (!empty($this->filters['service_catalog_id'])) {
            $catalogId = (int) $this->filters['service_catalog_id'];
            $query->whereHas('services', fn ($q) => $q->where('service_catalog_id', $catalogId));
        }

        if (!empty($this->filters['check_in_from']) && ($d = $this->toGregorian((string) $this->filters['check_in_from']))) {
            $query->whereDate('check_in', '>=', $d);
        }

        if (!empty($this->filters['check_in_to']) && ($d = $this->toGregorian((string) $this->filters['check_in_to']))) {
            $query->whereDate('check_in', '<=', $d);
        }

        if (!empty($this->filters['check_out_from']) && ($d = $this->toGregorian((string) $this->filters['check_out_from']))) {
            $query->whereDate('check_out', '>=', $d);
        }

        if (!empty($this->filters['check_out_to']) && ($d = $this->toGregorian((string) $this->filters['check_out_to']))) {
            $query->whereDate('check_out', '<=', $d);
        }

        if (isset($this->filters['nights_min']) && $this->filters['nights_min'] !== '') {
            $query->where('nights', '>=', (int) $this->filters['nights_min']);
        }

        if (isset($this->filters['nights_max']) && $this->filters['nights_max'] !== '') {
            $query->where('nights', '<=', (int) $this->filters['nights_max']);
        }

        if (isset($this->filters['price_min']) && $this->filters['price_min'] !== '') {
            $query->where('total_price', '>=', (int) str_replace(',', '', (string) $this->filters['price_min']));
        }

        if (isset($this->filters['price_max']) && $this->filters['price_max'] !== '') {
            $query->where('total_price', '<=', (int) str_replace(',', '', (string) $this->filters['price_max']));
        }

        if (isset($this->filters['guests_min']) && $this->filters['guests_min'] !== '') {
            $query->where('guests', '>=', (int) $this->filters['guests_min']);
        }

        if ($this->truthy($this->filters['has_discount'] ?? false)) {
            $query->where('discount_percentage', '>', 0);
        }

        $roomCategory = (string) ($this->filters['room_category'] ?? $this->filters['bed_type'] ?? '');
        if ($roomCategory !== '') {
            $query->where(function ($w) use ($roomCategory) {
                $w->whereHas('roomType', fn ($q) => $q->where('bed_type', $roomCategory))
                    ->orWhereHas('bookingRooms.roomType', fn ($q) => $q->where('bed_type', $roomCategory));
            });
        }

        if (!empty($this->filters['room_id'])) {
            $roomId = (int) $this->filters['room_id'];
            $query->whereHas('bookingRooms', fn ($q) => $q->where('room_id', $roomId));
        }

        if (array_key_exists('veteran_type', $this->filters)) {
            $veteranType = (string) ($this->filters['veteran_type'] ?? '');
            if ($veteranType === '__none__') {
                $query->where(function ($w) {
                    $w->where(function ($inner) {
                        $inner->whereNull('veteran_type_applied')->orWhere('veteran_type_applied', '');
                    })->where(function ($inner) {
                        $inner->whereDoesntHave('user')
                            ->orWhereHas('user', fn ($u) => $u->where(function ($user) {
                                $user->where(fn ($q) => $q->whereNull('veteran_type')->orWhere('veteran_type', ''))
                                    ->where(fn ($q) => $q->whereNull('secondary_veteran_type')->orWhere('secondary_veteran_type', ''));
                            }));
                    });
                });
            } elseif ($veteranType !== '') {
                $query->where(function ($w) use ($veteranType) {
                    $w->where('veteran_type_applied', $veteranType)
                        ->orWhere('secondary_veteran_type_applied', $veteranType)
                        ->orWhere(function ($q) use ($veteranType) {
                            $q->where(function ($inner) {
                                $inner->whereNull('veteran_type_applied')->orWhere('veteran_type_applied', '');
                            })->whereHas('user', fn ($u) => $u
                                ->where(fn ($user) => $user->where('veteran_type', $veteranType)
                                    ->orWhere('secondary_veteran_type', $veteranType)));
                        });
                });
            }
        }

        if (!empty($this->filters['booking_source'])) {
            $source = (string) $this->filters['booking_source'];
            if (in_array($source, ['manual', 'online', 'program'], true)) {
                $query->where('booking_source', $source);
            }
        }

        if (!empty($this->filters['program_type'])) {
            $query->whereHas('program', fn ($q) => $q->where('program_type', $this->filters['program_type']));
        }

        if (!empty($this->filters['program_payment_type'])) {
            $query->whereHas('program', fn ($q) => $q->where('payment_type', $this->filters['program_payment_type']));
        }

        if (!empty($this->filters['program_counterparty'])) {
            $term = trim((string) $this->filters['program_counterparty']);
            $query->whereHas('program', fn ($q) => $q->where('counterparty', 'like', "%{$term}%"));
        }

        if ($withSort) {
            $sortable = ['id', 'check_in', 'check_out', 'nights', 'total_price', 'guests', 'created_at'];
            $sort = in_array($this->filters['sort'] ?? '', $sortable, true) ? $this->filters['sort'] : 'created_at';
            $dir = ($this->filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir);
        }

        return $query;
    }

    public function hasActiveFilters(): bool
    {
        $filters = $this->normalizedFilters();
        unset($filters['sort'], $filters['dir']);

        foreach ($filters as $value) {
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
            'search'            => $this->filters['search'] ?? '',
            'status'            => $this->filters['status'] ?? '',
            'accommodation_id'  => $this->filters['accommodation_id'] ?? '',
            'host_id'           => $this->filters['host_id'] ?? '',
            'reserver_id'       => $this->filters['reserver_id'] ?? '',
            'province_id'       => $this->filters['province_id'] ?? '',
            'city_id'           => $this->filters['city_id'] ?? '',
            'county_id'                  => $this->filters['county_id'] ?? '',
            'service_catalog_id'         => $this->filters['service_catalog_id'] ?? '',
            'service_catalog_variant_id' => $this->filters['service_catalog_variant_id'] ?? '',
            'check_in_from'     => $this->filters['check_in_from'] ?? '',
            'check_in_to'       => $this->filters['check_in_to'] ?? '',
            'check_out_from'    => $this->filters['check_out_from'] ?? '',
            'check_out_to'      => $this->filters['check_out_to'] ?? '',
            'nights_min'        => $this->filters['nights_min'] ?? '',
            'nights_max'        => $this->filters['nights_max'] ?? '',
            'price_min'         => $this->filters['price_min'] ?? '',
            'price_max'         => $this->filters['price_max'] ?? '',
            'guests_min'        => $this->filters['guests_min'] ?? '',
            'has_discount'      => $this->truthy($this->filters['has_discount'] ?? false),
            'bed_type'          => (string) ($this->filters['room_category'] ?? $this->filters['bed_type'] ?? ''),
            'room_id'           => $this->filters['room_id'] ?? '',
            'veteran_type'      => $this->filters['veteran_type'] ?? '',
            'booking_source'    => $this->filters['booking_source'] ?? '',
            'program_type'          => $this->filters['program_type'] ?? '',
            'program_payment_type'  => $this->filters['program_payment_type'] ?? '',
            'program_counterparty'  => $this->filters['program_counterparty'] ?? '',
            'sort'              => $this->filters['sort'] ?? 'created_at',
            'dir'               => $this->filters['dir'] ?? 'desc',
        ];
    }

    /** @return array<string, mixed> */
    public function exportQuery(): array
    {
        $query = $this->normalizedFilters();

        return array_filter(
            $query,
            fn ($v, $k) => $v !== '' && $v !== null && $v !== false && !($k === 'sort' && $v === 'created_at') && !($k === 'dir' && $v === 'desc'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function truthy(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) {
            return null;
        }

        try {
            $normalized = strtr(trim($jalali), [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);

            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
