<?php

namespace App\Support;

use App\Models\HostPositionTitle;

class HostPositionTitles
{
    /** @return list<string> */
    public static function defaults(): array
    {
        return [
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
        return filled($stored) ? trim($stored) : '';
    }

    public static function resolve(string $preset): ?string
    {
        $title = trim($preset);

        return $title !== '' ? $title : null;
    }

    public static function remember(string $label): string
    {
        return HostPositionTitle::findOrCreateByLabel($label)->label;
    }
}
