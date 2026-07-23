<?php

namespace App\Support;

use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;
use Morilog\Jalali\Jalalian;

class ProgramFilter
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
                $w->where('title', 'like', "%{$s}%")
                    ->orWhere('counterparty', 'like', "%{$s}%")
                    ->orWhere('employer', 'like', "%{$s}%")
                    ->orWhere('contractor', 'like', "%{$s}%")
                    ->orWhereHas('accommodation', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('booking', fn ($q) => $q->where('tracking_code', 'like', "%{$s}%"));
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['program_type'])) {
            $query->where('program_type', $this->filters['program_type']);
        }

        if (!empty($this->filters['payment_type'])) {
            $query->where('payment_type', $this->filters['payment_type']);
        }

        if (!empty($this->filters['accommodation_id'])) {
            $accommodationId = (int) $this->filters['accommodation_id'];
            if ($this->scopedAccommodationIds === null || in_array($accommodationId, $this->scopedAccommodationIds, true)) {
                $query->where('accommodation_id', $accommodationId);
            }
        }

        if (!empty($this->filters['employer'])) {
            $query->where('employer', 'like', '%' . trim((string) $this->filters['employer']) . '%');
        }

        if (!empty($this->filters['contractor'])) {
            $query->where('contractor', 'like', '%' . trim((string) $this->filters['contractor']) . '%');
        }

        if (!empty($this->filters['counterparty'])) {
            $query->where('counterparty', 'like', '%' . trim((string) $this->filters['counterparty']) . '%');
        }

        if (!empty($this->filters['start_from']) && ($d = $this->toGregorian((string) $this->filters['start_from']))) {
            $query->whereHas('booking', fn ($q) => $q->whereDate('check_in', '>=', $d));
        }

        if (!empty($this->filters['start_to']) && ($d = $this->toGregorian((string) $this->filters['start_to']))) {
            $query->whereHas('booking', fn ($q) => $q->whereDate('check_in', '<=', $d));
        }

        if (!empty($this->filters['end_from']) && ($d = $this->toGregorian((string) $this->filters['end_from']))) {
            $query->whereHas('booking', fn ($q) => $q->whereDate('check_out', '>=', $d));
        }

        if (!empty($this->filters['end_to']) && ($d = $this->toGregorian((string) $this->filters['end_to']))) {
            $query->whereHas('booking', fn ($q) => $q->whereDate('check_out', '<=', $d));
        }

        if (isset($this->filters['guests_min']) && $this->filters['guests_min'] !== '') {
            $query->where('guest_count', '>=', (int) $this->filters['guests_min']);
        }

        if (isset($this->filters['rooms_min']) && $this->filters['rooms_min'] !== '') {
            $query->where('rooms_allocated', '>=', (int) $this->filters['rooms_min']);
        }

        if (isset($this->filters['price_min']) && $this->filters['price_min'] !== '') {
            $query->where('total_amount', '>=', (int) str_replace(',', '', (string) $this->filters['price_min']));
        }

        if (isset($this->filters['price_max']) && $this->filters['price_max'] !== '') {
            $query->where('total_amount', '<=', (int) str_replace(',', '', (string) $this->filters['price_max']));
        }

        if (!empty($this->filters['beneficiary_id'])) {
            $beneficiaryId = (int) $this->filters['beneficiary_id'];
            $query->whereHas('beneficiaryCosts', fn ($q) => $q->where('program_beneficiary_id', $beneficiaryId));
        }

        if ($withSort) {
            $sortable = ['id', 'title', 'guest_count', 'rooms_allocated', 'total_amount', 'created_at'];
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
            'search'           => $this->filters['search'] ?? '',
            'status'           => $this->filters['status'] ?? '',
            'program_type'     => $this->filters['program_type'] ?? '',
            'payment_type'     => $this->filters['payment_type'] ?? '',
            'accommodation_id' => $this->filters['accommodation_id'] ?? '',
            'employer'         => $this->filters['employer'] ?? '',
            'contractor'       => $this->filters['contractor'] ?? '',
            'counterparty'     => $this->filters['counterparty'] ?? '',
            'start_from'       => $this->filters['start_from'] ?? '',
            'start_to'         => $this->filters['start_to'] ?? '',
            'end_from'         => $this->filters['end_from'] ?? '',
            'end_to'           => $this->filters['end_to'] ?? '',
            'guests_min'       => $this->filters['guests_min'] ?? '',
            'rooms_min'        => $this->filters['rooms_min'] ?? '',
            'price_min'        => $this->filters['price_min'] ?? '',
            'price_max'        => $this->filters['price_max'] ?? '',
            'beneficiary_id'   => $this->filters['beneficiary_id'] ?? '',
            'sort'             => $this->filters['sort'] ?? 'created_at',
            'dir'              => $this->filters['dir'] ?? 'desc',
        ];
    }

    private function toGregorian(string $jalali): ?string
    {
        try {
            $normalized = strtr(trim($jalali), [
                '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
                '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            ]);

            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
