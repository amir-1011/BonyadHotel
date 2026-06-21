<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

class PlatformCommissionEntryFilter
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(private array $filters = []) {}

    /** @param  array<string, mixed>  $filters */
    public static function make(array $filters): self
    {
        return new self($filters);
    }

    public function apply(Builder $query): Builder
    {
        if (!empty($this->filters['search'])) {
            $s = trim((string) $this->filters['search']);
            $query->where(function ($q) use ($s) {
                $term = '%' . $s . '%';
                if (ctype_digit($s)) {
                    $q->where('id', (int) $s)
                        ->orWhere('booking_id', (int) $s);
                }
                $q->orWhere('service_name', 'like', $term)
                    ->orWhere('category_key', 'like', $term)
                    ->orWhere('meta->booker_name', 'like', $term)
                    ->orWhere('meta->booker_mobile', 'like', $term)
                    ->orWhere('meta->tracking_code', 'like', $term)
                    ->orWhere('meta->accommodation_name', 'like', $term)
                    ->orWhereHas('booking', function ($b) use ($term) {
                        $b->where('tracking_code', 'like', $term)
                            ->orWhere('guest_contact_name', 'like', $term)
                            ->orWhere('guest_contact_mobile', 'like', $term)
                            ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('mobile', 'like', $term));
                    })
                    ->orWhereHas('accommodation', fn ($a) => $a->where('name', 'like', $term));
            });
        }

        if (!empty($this->filters['category'])) {
            $query->where('category', $this->filters['category']);
        }

        if (!empty($this->filters['entry_type'])) {
            $query->where('entry_type', $this->filters['entry_type']);
        }

        if (!empty($this->filters['reason'])) {
            $query->where('reason', $this->filters['reason']);
        }

        if (!empty($this->filters['accommodation_id'])) {
            $query->where('accommodation_id', (int) $this->filters['accommodation_id']);
        }

        if (!empty($this->filters['city_id'])) {
            $query->whereHas('accommodation', fn ($q) => $q->where('city_id', (int) $this->filters['city_id']));
        }

        if (!empty($this->filters['service_catalog_id'])) {
            $query->where('service_catalog_id', (int) $this->filters['service_catalog_id']);
        }

        if (!empty($this->filters['booking_source'])) {
            $source = $this->filters['booking_source'];
            $query->where(function ($q) use ($source) {
                $q->where('meta->booking_source', $source)
                    ->orWhereHas('booking', fn ($b) => $b->where('booking_source', $source));
            });
        }

        if (!empty($this->filters['booking_status'])) {
            $status = $this->filters['booking_status'];
            $query->whereHas('booking', fn ($b) => $b->where('status', $status));
        }

        if (!empty($this->filters['sign'])) {
            match ($this->filters['sign']) {
                'positive' => $query->where('commission_amount', '>', 0),
                'negative' => $query->where('commission_amount', '<', 0),
                default    => null,
            };
        }

        if (!empty($this->filters['date_from']) && ($d = $this->toGregorian($this->filters['date_from']))) {
            $query->whereDate('created_at', '>=', $d);
        }

        if (!empty($this->filters['date_to']) && ($d = $this->toGregorian($this->filters['date_to']))) {
            $query->whereDate('created_at', '<=', $d);
        }

        if (isset($this->filters['commission_min']) && $this->filters['commission_min'] !== '') {
            $query->where('commission_amount', '>=', (int) str_replace(',', '', (string) $this->filters['commission_min']));
        }

        if (isset($this->filters['commission_max']) && $this->filters['commission_max'] !== '') {
            $query->where('commission_amount', '<=', (int) str_replace(',', '', (string) $this->filters['commission_max']));
        }

        if (isset($this->filters['transaction_min']) && $this->filters['transaction_min'] !== '') {
            $query->where('transaction_amount', '>=', (int) str_replace(',', '', (string) $this->filters['transaction_min']));
        }

        if (isset($this->filters['transaction_max']) && $this->filters['transaction_max'] !== '') {
            $query->where('transaction_amount', '<=', (int) str_replace(',', '', (string) $this->filters['transaction_max']));
        }

        $sortable = ['id', 'created_at', 'transaction_amount', 'commission_amount', 'category', 'entry_type'];
        $sort = in_array($this->filters['sort'] ?? '', $sortable, true) ? $this->filters['sort'] : 'id';
        $dir = ($this->filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

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
            'search'              => $this->filters['search'] ?? '',
            'category'            => $this->filters['category'] ?? '',
            'entry_type'          => $this->filters['entry_type'] ?? '',
            'reason'              => $this->filters['reason'] ?? '',
            'accommodation_id'    => $this->filters['accommodation_id'] ?? '',
            'city_id'             => $this->filters['city_id'] ?? '',
            'service_catalog_id'  => $this->filters['service_catalog_id'] ?? '',
            'booking_source'      => $this->filters['booking_source'] ?? '',
            'booking_status'      => $this->filters['booking_status'] ?? '',
            'sign'                => $this->filters['sign'] ?? '',
            'date_from'           => $this->filters['date_from'] ?? '',
            'date_to'             => $this->filters['date_to'] ?? '',
            'commission_min'      => $this->filters['commission_min'] ?? '',
            'commission_max'      => $this->filters['commission_max'] ?? '',
            'transaction_min'     => $this->filters['transaction_min'] ?? '',
            'transaction_max'     => $this->filters['transaction_max'] ?? '',
            'sort'                => $this->filters['sort'] ?? 'id',
            'dir'                 => $this->filters['dir'] ?? 'desc',
        ];
    }

    /** @return array<string, string> */
    public function exportQuery(): array
    {
        return array_filter(
            $this->normalizedFilters(),
            fn ($v) => $v !== '' && $v !== null && $v !== false
        );
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
