<?php

namespace App\Support;

class HostPermissions
{
    public static function catalog(): array
    {
        return [
            'dashboard' => [
                'label'       => 'داشبورد',
                'description' => 'خلاصه آمار و آخرین فعالیت‌ها بر اساس اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'grid-1x2-fill',
            ],
            'accommodations' => [
                'label'       => 'اقامتگاه‌ها',
                'description' => 'مشاهده و مدیریت اقامتگاه‌های نسبت‌داده‌شده، اتاق‌ها و رزرو دستی',
                'icon'        => 'building-fill',
            ],
            'bookings' => [
                'label'       => 'رزروها',
                'description' => 'رزروهای مربوط به اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'calendar-check-fill',
            ],
            'programs' => [
                'label'       => 'برنامه‌ها و اردوها',
                'description' => 'برنامه‌های مرتبط با اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'flag-fill',
            ],
            'reviews' => [
                'label'       => 'نظرات مهمانان',
                'description' => 'نظرات اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'star-fill',
            ],
            'users' => [
                'label'       => 'کاربران',
                'description' => 'مهمانانی که در اقامتگاه‌های انتصاب‌شده رزرو داشته‌اند',
                'icon'        => 'people-fill',
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function defaults(): array
    {
        return self::keys();
    }

    public static function permissionForRoute(?string $routeName): ?string
    {
        if (!$routeName || !str_starts_with($routeName, 'host.')) {
            return null;
        }

        $map = [
            'dashboard'      => ['host.dashboard'],
            'accommodations' => ['host.accommodations.', 'host.room-types.'],
            'bookings'       => ['host.bookings.'],
            'programs'       => ['host.programs.'],
            'reviews'        => ['host.reviews.'],
            'users'          => ['host.users.'],
        ];

        foreach ($map as $permission => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return $permission;
                }
            }
        }

        return null;
    }

    public static function landingRoute(string $permission): string
    {
        return match ($permission) {
            'dashboard'      => route('host.dashboard'),
            'accommodations' => route('host.accommodations.index'),
            'bookings'       => route('host.bookings.index'),
            'programs'       => route('host.programs.index'),
            'reviews'        => route('host.reviews.index'),
            'users'          => route('host.users.index'),
            default          => route('host.dashboard'),
        };
    }
}
