<?php

namespace App\Support;

class HostLabels
{
    public const ROLE = 'کاربر';

    public const ROLE_PLURAL = 'کاربران';

    public const PANEL = 'پنل کاربر';

    public const SECTION = 'کاربری';

    public static function roleLabel(): string
    {
        return self::ROLE;
    }

    public static function roleLabelPlural(): string
    {
        return self::ROLE_PLURAL;
    }

    public static function displayPositionLabel(?string $storedLabel): string
    {
        $storedLabel = trim((string) $storedLabel);

        if (HostPositionTitles::isDefaultPositionLabel($storedLabel)) {
            return self::ROLE;
        }

        return $storedLabel;
    }

    public static function storedPositionLabel(?string $inputLabel): string
    {
        $inputLabel = trim((string) $inputLabel);

        if ($inputLabel === self::ROLE || HostPositionTitles::isDefaultPositionLabel($inputLabel)) {
            return HostPositionTitles::DEFAULT_LABEL;
        }

        return $inputLabel;
    }
}
