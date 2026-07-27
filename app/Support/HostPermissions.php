<?php

namespace App\Support;

class HostPermissions
{
    public const ACTION_READ   = 'read';
    public const ACTION_WRITE  = 'write';
    public const ACTION_EDIT   = 'edit';
    public const ACTION_DELETE = 'delete';

    /** @var list<string> */
    public const ACTIONS = [
        self::ACTION_READ,
        self::ACTION_WRITE,
        self::ACTION_EDIT,
        self::ACTION_DELETE,
    ];

    public static function catalog(): array
    {
        return [
            'dashboard' => [
                'label'       => 'داشبورد',
                'description' => 'خلاصه آمار و آخرین فعالیت‌ها بر اساس اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'grid-1x2-fill',
                'pages'       => [
                    'dashboard.overview' => [
                        'label'       => 'صفحه داشبورد',
                        'description' => 'دسترسی به صفحه اصلی داشبورد میزبان',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.accommodation-filter' => [
                        'label'       => 'فیلتر اقامتگاه‌ها',
                        'description' => 'انتخاب اقامتگاه برای فیلتر داده‌های داشبورد',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-accommodations' => [
                        'label'       => 'کارت اقامتگاه‌های من',
                        'description' => 'نمایش تعداد اقامتگاه‌ها و وضعیت فعال بودن',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-confirmed-bookings' => [
                        'label'       => 'کارت رزرو تأیید‌شده',
                        'description' => 'نمایش تعداد رزروهای تأیید‌شده و در انتظار',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-total-revenue' => [
                        'label'       => 'کارت درآمد کل',
                        'description' => 'نمایش درآمد کل و درآمد امروز',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-month-revenue' => [
                        'label'       => 'کارت درآمد این ماه',
                        'description' => 'نمایش درآمد ماه جاری و نرخ رشد',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-services-revenue' => [
                        'label'       => 'کارت فروش خدمات',
                        'description' => 'نمایش درآمد حاصل از خدمات اضافی',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.kpi-pending-reviews' => [
                        'label'       => 'کارت نظرات بی‌پاسخ',
                        'description' => 'نمایش تعداد نظراتی که نیاز به پاسخ دارند',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.room-status-board' => [
                        'label'       => 'تابلو وضعیت اتاق‌ها',
                        'description' => 'نمایش وضعیت لحظه‌ای اتاق‌ها و ویرایش نقشه ساختمان',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'dashboard.occupancy-calendar' => [
                        'label'       => 'تقویم اشغال',
                        'description' => 'نمایش تقویم اشغال و رزرو اتاق‌ها',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.checkouts-today' => [
                        'label'       => 'خروج امروز',
                        'description' => 'لیست مهمانانی که امروز خروج دارند',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.checkouts-soon' => [
                        'label'       => 'نزدیک به پایان (۱–۲ روز)',
                        'description' => 'لیست رزروهایی که تا دو روز آینده پایان می‌یابند',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.active-stays' => [
                        'label'       => 'مهمانان فعلی',
                        'description' => 'لیست مهمانانی که در حال اقامت هستند',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.revenue-chart' => [
                        'label'       => 'نمودار درآمد و رزرو',
                        'description' => 'روند درآمد و تعداد رزرو در ۳۰ روز گذشته',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.booking-status-chart' => [
                        'label'       => 'نمودار وضعیت رزروها',
                        'description' => 'نمودار دایره‌ای وضعیت رزروها (تأیید، انتظار، لغو)',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.accommodations-performance' => [
                        'label'       => 'عملکرد اقامتگاه‌ها',
                        'description' => 'کارت‌های عملکرد، درآمد و اسپارک‌لاین هر اقامتگاه',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.services-summary' => [
                        'label'       => 'خلاصه فروش خدمات',
                        'description' => 'جدول خلاصه خدمات فروخته‌شده',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.services-details' => [
                        'label'       => 'جزئیات خدمات فروخته‌شده',
                        'description' => 'جدول جزئیات هر خدمت فروخته‌شده',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.recent-bookings' => [
                        'label'       => 'آخرین رزروها',
                        'description' => 'جدول آخرین رزروهای ثبت‌شده',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'dashboard.booking-actions' => [
                        'label'       => 'تأیید / لغو سریع رزرو',
                        'description' => 'دکمه‌های تأیید و لغو رزرو در جدول آخرین رزروها',
                        'actions'     => [self::ACTION_EDIT],
                    ],
                ],
            ],
            'accommodations' => [
                'label'       => 'اقامتگاه‌ها',
                'description' => 'مدیریت اقامتگاه‌های نسبت‌داده‌شده، اتاق‌ها، نرخ‌ها و رزرو دستی',
                'icon'        => 'building-fill',
                'pages'       => [
                    'accommodations.list' => [
                        'label'       => 'لیست اقامتگاه‌ها',
                        'description' => 'مشاهده فهرست اقامتگاه‌های من',
                        'actions'     => [self::ACTION_READ, self::ACTION_DELETE],
                    ],
                    'accommodations.create' => [
                        'label'       => 'افزودن اقامتگاه',
                        'description' => 'ثبت اقامتگاه جدید',
                        'actions'     => [self::ACTION_WRITE],
                    ],
                    'accommodations.edit' => [
                        'label'       => 'ویرایش اقامتگاه',
                        'description' => 'ویرایش اطلاعات، تصاویر و تنظیمات اقامتگاه',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'accommodations.report' => [
                        'label'       => 'گزارش فروش اقامتگاه',
                        'description' => 'گزارش درآمد و آمار فروش',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'accommodations.manual-booking' => [
                        'label'       => 'رزرو دستی',
                        'description' => 'ثبت رزرو دستی برای اقامتگاه',
                        'actions'     => [self::ACTION_WRITE],
                    ],
                    'accommodations.veteran-policy' => [
                        'label'       => 'سیاست ایثارگری اقامتگاه',
                        'description' => 'تنظیم تخفیف‌ها و خدمات ایثارگری',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'accommodations.cancellation-policy' => [
                        'label'       => 'سیاست کنسلی اقامتگاه',
                        'description' => 'تنظیم قوانین استرداد و دلایل کنسلی',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'room-types.list' => [
                        'label'       => 'لیست انواع اتاق',
                        'description' => 'مشاهده انواع اتاق هر اقامتگاه',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'room-types.create' => [
                        'label'       => 'افزودن نوع اتاق',
                        'description' => 'تعریف نوع اتاق جدید',
                        'actions'     => [self::ACTION_WRITE],
                    ],
                    'room-types.edit' => [
                        'label'       => 'ویرایش نوع اتاق',
                        'description' => 'ویرایش مشخصات و ظرفیت نوع اتاق',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT, self::ACTION_DELETE],
                    ],
                    'room-types.rates' => [
                        'label'       => 'نرخ‌های اتاق',
                        'description' => 'مدیریت نرخ‌ها و قوانین قیمت هفتگی',
                        'actions'     => [self::ACTION_WRITE, self::ACTION_EDIT, self::ACTION_DELETE],
                    ],
                    'room-types.blocked-dates' => [
                        'label'       => 'تاریخ‌های مسدود',
                        'description' => 'مسدودسازی و آزادسازی تاریخ‌ها',
                        'actions'     => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
                    ],
                    'room-types.daily-availability' => [
                        'label'       => 'قیمت روزانه / موجودی',
                        'description' => 'تنظیم قیمت و موجودی روزانه',
                        'actions'     => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
                    ],
                ],
            ],
            'bookings' => [
                'label'       => 'رزروها',
                'description' => 'مدیریت رزروهای مربوط به اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'calendar-check-fill',
                'pages'       => [
                    'bookings.list' => [
                        'label'       => 'لیست رزروها',
                        'description' => 'مشاهده و فیلتر رزروها، تأیید و لغو',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'bookings.show' => [
                        'label'       => 'جزئیات رزرو',
                        'description' => 'مشاهده جزئیات رزرو، تأیید و لغو',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'bookings.guests' => [
                        'label'       => 'مهمانان رزرو',
                        'description' => 'ویرایش اطلاعات مهمانان در جزئیات رزرو',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT],
                    ],
                    'bookings.services' => [
                        'label'       => 'خدمات رزرو',
                        'description' => 'افزودن، ویرایش و حذف خدمات اضافه',
                        'actions'     => [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_EDIT, self::ACTION_DELETE],
                    ],
                    'bookings.forms' => [
                        'label'       => 'فرم رزرو امضا‌شده',
                        'description' => 'آپلود و حذف فایل فرم رزرو',
                        'actions'     => [self::ACTION_WRITE, self::ACTION_DELETE],
                    ],
                    'bookings.cancellation-submit' => [
                        'label'       => 'ثبت درخواست کنسلی',
                        'description' => 'ثبت درخواست کنسلی و استرداد از صفحه رزرو',
                        'actions'     => [self::ACTION_WRITE],
                    ],
                    'bookings.export' => [
                        'label'       => 'خروجی اکسل رزروها',
                        'description' => 'دانلود فایل اکسل رزروها',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'bookings.pdf' => [
                        'label'       => 'رسید PDF رزرو',
                        'description' => 'دانلود رسید رزرو',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'cancellation-requests.list' => [
                        'label'       => 'لیست درخواست‌های کنسلی',
                        'description' => 'مشاهده فهرست درخواست‌های کنسلی و استرداد',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'cancellation-requests.decide' => [
                        'label'       => 'تایید یا رد درخواست کنسلی',
                        'description' => 'بررسی، تایید یا رد درخواست‌های کنسلی',
                        'actions'     => [self::ACTION_EDIT],
                    ],
                    'cancellation-requests.settle' => [
                        'label'       => 'ثبت تسویه استرداد',
                        'description' => 'ثبت واریز مبلغ استرداد به حساب مهمان',
                        'actions'     => [self::ACTION_EDIT],
                    ],
                ],
            ],
            'programs' => [
                'label'       => 'برنامه‌ها و اردوها',
                'description' => 'برنامه‌های مرتبط با اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'flag-fill',
                'pages'       => [
                    'programs.list' => [
                        'label'       => 'لیست برنامه‌ها',
                        'description' => 'مشاهده و لغو برنامه‌ها',
                        'actions'     => [self::ACTION_READ, self::ACTION_DELETE],
                    ],
                    'programs.create' => [
                        'label'       => 'افزودن برنامه',
                        'description' => 'ایجاد برنامه یا اردوی جدید',
                        'actions'     => [self::ACTION_WRITE],
                    ],
                    'programs.show' => [
                        'label'       => 'جزئیات برنامه',
                        'description' => 'مشاهده اطلاعات و مهمانان برنامه',
                        'actions'     => [self::ACTION_READ, self::ACTION_DELETE],
                    ],
                    'programs.supportive-report' => [
                        'label'       => 'گزارش خدمات حمایتی',
                        'description' => 'گزارش خدمات حمایتی برنامه‌ها',
                        'actions'     => [self::ACTION_READ],
                    ],
                ],
            ],
            'reviews' => [
                'label'       => 'نظرات مهمانان',
                'description' => 'نظرات اقامتگاه‌های انتصاب‌شده',
                'icon'        => 'star-fill',
                'pages'       => [
                    'reviews.list' => [
                        'label'       => 'لیست نظرات',
                        'description' => 'مشاهده، پاسخ و حذف پاسخ نظرات',
                        'actions'     => [self::ACTION_READ, self::ACTION_EDIT, self::ACTION_DELETE],
                    ],
                ],
            ],
            'users' => [
                'label'       => 'کاربران',
                'description' => 'مهمانانی که در اقامتگاه‌های انتصاب‌شده رزرو داشته‌اند',
                'icon'        => 'people-fill',
                'pages'       => [
                    'users.list' => [
                        'label'       => 'لیست کاربران',
                        'description' => 'مشاهده مهمانان و تاریخچه رزرو',
                        'actions'     => [self::ACTION_READ],
                    ],
                    'users.export' => [
                        'label'       => 'خروجی اکسل کاربران',
                        'description' => 'دانلود فایل اکسل کاربران',
                        'actions'     => [self::ACTION_READ],
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function moduleKeys(): array
    {
        return array_keys(self::catalog());
    }

    /** @return list<string> */
    public static function pageKeys(): array
    {
        $keys = [];

        foreach (self::catalog() as $module) {
            foreach ($module['pages'] as $pageKey => $page) {
                $keys[] = $pageKey;
            }
        }

        return $keys;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return self::moduleKeys();
    }

    /** @return array<string, list<string>> */
    public static function defaults(): array
    {
        return self::fullAccessGrants();
    }

    /** @return array<string, list<string>> */
    public static function fullAccessGrants(): array
    {
        $grants = [];

        foreach (self::catalog() as $module) {
            foreach ($module['pages'] as $pageKey => $page) {
                $grants[$pageKey] = $page['actions'];
            }
        }

        return $grants;
    }

    /** @return array<string, list<string>> */
    public static function moduleFullAccessGrants(string $moduleKey): array
    {
        $module = self::catalog()[$moduleKey] ?? null;

        if (!$module) {
            return [];
        }

        $grants = [];

        foreach ($module['pages'] as $pageKey => $page) {
            $grants[$pageKey] = $page['actions'];
        }

        return $grants;
    }

    public static function moduleForPage(string $pageKey): ?string
    {
        foreach (self::catalog() as $moduleKey => $module) {
            if (array_key_exists($pageKey, $module['pages'])) {
                return $moduleKey;
            }
        }

        return null;
    }

    public static function pageDefinition(string $pageKey): ?array
    {
        $moduleKey = self::moduleForPage($pageKey);

        if (!$moduleKey) {
            return null;
        }

        return self::catalog()[$moduleKey]['pages'][$pageKey] ?? null;
    }

    /** @return list<string> */
    public static function allowedActionsForPage(string $pageKey): array
    {
        return self::pageDefinition($pageKey)['actions'] ?? [];
    }

    /**
     * Normalize stored permissions (legacy flat modules or granular grants).
     *
     * @param  mixed  $stored
     * @return array<string, list<string>>
     */
    public static function normalizeStored(mixed $stored): array
    {
        if ($stored === null) {
            return self::fullAccessGrants();
        }

        if (!is_array($stored)) {
            return self::fullAccessGrants();
        }

        if (self::isLegacyModuleList($stored)) {
            return self::expandLegacyModules($stored);
        }

        return self::sanitizeGrants($stored);
    }

    /**
     * @param  array<int|string, mixed>  $stored
     */
    private static function isLegacyModuleList(array $stored): bool
    {
        if ($stored === []) {
            return true;
        }

        foreach ($stored as $key => $value) {
            if (!is_int($key) || !is_string($value) || !in_array($value, self::moduleKeys(), true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $modules
     * @return array<string, list<string>>
     */
    public static function expandLegacyModules(array $modules): array
    {
        if ($modules === []) {
            return [];
        }

        $grants = [];

        foreach (array_unique($modules) as $moduleKey) {
            if (!in_array($moduleKey, self::moduleKeys(), true)) {
                continue;
            }

            $grants = array_merge($grants, self::moduleFullAccessGrants($moduleKey));
        }

        return $grants;
    }

    /**
     * @param  array<string, mixed>  $grants
     * @return array<string, list<string>>
     */
    public static function sanitizeGrants(array $grants): array
    {
        $grants = self::migrateLegacyCancellationListEdit($grants);
        $grants = self::migrateLegacyDashboardGrants($grants);
        $normalized = [];

        foreach ($grants as $pageKey => $actions) {
            if (!in_array($pageKey, self::pageKeys(), true)) {
                continue;
            }

            $allowed = self::allowedActionsForPage($pageKey);
            $enabled = self::normalizeActionList($actions, $allowed);

            if ($enabled !== []) {
                $normalized[$pageKey] = $enabled;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $grants
     * @return array<string, list<string>>
     */
    private static function migrateLegacyCancellationListEdit(array $grants): array
    {
        $list = $grants['cancellation-requests.list'] ?? [];

        if (!in_array(self::ACTION_EDIT, $list, true)) {
            return $grants;
        }

        $list = array_values(array_diff($list, [self::ACTION_EDIT]));

        if ($list !== []) {
            $grants['cancellation-requests.list'] = $list;
        } else {
            unset($grants['cancellation-requests.list']);
        }

        foreach (['cancellation-requests.decide', 'cancellation-requests.settle'] as $pageKey) {
            $actions = $grants[$pageKey] ?? [];

            if (!in_array(self::ACTION_EDIT, $actions, true)) {
                $actions[] = self::ACTION_EDIT;
                $grants[$pageKey] = array_values(array_unique($actions));
            }
        }

        return $grants;
    }

    /**
     * Expand legacy flat `dashboard` grants to granular dashboard widget pages.
     *
     * @param  array<string, list<string>>  $grants
     * @return array<string, list<string>>
     */
    private static function migrateLegacyDashboardGrants(array $grants): array
    {
        if (!array_key_exists('dashboard', $grants)) {
            return $grants;
        }

        $legacy = self::normalizeActionList(
            $grants['dashboard'],
            [self::ACTION_READ, self::ACTION_EDIT],
        );
        unset($grants['dashboard']);

        if ($legacy === []) {
            return $grants;
        }

        $module = self::catalog()['dashboard'] ?? null;

        if (!$module) {
            return $grants;
        }

        foreach ($module['pages'] as $pageKey => $page) {
            $enabled = [];

            if (in_array(self::ACTION_READ, $legacy, true)
                && in_array(self::ACTION_READ, $page['actions'], true)) {
                $enabled[] = self::ACTION_READ;
            }

            if (in_array(self::ACTION_EDIT, $legacy, true)) {
                foreach ($page['actions'] as $action) {
                    if (in_array($action, [self::ACTION_EDIT, self::ACTION_WRITE, self::ACTION_DELETE], true)) {
                        $enabled[] = $action;
                    }
                }
            }

            if ($enabled !== []) {
                $grants[$pageKey] = array_values(array_unique($enabled));
            }
        }

        return $grants;
    }

    /**
     * @return list<string>
     */
    public static function dashboardPageKeys(): array
    {
        return array_keys(self::catalog()['dashboard']['pages'] ?? []);
    }

    /**
     * @param  array<string, list<string>>  $grants
     */
    public static function grantsHaveDashboardReadAccess(array $grants): bool
    {
        foreach (self::catalog()['dashboard']['pages'] ?? [] as $pageKey => $page) {
            if (!in_array(self::ACTION_READ, $page['actions'], true)) {
                continue;
            }

            if (self::grantsAllow($pageKey, self::ACTION_READ, $grants)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, list<string>>  $grants
     */
    public static function grantsHaveDashboardAccess(array $grants): bool
    {
        foreach (self::dashboardPageKeys() as $pageKey) {
            if (($grants[$pageKey] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function enabledModulesFromGrants(array $grants): array
    {
        $modules = [];

        foreach (array_keys($grants) as $pageKey) {
            $module = self::moduleForPage($pageKey);

            if ($module) {
                $modules[$module] = true;
            }
        }

        return array_keys($modules);
    }

    /**
     * @param  mixed  $actions
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function normalizeActionList(mixed $actions, array $allowed): array
    {
        if (is_array($actions) && array_is_list($actions)) {
            return array_values(array_intersect($actions, $allowed));
        }

        if (!is_array($actions)) {
            return [];
        }

        $enabled = [];

        foreach (self::ACTIONS as $action) {
            if (!in_array($action, $allowed, true)) {
                continue;
            }

            if (!empty($actions[$action])) {
                $enabled[] = $action;
            }
        }

        return $enabled;
    }

    public static function grantsAllow(string $pageKey, string $action, array $grants): bool
    {
        if (!in_array($action, self::ACTIONS, true)) {
            return false;
        }

        $allowed = self::allowedActionsForPage($pageKey);

        if (!in_array($action, $allowed, true)) {
            return false;
        }

        return in_array($action, $grants[$pageKey] ?? [], true);
    }

    public static function grantsHaveModuleAccess(string $moduleKey, array $grants): bool
    {
        foreach (array_keys($grants) as $pageKey) {
            if (self::moduleForPage($pageKey) === $moduleKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{page: string, action: string}|null
     */
    public static function permissionForRoute(?string $routeName, string $method = 'GET'): ?array
    {
        if (!$routeName || !str_starts_with($routeName, 'host.')) {
            return null;
        }

        $method = strtoupper($method);

        $map = [
            'dashboard.overview' => [
                'host.dashboard' => self::ACTION_READ,
            ],
            'accommodations.list' => [
                'host.accommodations.index' => self::ACTION_READ,
            ],
            'accommodations.create' => [
                'host.accommodations.create' => self::ACTION_WRITE,
            ],
            'accommodations.edit' => [
                'host.accommodations.edit' => self::ACTION_READ,
            ],
            'accommodations.report' => [
                'host.accommodations.report' => self::ACTION_READ,
            ],
            'accommodations.manual-booking' => [
                'host.accommodations.manual-booking' => self::ACTION_WRITE,
            ],
            'accommodations.veteran-policy' => [
                'host.accommodations.veteran-policy' => self::ACTION_READ,
            ],
            'accommodations.cancellation-policy' => [
                'host.accommodations.cancellation-policy' => self::ACTION_READ,
            ],
            'room-types.list' => [
                'host.room-types.index' => self::ACTION_READ,
            ],
            'room-types.create' => [
                'host.room-types.create' => self::ACTION_WRITE,
            ],
            'room-types.edit' => [
                'host.room-types.edit'   => self::ACTION_READ,
                'host.room-types.update' => self::ACTION_EDIT,
                'host.room-types.destroy'=> self::ACTION_DELETE,
            ],
            'room-types.rates' => [
                'host.room-types.rates.store'   => self::ACTION_WRITE,
                'host.room-types.rates.update'  => self::ACTION_EDIT,
                'host.room-types.rates.destroy' => self::ACTION_DELETE,
            ],
            'room-types.blocked-dates' => [
                'host.room-types.blocked-dates'        => self::ACTION_READ,
                'host.room-types.blocked-dates.preview'=> self::ACTION_READ,
                'host.room-types.blocked-dates.store'  => self::ACTION_WRITE,
                'host.room-types.blocked-dates.destroy'=> self::ACTION_DELETE,
                'host.room-types.blocked-dates-range.destroy' => self::ACTION_DELETE,
            ],
            'room-types.daily-availability' => [
                'host.room-types.daily-availability'       => self::ACTION_READ,
                'host.room-types.daily-availability.store'   => self::ACTION_WRITE,
                'host.room-types.daily-availability.destroy' => self::ACTION_DELETE,
                'host.room-types.daily-availability-range.destroy' => self::ACTION_DELETE,
                'host.room-types.weekly-price-rules.destroy' => self::ACTION_DELETE,
                'host.room-types.rate-weekly-price-rules.destroy' => self::ACTION_DELETE,
            ],
            'bookings.list' => [
                'host.bookings.index' => self::ACTION_READ,
            ],
            'bookings.show' => [
                'host.bookings.show' => self::ACTION_READ,
            ],
            'bookings.export' => [
                'host.bookings.export' => self::ACTION_READ,
            ],
            'bookings.pdf' => [
                'host.bookings.pdf' => self::ACTION_READ,
            ],
            'cancellation-requests.list' => [
                'host.cancellation-requests.index' => self::ACTION_READ,
            ],
            'programs.list' => [
                'host.programs.index' => self::ACTION_READ,
            ],
            'programs.create' => [
                'host.programs.create' => self::ACTION_WRITE,
            ],
            'programs.show' => [
                'host.programs.show' => self::ACTION_READ,
            ],
            'programs.supportive-report' => [
                'host.programs.supportive-report' => self::ACTION_READ,
            ],
            'reviews.list' => [
                'host.reviews.index' => self::ACTION_READ,
            ],
            'users.list' => [
                'host.users.index' => self::ACTION_READ,
            ],
            'users.export' => [
                'host.users.export' => self::ACTION_READ,
            ],
        ];

        // HTTP method overrides for routes mapped to multiple actions
        $methodOverrides = [
            'host.room-types.store'    => ['page' => 'room-types.create', 'action' => self::ACTION_WRITE],
            'host.programs.destroy'    => ['page' => 'programs.show', 'action' => self::ACTION_DELETE],
        ];

        if (isset($methodOverrides[$routeName])) {
            return $methodOverrides[$routeName];
        }

        foreach ($map as $pageKey => $routes) {
            foreach ($routes as $pattern => $defaultAction) {
                if ($routeName === $pattern || (str_ends_with($pattern, '.') && str_starts_with($routeName, $pattern))) {
                    return ['page' => $pageKey, 'action' => $defaultAction];
                }
            }
        }

        return null;
    }

    public static function landingRoute(string $moduleOrPage): string
    {
        $module = self::moduleForPage($moduleOrPage) ?? $moduleOrPage;

        return match ($module) {
            'dashboard'      => route('host.dashboard'),
            'accommodations' => route('host.accommodations.index'),
            'bookings'       => route('host.bookings.index'),
            'programs'       => route('host.programs.index'),
            'reviews'        => route('host.reviews.index'),
            'users'          => route('host.users.index'),
            default          => route('host.dashboard'),
        };
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_READ   => 'مشاهده',
            self::ACTION_WRITE  => 'ایجاد',
            self::ACTION_EDIT   => 'ویرایش',
            self::ACTION_DELETE => 'حذف',
            default             => $action,
        };
    }

    /**
     * @param  array<string, list<string>>  $grants
     * @return array<string, bool>
     */
    public static function grantsToFormState(array $grants): array
    {
        $state = [];

        foreach (self::catalog() as $module) {
            foreach ($module['pages'] as $pageKey => $page) {
                foreach ($page['actions'] as $action) {
                    $state[self::formKey($pageKey, $action)] = in_array($action, $grants[$pageKey] ?? [], true);
                }
            }
        }

        return $state;
    }

    /**
     * @param  array<string, bool>  $formState
     * @return array<string, list<string>>
     */
    public static function grantsFromFormState(array $formState): array
    {
        $grants = [];

        foreach (self::catalog() as $module) {
            foreach ($module['pages'] as $pageKey => $page) {
                $enabled = [];

                foreach ($page['actions'] as $action) {
                    if (!empty($formState[self::formKey($pageKey, $action)])) {
                        $enabled[] = $action;
                    }
                }

                if ($enabled !== []) {
                    $grants[$pageKey] = $enabled;
                }
            }
        }

        return $grants;
    }

    public static function formKey(string $pageKey, string $action): string
    {
        // Livewire treats dots in wire:model paths as nested keys — keep this flat.
        return str_replace('.', '_', $pageKey) . '__' . $action;
    }

    /**
     * @param  array<string, bool>  $formState
     */
    public static function toggleModuleInFormState(array &$formState, string $moduleKey, bool $enabled): void
    {
        $module = self::catalog()[$moduleKey] ?? null;

        if (!$module) {
            return;
        }

        foreach ($module['pages'] as $pageKey => $page) {
            foreach ($page['actions'] as $action) {
                $formState[self::formKey($pageKey, $action)] = $enabled;
            }
        }
    }

    /**
     * @param  array<string, bool>  $formState
     */
    public static function togglePageInFormState(array &$formState, string $pageKey, bool $enabled): void
    {
        $page = self::pageDefinition($pageKey);

        if (!$page) {
            return;
        }

        foreach ($page['actions'] as $action) {
            $formState[self::formKey($pageKey, $action)] = $enabled;
        }
    }

    /**
     * @param  array<string, bool>  $formState
     */
    public static function moduleIsFullyEnabled(array $formState, string $moduleKey): bool
    {
        $module = self::catalog()[$moduleKey] ?? null;

        if (!$module) {
            return false;
        }

        foreach ($module['pages'] as $pageKey => $page) {
            foreach ($page['actions'] as $action) {
                if (empty($formState[self::formKey($pageKey, $action)])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $formState
     */
    public static function moduleHasAnyEnabled(array $formState, string $moduleKey): bool
    {
        $module = self::catalog()[$moduleKey] ?? null;

        if (!$module) {
            return false;
        }

        foreach ($module['pages'] as $pageKey => $page) {
            foreach ($page['actions'] as $action) {
                if (!empty($formState[self::formKey($pageKey, $action)])) {
                    return true;
                }
            }
        }

        return false;
    }
}
