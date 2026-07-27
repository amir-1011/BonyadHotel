<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\City;
use App\Support\ProvinceAccountingCodeCatalog;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['name' => 'تهران', 'cities' => ['تهران', 'کرج', 'ری', 'شهریار', 'دماوند', 'فیروزکوه']],
            ['name' => 'اصفهان', 'cities' => ['اصفهان', 'کاشان', 'خمینی‌شهر', 'نجف‌آباد', 'شاهین‌شهر']],
            ['name' => 'فارس', 'cities' => ['شیراز', 'مرودشت', 'جهرم', 'لار', 'کازرون']],
            ['name' => 'خراسان رضوی', 'cities' => ['مشهد', 'نیشابور', 'سبزوار', 'تربت‌حیدریه', 'قوچان']],
            ['name' => 'گیلان', 'cities' => ['رشت', 'انزلی', 'لاهیجان', 'آستارا', 'صومعه‌سرا']],
            ['name' => 'مازندران', 'cities' => ['ساری', 'آمل', 'بابل', 'نوشهر', 'چالوس', 'رامسر']],
            ['name' => 'آذربایجان شرقی', 'cities' => ['تبریز', 'مراغه', 'اهر', 'بناب', 'سراب']],
            ['name' => 'خوزستان', 'cities' => ['اهواز', 'آبادان', 'خرمشهر', 'دزفول', 'شوشتر']],
            ['name' => 'کرمان', 'cities' => ['کرمان', 'رفسنجان', 'جیرفت', 'بم', 'زرند']],
            ['name' => 'یزد', 'cities' => ['یزد', 'میبد', 'اردکان', 'تفت']],
            ['name' => 'سمنان', 'cities' => ['سمنان', 'شاهرود', 'گرمسار', 'دامغان']],
            ['name' => 'کرمانشاه', 'cities' => ['کرمانشاه', 'اسلام‌آباد غرب', 'کنگاور', 'سرپل‌ذهاب']],
            ['name' => 'هرمزگان', 'cities' => ['بندرعباس', 'قشم', 'کیش', 'بندر لنگه', 'میناب']],
            ['name' => 'خراسان جنوبی', 'cities' => ['بیرجند', 'قاینات', 'سربیشه', 'نهبندان']],
            ['name' => 'بوشهر', 'cities' => ['بوشهر', 'بندر گناوه', 'کنگان', 'دیر']],
        ];

        foreach ($data as $item) {
            $code = ProvinceAccountingCodeCatalog::resolveForName($item['name']);
            $province = Province::firstOrCreate(
                ['name' => $item['name']],
                ['accounting_code' => $code],
            );

            if ($code !== null && !$province->accounting_code) {
                $province->update(['accounting_code' => $code]);
            }

            foreach ($item['cities'] as $cityName) {
                City::firstOrCreate([
                    'province_id' => $province->id,
                    'name'        => $cityName,
                ]);
            }
        }

        Province::query()->firstOrCreate(
            ['name' => 'ستاد مرکز'],
            ['accounting_code' => '500'],
        );
    }
}
