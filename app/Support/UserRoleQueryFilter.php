<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class UserRoleQueryFilter
{
    public static function apply(Builder $query, string $role): void
    {
        if ($role === 'guest') {
            $query->where(function (Builder $guestQuery) {
                $guestQuery->doesntHave('roles')
                    ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->where('name', 'guest'));
            })->whereDoesntHave('programBeneficiary')
                ->whereDoesntHave('programEmployer');

            return;
        }

        if ($role === 'beneficiary') {
            $query->whereHas('programBeneficiary');

            return;
        }

        if ($role === 'employer') {
            $query->whereHas('programEmployer');

            return;
        }

        if ($role === AdminUserRoleFilterCatalog::ALL_PERSONNEL) {
            $query->role('host');

            return;
        }

        if (str_starts_with($role, 'host_position:')) {
            $title = substr($role, strlen('host_position:'));
            if ($title === HostPositionTitles::DEFAULT_LABEL) {
                $query->role('host')->where(function (Builder $roleQuery) {
                    $roleQuery->whereNull('host_position_title')
                        ->orWhere('host_position_title', '')
                        ->orWhere('host_position_title', HostPositionTitles::DEFAULT_LABEL);
                });
            } else {
                $query->role('host')->where('host_position_title', $title);
            }

            return;
        }

        if ($role === 'host') {
            $query->role('host')->where(function (Builder $roleQuery) {
                $roleQuery->whereNull('host_position_title')
                    ->orWhere('host_position_title', '')
                    ->orWhere('host_position_title', HostPositionTitles::DEFAULT_LABEL);
            });

            return;
        }

        $query->role($role);
    }
}
