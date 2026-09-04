<?php

namespace App\Support;

/**
 * Default Day Insurance (بیمه دی) medical-accommodation tariff catalog.
 * Provinces can edit rates, companion limits, and add custom types.
 */
final class MedicalAccommodationTariffs
{
    public const EMPLOYER_NAME = 'بیمه دی';

    public const KEY_NECK_INJURY = 'neck_injury';

    public const KEY_SPINAL_AMPUTEE = 'spinal_amputee';

    public const KEY_OTHER_VETERAN = 'other_veteran';

    public const KEY_MEDICAL_STAFF = 'medical_staff';

    public const KEY_NORMAL_HOST = 'normal_host';

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            [
                'key'                    => self::KEY_NECK_INJURY,
                'label'                  => 'جانبازان معزز گردنی',
                'nightly_rate'           => 10_000_000,
                'companion_nightly_rate' => 0,
                'companions_included'    => 0,
                'max_companions'         => 2,
                'notes'                  => 'نرخ جهانی ماده ۱۰ قرارداد بیمه دی؛ شامل حداکثر دو همراه. هزینه همراه جداگانه پرداخت نمی‌شود.',
                'sort_order'             => 1,
                'is_active'              => true,
            ],
            [
                'key'                    => self::KEY_SPINAL_AMPUTEE,
                'label'                  => 'جانبازان معزز نخاعی و قطع دو عضو بالای زانو',
                'nightly_rate'           => 7_200_000,
                'companion_nightly_rate' => 0,
                'companions_included'    => 0,
                'max_companions'         => 1,
                'notes'                  => 'نرخ جهانی ماده ۱۰ قرارداد بیمه دی؛ شامل حداکثر یک همراه. هزینه همراه جداگانه پرداخت نمی‌شود.',
                'sort_order'             => 2,
                'is_active'              => true,
            ],
            [
                'key'                    => self::KEY_OTHER_VETERAN,
                'label'                  => 'سایر ایثارگران',
                'nightly_rate'           => 3_500_000,
                'companion_nightly_rate' => 1_500_000,
                'companions_included'    => 0,
                'max_companions'         => 1,
                'notes'                  => 'بیمه دی فقط هزینه یک همراه را با نرخ توافقی پرداخت می‌کند. همراه بیش از یک نفر قابل پذیرش نیست.',
                'sort_order'             => 3,
                'is_active'              => true,
            ],
            [
                'key'                    => self::KEY_MEDICAL_STAFF,
                'label'                  => 'کادر درمانی',
                'nightly_rate'           => 0,
                'companion_nightly_rate' => 0,
                'companions_included'    => 0,
                'max_companions'         => 1,
                'notes'                  => 'نرخ شبانه و همراه را استان بر اساس توافق محلی تعیین می‌کند.',
                'sort_order'             => 4,
                'is_active'              => false,
            ],
            [
                'key'                    => self::KEY_NORMAL_HOST,
                'label'                  => 'کاربر عادی',
                'nightly_rate'           => 0,
                'companion_nightly_rate' => 0,
                'companions_included'    => 0,
                'max_companions'         => 1,
                'notes'                  => 'نرخ شبانه و همراه را استان بر اساس توافق محلی تعیین می‌کند.',
                'sort_order'             => 5,
                'is_active'              => false,
            ],
        ];
    }

    /**
     * Catalog groups present on every Day Insurance contract.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function sharedReportGroups(): array
    {
        $groups = [];

        foreach ([self::KEY_NECK_INJURY, self::KEY_SPINAL_AMPUTEE, self::KEY_OTHER_VETERAN] as $key) {
            $definition = self::definition($key);
            $groups[] = [
                'key'   => $key,
                'label' => (string) ($definition['label'] ?? $key),
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public static function templateOptions(): array
    {
        $options = [];

        foreach (self::defaults() as $row) {
            $options[$row['key']] = $row['label'];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(string $key): ?array
    {
        foreach (self::defaults() as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    public static function isEmployerName(?string $name): bool
    {
        $name = trim((string) $name);

        return $name === self::EMPLOYER_NAME
            || str_starts_with($name, self::EMPLOYER_NAME . ' ');
    }

    public static function employerNameForProvince(?string $provinceName): string
    {
        $provinceName = trim((string) $provinceName);

        if ($provinceName === '') {
            return self::EMPLOYER_NAME;
        }

        if (str_contains(self::EMPLOYER_NAME, $provinceName)) {
            return self::EMPLOYER_NAME;
        }

        return self::EMPLOYER_NAME . ' ' . $provinceName;
    }
}
