<?php

declare(strict_types=1);

namespace App\Support;

/**
 * شاخص‌های یک‌رقمی کدینگ حسابداری استانی (بعد از کد سه‌رقمی استان).
 */
final class ProvinceAccountingIndicators
{
    public const BENEFICIARY    = 1;
    public const ORGANIZATION   = 4;
    public const PERSONNEL      = 7;

    /** @return array<int, string> */
    public static function labels(): array
    {
        return [
            self::BENEFICIARY  => 'ذینفع',
            self::ORGANIZATION => 'ارگان / اداره',
            self::PERSONNEL    => 'پرسنل',
        ];
    }

    public static function label(int $indicator): string
    {
        return self::labels()[$indicator] ?? 'نامشخص';
    }

    /** @return list<int> */
    public static function all(): array
    {
        return [self::BENEFICIARY, self::ORGANIZATION, self::PERSONNEL];
    }
}
