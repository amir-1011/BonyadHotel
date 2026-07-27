<?php

namespace App\Support;

use App\Models\HostPositionTitle;
use App\Models\User;

class HostPositionTitles
{
    public const DEFAULT_LABEL = 'میزبان';

    public static function defaultLabel(): string
    {
        return self::DEFAULT_LABEL;
    }

    public static function isDefaultPositionLabel(?string $label): bool
    {
        $label = trim((string) $label);

        return $label === '' || $label === self::DEFAULT_LABEL;
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return [
            self::DEFAULT_LABEL,
            'معاون تخصصی',
            'مدیر تخصصی',
            'کارشناس تخصصی',
            'مدیر مجموعه',
            'مدیر مالی',
            'مدیر داخلی',
            'کارشناس فروش',
            'کارشناس پشتیبانی',
        ];
    }

    /** @return list<string> */
    public static function options(): array
    {
        if (!app()->bound('db') || !\Illuminate\Support\Facades\Schema::hasTable('host_position_titles')) {
            return self::defaults();
        }

        return HostPositionTitle::optionLabels();
    }

    /** @return list<string> */
    public static function optionsForForm(string $selected = ''): array
    {
        $options = self::options();
        $selected = trim($selected);

        if ($selected !== '' && !in_array($selected, $options, true)) {
            $options[] = $selected;
        }

        return $options;
    }

    public static function formStateFromStored(?string $stored): string
    {
        return filled($stored) ? trim($stored) : self::DEFAULT_LABEL;
    }

    public static function resolve(string $preset): ?string
    {
        $title = trim($preset);

        return $title !== '' ? $title : self::DEFAULT_LABEL;
    }

    public static function remember(string $label): string
    {
        return HostPositionTitle::findOrCreateByLabel($label)->label;
    }

    /** @return array<string, list<string>>|null */
    public static function permissionsForLabel(?string $label): ?array
    {
        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        if (!app()->bound('db') || !\Illuminate\Support\Facades\Schema::hasTable('host_position_titles')) {
            return null;
        }

        $record = HostPositionTitle::query()->where('label', $label)->first();

        return $record?->host_panel_permissions;
    }

    /** @param array<string, list<string>> $grants */
    public static function savePermissionsForLabel(string $label, array $grants): void
    {
        HostPositionTitle::findOrCreateByLabel($label)->update([
            'host_panel_permissions' => $grants,
        ]);
    }

    /** @return array<string, list<string>> */
    public static function grantsForPositionLabel(?string $label): array
    {
        $label = filled($label) ? trim((string) $label) : self::DEFAULT_LABEL;

        return HostPermissions::normalizeStored(
            self::permissionsForLabel($label) ?? HostPermissions::defaults(),
        );
    }

    /**
     * @param  array<string, list<string>>  $grants
     */
    public static function syncUsersForPosition(string $label, array $grants): int
    {
        if (!app()->bound('db')) {
            return 0;
        }

        $label = trim($label);
        $normalized = HostPermissions::normalizeStored($grants);
        $count = 0;

        $hostsQuery = User::query()->role('host');

        if ($label === self::DEFAULT_LABEL) {
            $hosts = (clone $hostsQuery)->where(function ($query) {
                $query->whereNull('host_position_title')
                    ->orWhere('host_position_title', '')
                    ->orWhere('host_position_title', self::DEFAULT_LABEL);
            })->get();
        } else {
            $hosts = (clone $hostsQuery)
                ->where('host_position_title', $label)
                ->get();
        }

        foreach ($hosts as $host) {
            $updates = ['host_panel_permissions' => $normalized];

            if ($label === self::DEFAULT_LABEL) {
                $updates['host_position_title'] = self::DEFAULT_LABEL;
            }

            $host->update($updates);
            $count++;
        }

        return $count;
    }
}
