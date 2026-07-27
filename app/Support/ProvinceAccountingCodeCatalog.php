<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نگاشت پیش‌فرض نام استان به کد سه‌رقمی حسابداری.
 * مدیر می‌تواند از پنل مدیریت استان‌ها این مقادیر را تغییر دهد.
 */
final class ProvinceAccountingCodeCatalog
{
    /** @return array<string, string> */
    public static function defaultCodesByName(): array
    {
        return [
            'ستاد مرکز'              => '500',
            'آذربایجان شرقی'         => '501',
            'آذربایجان غربی'         => '502',
            'اردبیل'                 => '503',
            'اصفهان'                 => '504',
            'البرز'                  => '505',
            'ایلام'                  => '506',
            'بوشهر'                  => '507',
            'تهران'                  => '508',
            'چهارمحال و بختیاری'     => '509',
            'خراسان جنوبی'           => '510',
            'خراسان رضوی'            => '511',
            'خراسان شمالی'           => '512',
            'خوزستان'                => '513',
            'زنجان'                  => '514',
            'مازندران'               => '515',
            'سمنان'                  => '516',
            'سیستان و بلوچستان'      => '517',
            'فارس'                   => '518',
            'قزوین'                  => '519',
            'قم'                     => '520',
            'کردستان'                => '521',
            'کرمان'                  => '522',
            'کرمانشاه'               => '523',
            'کهگیلویه و بویراحمد'    => '524',
            'گلستان'                 => '525',
            'گیلان'                  => '526',
            'لرستان'                 => '527',
            'مرکزی'                  => '528',
            'هرمزگان'                => '529',
            'همدان'                  => '530',
            'یزد'                    => '531',
        ];
    }

    public static function resolveForName(string $name): ?string
    {
        $normalized = self::normalizeName($name);

        foreach (self::defaultCodesByName() as $provinceName => $code) {
            if (self::normalizeName($provinceName) === $normalized) {
                return $code;
            }
        }

        return null;
    }

    public static function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return $name;
    }
}
