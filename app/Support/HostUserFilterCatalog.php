<?php

namespace App\Support;

class HostUserFilterCatalog
{
    /** @return list<array{value: string, label: string}> */
    public static function userTypeOptions(): array
    {
        return [
            ['value' => 'guest', 'label' => 'مهمان'],
            ['value' => 'beneficiary', 'label' => 'ذینفع'],
            ['value' => 'employer', 'label' => 'ادارات و ارگان‌ها'],
            ['value' => 'host', 'label' => HostPositionTitles::DEFAULT_LABEL],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function hasBookingsOptions(): array
    {
        return [
            ['value' => '1', 'label' => 'دارای رزرو'],
            ['value' => '0', 'label' => 'بدون رزرو'],
        ];
    }

    /** @return list<array{value: string, label: string}> */
    public static function sortOptions(): array
    {
        return [
            ['value' => 'last_booking', 'label' => 'آخرین رزرو'],
            ['value' => 'name', 'label' => 'نام'],
            ['value' => 'bookings', 'label' => 'تعداد رزرو'],
        ];
    }
}
