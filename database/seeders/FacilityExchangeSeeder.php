<?php

namespace Database\Seeders;

use App\Models\FacilityExchangeItem;
use App\Models\FacilityItemBrand;
use App\Models\FacilityItemCategory;
use App\Models\Province;
use App\Models\User;
use App\Services\FacilityItemBrandCatalogService;
use App\Services\FacilityItemCategoryCatalogService;
use App\Support\HostPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacilityExchangeSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('facility_exchange_items')) {
            $this->command->warn('جدول facility_exchange_items وجود ندارد. ابتدا migration را اجرا کنید.');

            return;
        }

        $hosts = User::role('host')->get();

        if ($hosts->isEmpty()) {
            $this->command->warn('هیچ میزبانی یافت نشد. ابتدا RolesAndUsersSeeder را اجرا کنید.');

            return;
        }

        $this->wipeSeededItems();
        $this->assignHostProvinces($hosts);

        $categoryService = app(FacilityItemCategoryCatalogService::class);
        $brandService = app(FacilityItemBrandCatalogService::class);
        $categoryService->ensureSeeded();

        $categories = FacilityItemCategory::query()->pluck('id', 'name');
        $provinces = Province::query()->pluck('id', 'name');
        $hostByMobile = $hosts->keyBy('mobile');

        $brands = $this->seedBrands($brandService, $hosts);

        $surplusItems = $this->surplusDefinitions($hostByMobile, $categories, $provinces, $brands);
        $neededItems = $this->neededDefinitions($hostByMobile, $categories, $provinces, $brands);
        $bulkItems = $this->generateBulkItems(
            50 - count($surplusItems) - count($neededItems),
            $hosts,
            $categories,
            $provinces,
            $brands,
        );

        foreach ($surplusItems as $row) {
            $this->createItem(FacilityExchangeItem::TYPE_SURPLUS, $row);
        }

        foreach ($neededItems as $row) {
            $this->createItem(FacilityExchangeItem::TYPE_NEEDED, $row);
        }

        foreach ($bulkItems as $row) {
            $this->createItem($row['type'], $row);
        }

        $surplusCount = count($surplusItems) + collect($bulkItems)->where('type', FacilityExchangeItem::TYPE_SURPLUS)->count();
        $neededCount = count($neededItems) + collect($bulkItems)->where('type', FacilityExchangeItem::TYPE_NEEDED)->count();

        $this->command->info(sprintf(
            'آگهی‌های نمونه ثبت شد: %d اقلام مازاد، %d اقلام مورد نیاز (مجموع %d).',
            $surplusCount,
            $neededCount,
            $surplusCount + $neededCount,
        ));
    }

    private function wipeSeededItems(): void
    {
        FacilityExchangeItem::query()->each(function (FacilityExchangeItem $item): void {
            $item->deleteStoredImage();
        });

        FacilityExchangeItem::query()->delete();
    }

  /** @param \Illuminate\Support\Collection<int, User> $hosts */
    private function assignHostProvinces($hosts): void
    {
        $map = [
            '09110000001' => 'تهران',
            '09110000002' => 'اصفهان',
            '09110000003' => 'خراسان رضوی',
        ];

        $provinces = Province::query()->pluck('id', 'name');

        foreach ($hosts as $host) {
            $provinceName = $map[$host->mobile] ?? 'تهران';
            $provinceId = $provinces[$provinceName] ?? $provinces->first();

            if ($provinceId) {
                $host->update(['province_id' => $provinceId]);
            }

            if ($host->host_panel_permissions === null) {
                $host->update(['host_panel_permissions' => HostPermissions::fullAccessGrants()]);
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function seedBrands(FacilityItemBrandCatalogService $brandService, $hosts): array
    {
        $names = [
            'ایسوس',
            'پاناسونیک',
            'ایران رادیاتور',
            'جنرال',
            'پارس خزر',
            'بوش',
            'هیتاچی',
            'متفرقه',
        ];

        $brands = [];
        $creatorId = $hosts->first()?->id;

        foreach ($names as $name) {
            $brand = $brandService->add($name, $creatorId);
            $brands[$name] = $brand->id;
        }

        return $brands;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function surplusDefinitions($hostByMobile, $categories, $provinces, array $brands): array
    {
        $fateme = $hostByMobile->get('09110000003');
        $sara = $hostByMobile->get('09110000001');
        $mohammad = $hostByMobile->get('09110000002');

        return [
            [
                'user_id'        => $fateme?->id ?? $hostByMobile->first()->id,
                'name'           => 'کولر گازی اسپلیت',
                'brand_id'       => $brands['ایسوس'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'یک دستگاه ۱۸ هزار',
                'quantity'       => 10,
                'province_id'    => $provinces['خراسان رضوی'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000003',
                'description'    => 'کولرهای مازاد پس از بازسازی اتاق‌ها؛ سالم و سرویس‌شده.',
                'image'          => 'test_1.webp',
                'created_at'     => now()->subMinutes(2),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'گالن آب ۲۰ لیتری',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['مواد مصرفی'],
                'unit_volume'    => 'دو گالن ده لیتری',
                'quantity'       => 24,
                'province_id'    => $provinces['تهران'],
                'expiry_date'    => now()->addMonths(8)->toDateString(),
                'contact_phone'  => '09110000001',
                'description'    => 'گالن‌های پلمپ و تمیز؛ مناسب انبار مواد مصرفی.',
                'image'          => null,
                'created_at'     => now()->subHours(6),
            ],
            [
                'user_id'        => $mohammad?->id ?? $hostByMobile->first()->id,
                'name'           => 'پمپ آب طبقاتی',
                'brand_id'       => $brands['پارس خزر'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'یک دستگاه ۲ اسب',
                'quantity'       => 2,
                'province_id'    => $provinces['اصفهان'],
                'expiry_date'    => null,
                'contact_phone'  => '03132223344',
                'description'    => 'پمپ کم‌کارکرد؛ جایگزین شده با مدل جدید.',
                'image'          => 'building.webp',
                'created_at'     => now()->subDays(2),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'فرش اتاق دو نفره',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'یک قطعه ۳×۲ متر',
                'quantity'       => 5,
                'province_id'    => $provinces['گیلان'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000001',
                'description'    => 'فرش‌های تمیز و بدون لکه؛ مناسب اتاق‌های مهمان.',
                'image'          => 'test_2.webp',
                'created_at'     => now()->subWeeks(1),
            ],
            [
                'user_id'        => $fateme?->id ?? $hostByMobile->first()->id,
                'name'           => 'صابون و شامپو مهمان',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['مواد مصرفی'],
                'unit_volume'    => 'بسته ۵۰ عددی',
                'quantity'       => 500,
                'province_id'    => $provinces['مازندران'],
                'expiry_date'    => now()->addYear()->toDateString(),
                'contact_phone'  => '09110000003',
                'description'    => 'مواد بهداشتی مازاد با تاریخ انقضای مناسب.',
                'image'          => null,
                'created_at'     => now()->subDays(5),
            ],
            [
                'user_id'        => $mohammad?->id ?? $hostByMobile->first()->id,
                'name'           => 'چراغ اضطراری LED',
                'brand_id'       => $brands['پاناسونیک'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'بسته ۱۰ عددی',
                'quantity'       => 30,
                'province_id'    => $provinces['فارس'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000002',
                'description'    => 'چراغ‌های شارژی برای راهروها و پارکینگ.',
                'image'          => 'test_3.webp',
                'created_at'     => now()->subHours(20),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'شیرآلات سرویس بهداشتی',
                'brand_id'       => $brands['ایران رادیاتور'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'یک ست کامل',
                'quantity'       => 8,
                'province_id'    => $provinces['تهران'],
                'expiry_date'    => null,
                'contact_phone'  => '02188776655',
                'description'    => 'شیر و روشویی یدکی؛ آکبند و بدون استفاده.',
                'image'          => 'calendar.webp',
                'created_at'     => now()->subDays(10),
            ],
            [
                'user_id'        => $fateme?->id ?? $hostByMobile->first()->id,
                'name'           => 'کولر آبی صنعتی',
                'brand_id'       => $brands['جنرال'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'یک دستگاه ۹۰ سانت',
                'quantity'       => 1,
                'province_id'    => $provinces['خراسان رضوی'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000003',
                'description'    => null,
                'image'          => 'test_1.webp',
                'created_at'     => now()->subMonths(1),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function neededDefinitions($hostByMobile, $categories, $provinces, array $brands): array
    {
        $fateme = $hostByMobile->get('09110000003');
        $sara = $hostByMobile->get('09110000001');
        $mohammad = $hostByMobile->get('09110000002');

        return [
            [
                'user_id'        => $mohammad?->id ?? $hostByMobile->first()->id,
                'name'           => 'پمپ آب فشار قوی',
                'brand_id'       => $brands['پارس خزر'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'یک دستگاه ۳ اسب',
                'quantity'       => 1,
                'province_id'    => $provinces['اصفهان'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000002',
                'description'    => 'پمپ فعلی از کار افتاده؛ نیاز فوری برای آب‌رسانی.',
                'created_at'     => now()->subMinutes(15),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'مواد شوینده و ضدعفونی',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['مواد مصرفی'],
                'unit_volume'    => 'کارتن ۱۲ عددی',
                'quantity'       => 40,
                'province_id'    => $provinces['تهران'],
                'expiry_date'    => now()->addMonths(6)->toDateString(),
                'contact_phone'  => '09110000001',
                'description'    => 'برای فصل پیک؛ ترجیحاً برند معتبر.',
                'created_at'     => now()->subHours(3),
            ],
            [
                'user_id'        => $fateme?->id ?? $hostByMobile->first()->id,
                'name'           => 'برج خنک‌کننده',
                'brand_id'       => $brands['بوش'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'یک دستگاه ۶۰ لیتری',
                'quantity'       => 2,
                'province_id'    => $provinces['خراسان رضوی'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000003',
                'description'    => 'برای آشپزخانه صنعتی اقامتگاه.',
                'created_at'     => now()->subDay(),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'یخچال کوچک هتلی',
                'brand_id'       => $brands['هیتاچی'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'یک دستگاه ۹۰ لیتری',
                'quantity'       => 3,
                'province_id'    => $provinces['مازندران'],
                'expiry_date'    => null,
                'contact_phone'  => '02144556677',
                'description'    => 'برای اتاق‌های جدید؛ حالت نو یا کم‌کارکرد.',
                'created_at'     => now()->subDays(4),
            ],
            [
                'user_id'        => $mohammad?->id ?? $hostByMobile->first()->id,
                'name'           => 'حوله و روتختی یک‌نفره',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['مواد مصرفی'],
                'unit_volume'    => 'ست کامل',
                'quantity'       => 120,
                'province_id'    => $provinces['گیلان'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000002',
                'description'    => 'برای تعویض دوره‌ای اتاق‌ها.',
                'created_at'     => now()->subDays(7),
            ],
            [
                'user_id'        => $fateme?->id ?? $hostByMobile->first()->id,
                'name'           => 'ست ملزومات خواب',
                'brand_id'       => $brands['متفرقه'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'پتو + بالش + ملحفه',
                'quantity'       => 25,
                'province_id'    => $provinces['یزد'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000003',
                'description'    => 'نیاز به ست کامل برای اتاق‌های اضافه‌شده در طبقه دوم. ترجیحاً رنگ روشن و قابل شستشو.',
                'created_at'     => now()->subWeeks(2),
            ],
            [
                'user_id'        => $sara?->id ?? $hostByMobile->first()->id,
                'name'           => 'کابل برق صنعتی',
                'brand_id'       => $brands['ایسوس'],
                'category_id'    => $categories['تاسیسات'],
                'unit_volume'    => 'حلقه ۵۰ متر',
                'quantity'       => 4,
                'province_id'    => $provinces['تهران'],
                'expiry_date'    => null,
                'contact_phone'  => '09110000001',
                'description'    => 'برای تعمیرات برق موقت در بخش شمالی ساختمان.',
                'created_at'     => now()->subHours(45),
            ],
            [
                'user_id'        => $mohammad?->id ?? $hostByMobile->first()->id,
                'name'           => 'لامپ LED مهتابی',
                'brand_id'       => $brands['پاناسونیک'],
                'category_id'    => $categories['تجهیزات'],
                'unit_volume'    => 'بسته ۲۰ عددی',
                'quantity'       => 15,
                'province_id'    => $provinces['خوزستان'],
                'expiry_date'    => null,
                'contact_phone'  => '06133445566',
                'description'    => 'جایگزینی لامپ‌های سوخته راهروها.',
                'created_at'     => now()->subMonths(2),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function generateBulkItems(int $count, $hosts, $categories, $provinces, array $brands): array
    {
        if ($count <= 0) {
            return [];
        }

        $seedImages = ['test_1.webp', 'test_2.webp', 'test_3.webp', 'building.webp', 'calendar.webp'];
        $categoryIds = array_values($categories->all());
        $provinceIds = array_values($provinces->all());
        $brandIds = array_values($brands);
        $hostList = $hosts->values()->all();

        $surplusCatalog = [
            ['name' => 'تلویزیون LED ۴۳ اینچ', 'unit' => 'یک دستگاه', 'category' => 'تجهیزات', 'qty' => [1, 5]],
            ['name' => 'مبل راحتی یک‌نفره', 'unit' => 'یک عدد', 'category' => 'تجهیزات', 'qty' => [2, 12]],
            ['name' => 'میز و صندلی ناهارخوری', 'unit' => 'یک ست ۴ نفره', 'category' => 'تجهیزات', 'qty' => [1, 8]],
            ['name' => 'قفل دیجیتال درب', 'unit' => 'یک دستگاه', 'category' => 'تجهیزات', 'qty' => [3, 15]],
            ['name' => 'سنسور دود', 'unit' => 'بسته ۵ عددی', 'category' => 'تجهیزات', 'qty' => [10, 40]],
            ['name' => 'کپسول آتش‌نشانی', 'unit' => 'یک عدد ۶ کیلو', 'category' => 'تجهیزات', 'qty' => [2, 10]],
            ['name' => 'پتو نخی یک‌نفره', 'unit' => 'یک عدد', 'category' => 'مواد مصرفی', 'qty' => [20, 100]],
            ['name' => 'ملحفه یک‌نفره', 'unit' => 'یک ست', 'category' => 'مواد مصرفی', 'qty' => [30, 150]],
            ['name' => 'دستمال کاغذی', 'unit' => 'کارتن ۴۸ بسته', 'category' => 'مواد مصرفی', 'qty' => [5, 25]],
            ['name' => 'مایع دستشویی', 'unit' => 'گالن ۴ لیتری', 'category' => 'مواد مصرفی', 'qty' => [8, 40]],
            ['name' => 'شیر مخلوط حمام', 'unit' => 'یک عدد', 'category' => 'تاسیسات', 'qty' => [4, 20]],
            ['name' => 'فلاش تانک توکار', 'unit' => 'یک دستگاه', 'category' => 'تاسیسات', 'qty' => [2, 12]],
            ['name' => 'لوله PVC آبی', 'unit' => 'شاخه ۶ متری', 'category' => 'تاسیسات', 'qty' => [10, 50]],
            ['name' => 'رادیاتور پنلی', 'unit' => 'یک قطعه ۶۰ سانتی', 'category' => 'تاسیسات', 'qty' => [4, 16]],
            ['name' => 'کولر پشت‌بامی', 'unit' => 'یک دستگاه ۲۵ هزار', 'category' => 'تاسیسات', 'qty' => [1, 4]],
        ];

        $neededCatalog = [
            ['name' => 'یخچال فریزر هتلی', 'unit' => 'یک دستگاه ۳۰۰ لیتری', 'category' => 'تجهیزات', 'qty' => [1, 3]],
            ['name' => 'ماشین لباسشویی صنعتی', 'unit' => 'یک دستگاه ۱۰ کیلو', 'category' => 'تجهیزات', 'qty' => [1, 2]],
            ['name' => 'اجاق گاز چهار شعله', 'unit' => 'یک دستگاه', 'category' => 'تجهیزات', 'qty' => [1, 4]],
            ['name' => 'جاروبرقی صنعتی', 'unit' => 'یک دستگاه', 'category' => 'تجهیزات', 'qty' => [2, 6]],
            ['name' => 'پتو مهمان', 'unit' => 'یک عدد', 'category' => 'مواد مصرفی', 'qty' => [50, 200]],
            ['name' => 'شامپو و ژل حمام', 'unit' => 'بسته ۱۰۰ عددی', 'category' => 'مواد مصرفی', 'qty' => [20, 80]],
            ['name' => 'کیسه زباله', 'unit' => 'بسته ۵۰ عددی', 'category' => 'مواد مصرفی', 'qty' => [10, 60]],
            ['name' => 'فیلتر آب تصفیه', 'unit' => 'یک عدد', 'category' => 'تاسیسات', 'qty' => [2, 8]],
            ['name' => 'شیر برقی آبسردکن', 'unit' => 'یک دستگاه', 'category' => 'تاسیسات', 'qty' => [1, 3]],
            ['name' => 'موتورخانه و پمپ', 'unit' => 'یک دستگاه ۱.۵ اسب', 'category' => 'تاسیسات', 'qty' => [1, 2]],
            ['name' => 'لامپ مهتابی LED', 'unit' => 'بسته ۱۰ عددی', 'category' => 'تجهیزات', 'qty' => [5, 30]],
            ['name' => 'پرده اتاق', 'unit' => 'یک ست دو لایه', 'category' => 'تجهیزات', 'qty' => [4, 20]],
            ['name' => 'آبگرمکن دیواری', 'unit' => 'یک دستگاه ۵۰ لیتری', 'category' => 'تاسیسات', 'qty' => [1, 4]],
            ['name' => 'سطل و دستمال نظافت', 'unit' => 'یک ست کامل', 'category' => 'مواد مصرفی', 'qty' => [8, 25]],
            ['name' => 'کلید و پریز برق', 'unit' => 'بسته ۲۰ عددی', 'category' => 'تاسیسات', 'qty' => [10, 40]],
        ];

        $surplusDescriptions = [
            'مازاد پس از بازسازی؛ سالم و آماده تحویل.',
            'در انبار اقامتگاه موجود است؛ قابل بازدید.',
            'کم‌کارکرد و بدون مشکل فنی.',
            'فقط به دلیل تعویض با مدل جدید فروخته می‌شود.',
            null,
        ];

        $neededDescriptions = [
            'نیاز فوری برای بهره‌برداری اتاق‌ها.',
            'ترجیحاً برند معتبر و سالم.',
            'قابل تحویل در استان مبدا یا استان‌های مجاور.',
            'برای تکمیل موجودی انبار مواد مصرفی.',
            null,
        ];

        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $isSurplus = $i % 2 === 0;
            $catalog = $isSurplus ? $surplusCatalog : $neededCatalog;
            $template = $catalog[$i % count($catalog)];
            $host = $hostList[$i % count($hostList)];
            $categoryId = $categories[$template['category']] ?? $categoryIds[$i % count($categoryIds)];
            $provinceId = $host->province_id ?: $provinceIds[$i % count($provinceIds)];
            $brandId = $brandIds[$i % count($brandIds)];
            $quantity = random_int($template['qty'][0], $template['qty'][1]);
            $hasExpiry = $template['category'] === 'مواد مصرفی' && random_int(0, 1) === 1;
            $createdAt = now()->subDays(random_int(0, 90))->subHours(random_int(0, 23));

            $row = [
                'type'           => $isSurplus ? FacilityExchangeItem::TYPE_SURPLUS : FacilityExchangeItem::TYPE_NEEDED,
                'user_id'        => $host->id,
                'name'           => $template['name'],
                'brand_id'       => $brandId,
                'category_id'    => $categoryId,
                'unit_volume'    => $template['unit'],
                'quantity'       => $quantity,
                'province_id'    => $provinceId,
                'expiry_date'    => $hasExpiry ? now()->addMonths(random_int(3, 18))->toDateString() : null,
                'contact_phone'  => $host->mobile ?: '09120000000',
                'description'    => ($isSurplus ? $surplusDescriptions : $neededDescriptions)[random_int(0, count($isSurplus ? $surplusDescriptions : $neededDescriptions) - 1)],
                'created_at'     => $createdAt,
            ];

            if ($isSurplus && random_int(0, 2) !== 0) {
                $row['image'] = $seedImages[$i % count($seedImages)];
            }

            $items[] = $row;
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createItem(string $type, array $row): void
    {
        $imagePath = null;

        if ($type === FacilityExchangeItem::TYPE_SURPLUS && !empty($row['image'])) {
            $imagePath = $this->copySeedImage((string) $row['image']);
        }

        $createdAt = $row['created_at'] ?? now();

        FacilityExchangeItem::query()->create([
            'user_id'        => $row['user_id'],
            'type'           => $type,
            'name'           => $row['name'],
            'brand_id'       => $row['brand_id'],
            'category_id'    => $row['category_id'],
            'unit_volume'    => $row['unit_volume'],
            'quantity'       => $row['quantity'],
            'province_id'    => $row['province_id'],
            'expiry_date'    => $row['expiry_date'] ?? null,
            'image_path'     => $imagePath,
            'contact_phone'  => $row['contact_phone'],
            'description'    => $row['description'] ?? null,
            'created_at'     => $createdAt,
            'updated_at'     => $createdAt,
        ]);
    }

    private function copySeedImage(string $basename): ?string
    {
        $source = base_path('public_html/images/ros/' . $basename);

        if (!is_file($source)) {
            return null;
        }

        $relativePath = 'facility-exchange/seed-' . Str::uuid()->toString() . '.webp';

        Storage::disk('public')->put($relativePath, file_get_contents($source));

        return $relativePath;
    }
}
