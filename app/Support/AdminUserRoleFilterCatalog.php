<?php

namespace App\Support;

use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminUserRoleFilterCatalog
{
    public const ALL_PERSONNEL = 'all_personnel';

    /**
     * Build filter options from roles that actually appear in the users table.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        if (!app()->bound('db')) {
            return [];
        }

        $options = [];

        if (self::hasGuestUsers()) {
            $options[] = ['value' => 'guest', 'label' => 'مهمان'];
        }

        if (User::query()->whereHas('programBeneficiary')->exists()) {
            $options[] = ['value' => 'beneficiary', 'label' => 'ذینفع'];
        }

        if (User::query()->whereHas('programEmployer')->exists()) {
            $options[] = ['value' => 'employer', 'label' => 'ادارات و ارگان‌ها'];
        }

        foreach (Role::query()->whereHas('users')->orderBy('name')->get() as $role) {
            if (in_array($role->name, ['host', 'guest'], true)) {
                continue;
            }

            $options[] = [
                'value' => $role->name,
                'label' => self::labelForRole($role->name),
            ];
        }

        $hostTitles = User::query()
            ->role('host')
            ->whereNotNull('host_position_title')
            ->where('host_position_title', '!=', '')
            ->distinct()
            ->orderBy('host_position_title')
            ->pluck('host_position_title');

        foreach ($hostTitles as $title) {
            if ($title === HostPositionTitles::DEFAULT_LABEL) {
                continue;
            }

            $options[] = [
                'value' => 'host_position:' . $title,
                'label' => (string) $title,
            ];
        }

        if (User::query()->role('host')->where(function ($query) {
            $query->whereNull('host_position_title')
                ->orWhere('host_position_title', '')
                ->orWhere('host_position_title', HostPositionTitles::DEFAULT_LABEL);
        })->exists()) {
            $options[] = ['value' => 'host', 'label' => HostLabels::roleLabel()];
        }

        return self::uniqueByValue($options);
    }

    /**
     * Sub-tab options for the «پرسنل» section (all hosts, then host positions).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function personnelTabOptions(): array
    {
        if (!app()->bound('db') || !self::hasPersonnelUsers()) {
            return [];
        }

        $options = [
            ['value' => self::ALL_PERSONNEL, 'label' => 'همه پرسنل'],
        ];

        if (User::query()->role('host')->where(function ($query) {
            $query->whereNull('host_position_title')
                ->orWhere('host_position_title', '')
                ->orWhere('host_position_title', HostPositionTitles::DEFAULT_LABEL);
        })->exists()) {
            $options[] = ['value' => 'host', 'label' => HostLabels::roleLabelPlural()];
        }

        $hostTitles = User::query()
            ->role('host')
            ->whereNotNull('host_position_title')
            ->where('host_position_title', '!=', '')
            ->distinct()
            ->orderBy('host_position_title')
            ->pluck('host_position_title');

        foreach ($hostTitles as $title) {
            if ($title === HostPositionTitles::DEFAULT_LABEL) {
                continue;
            }

            $options[] = [
                'value' => 'host_position:' . $title,
                'label' => (string) $title,
            ];
        }

        return self::uniqueByValue($options);
    }

    /**
     * @deprecated Use personnelTabOptions() for host sub-tabs.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function roleTabOptions(): array
    {
        return self::personnelTabOptions();
    }

    public static function hasEmployerUsers(): bool
    {
        if (!app()->bound('db')) {
            return false;
        }

        return User::query()->whereHas('programEmployer')->exists();
    }

    public static function hasBeneficiaryUsers(): bool
    {
        if (!app()->bound('db')) {
            return false;
        }

        return User::query()->whereHas('programBeneficiary')->exists();
    }

    public static function hasPersonnelUsers(): bool
    {
        if (!app()->bound('db')) {
            return false;
        }

        return User::query()->role('host')->exists();
    }

    public static function hasGuestUsers(): bool
    {
        return User::query()->where(function ($query) {
            $query->doesntHave('roles')
                ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'guest'));
        })->exists();
    }

    public static function labelForRole(string $roleName): string
    {
        return match ($roleName) {
            'super_admin' => 'ادمین',
            'host'        => HostLabels::roleLabel(),
            'guest'       => 'مهمان',
            default       => $roleName,
        };
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     * @return list<array{value: string, label: string}>
     */
    private static function uniqueByValue(array $options): array
    {
        $seen = [];
        $unique = [];

        foreach ($options as $option) {
            if (isset($seen[$option['value']])) {
                continue;
            }

            $seen[$option['value']] = true;
            $unique[] = $option;
        }

        return $unique;
    }
}
