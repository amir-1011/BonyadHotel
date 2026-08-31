<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class AdminUserFilter
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(private array $filters = []) {}

    /** @param  array<string, mixed>  $filters */
    public static function make(array $filters): self
    {
        return new self($filters);
    }

    public function apply(Builder $query, bool $withSort = true): Builder
    {
        if (!empty($this->filters['search'])) {
            $s = trim((string) $this->filters['search']);
            $query->where(fn (Builder $w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhere('mobile', 'like', "%{$s}%")
                ->orWhere('national_id', 'like', "%{$s}%")
                ->orWhere('personnel_code', 'like', "%{$s}%")
                ->orWhereHas('programEmployer', fn (Builder $employer) => $employer
                    ->where('employer_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%"))
                ->orWhereHas('programBeneficiary', fn (Builder $beneficiary) => $beneficiary
                    ->where('beneficiary_code', 'like', "%{$s}%")
                    ->orWhere('name', 'like', "%{$s}%")));
        }

        if (!empty($this->filters['role'])) {
            UserRoleQueryFilter::apply($query, (string) $this->filters['role']);
        }

        if ($withSort) {
            $query->latest();
        }

        return $query;
    }

    public function hasActiveFilters(): bool
    {
        foreach ($this->normalizedFilters() as $value) {
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function normalizedFilters(): array
    {
        return [
            'search' => $this->filters['search'] ?? '',
            'role'   => $this->filters['role'] ?? '',
        ];
    }

    /** @return array<string, mixed> */
    public function exportQuery(): array
    {
        return array_filter(
            $this->normalizedFilters(),
            fn ($v) => $v !== '' && $v !== null,
        );
    }
}
