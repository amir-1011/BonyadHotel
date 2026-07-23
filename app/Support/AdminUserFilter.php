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
                ->orWhere('national_id', 'like', "%{$s}%"));
        }

        if (!empty($this->filters['role'])) {
            $role = (string) $this->filters['role'];

            if ($role === 'guest') {
                $query->where(function (Builder $guestQuery) {
                    $guestQuery->doesntHave('roles')
                        ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'guest'));
                });
            } elseif (str_starts_with($role, 'host_position:')) {
                $title = substr($role, strlen('host_position:'));
                $query->role('host')->where('host_position_title', $title);
            } elseif ($role === 'host') {
                $query->role('host')->where(function (Builder $roleQuery) {
                    $roleQuery->whereNull('host_position_title')
                        ->orWhere('host_position_title', '');
                });
            } else {
                $query->role($role);
            }
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
