<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\City;
use Illuminate\Database\Seeder;

class AccommodationSeeder extends Seeder
{
    public function run(): void
    {
        $accommodations = [
            // Tehran
            // Tehran
            ['city' => 'تهران', 'name' => 'هتل پارسیان استقلال', 'type' => 'hotel',
             'price' => 2500000, 'capacity' => 4, 'rooms' => 2,
             'lat' => 35.7448, 'lng' => 51.3753,
             'address' => 'خیابان ولیعصر، تهران',
             'description' => 'یکی از معروف‌ترین هتل‌های تهران با امکانات کامل و موقعیت مرکزی.',
             'amenities' => ['پارکینگ', 'رستوران', 'استخر', 'Wi-Fi', 'سرویس اتاق', 'تهویه مطبوع',
                             'مناسب ویلچر', 'آسانسور', 'رمپ دسترسی', 'سرویس بهداشتی ویژه معلولان']],
            ['city' => 'تهران', 'name' => 'آپارتمان مدرن الهیه', 'type' => 'apartment',
             'price' => 1800000, 'capacity' => 6, 'rooms' => 3,
             'lat' => 35.7940, 'lng' => 51.4280,
             'address' => 'الهیه، تهران',
             'description' => 'آپارتمان مجهز در منطقه الهیه با چشم‌انداز زیبا.',
             'amenities' => ['Wi-Fi', 'آشپزخانه', 'پارکینگ', 'ماشین لباسشویی',
                             'مناسب ویلچر', 'آسانسور']],

            // Isfahan
            ['city' => 'اصفهان', 'name' => 'هتل عباسی', 'type' => 'traditional',
             'price' => 3200000, 'capacity' => 2, 'rooms' => 1,
             'lat' => 32.6539, 'lng' => 51.6660,
             'address' => 'خیابان چهارباغ، اصفهان',
             'description' => 'هتل بی‌نظیر عباسی، بنایی تاریخی از دوران صفوی با معماری اصیل ایرانی.',
             'amenities' => ['رستوران', 'باغ', 'Wi-Fi', 'تهویه مطبوع', 'صبحانه']],
            ['city' => 'اصفهان', 'name' => 'اقامتگاه سنتی خانه بهروزی', 'type' => 'traditional',
             'price' => 950000, 'capacity' => 4, 'rooms' => 2,
             'lat' => 32.6510, 'lng' => 51.6740,
             'address' => 'جلفا، اصفهان',
             'description' => 'خانه‌ای سنتی در محله جلفا، با حیاط ایرانی و دکوراسیون اصیل.',
             'amenities' => ['Wi-Fi', 'صبحانه', 'حیاط', 'آشپزخانه مشترک']],

            // Shiraz
            ['city' => 'شیراز', 'name' => 'هتل آریوبرزن', 'type' => 'hotel',
             'price' => 1400000, 'capacity' => 3, 'rooms' => 1,
             'lat' => 29.6100, 'lng' => 52.5310,
             'address' => 'خیابان زند، شیراز',
             'description' => 'هتلی در قلب شیراز، نزدیک به اماکن تاریخی.',
             'amenities' => ['Wi-Fi', 'رستوران', 'پارکینگ', 'صبحانه',
                             'مناسب ویلچر', 'آسانسور', 'رمپ دسترسی']],

            // Mashhad
            ['city' => 'مشهد', 'name' => 'هتل قصر الضیافه', 'type' => 'hotel',
             'price' => 2100000, 'capacity' => 4, 'rooms' => 2,
             'lat' => 36.2971, 'lng' => 59.6062,
             'address' => 'بلوار وکیل‌آباد، مشهد',
             'description' => 'هتلی نزدیک حرم مطهر امام رضا (ع) با امکانات رفاهی مناسب.',
             'amenities' => ['Wi-Fi', 'رستوران', 'نمازخانه', 'صبحانه', 'سرویس رایگان تا حرم',
                             'مناسب ویلچر', 'آسانسور', 'رمپ دسترسی', 'سرویس بهداشتی ویژه معلولان']],
            ['city' => 'مشهد', 'name' => 'مجتمع اقامتی آستان قدس', 'type' => 'apartment',
             'price' => 1200000, 'capacity' => 6, 'rooms' => 3,
             'lat' => 36.2940, 'lng' => 59.6020,
             'address' => 'خیابان امام رضا، مشهد',
             'description' => 'آپارتمان‌های مجهز نزدیک حرم، مناسب برای خانواده‌ها.',
             'amenities' => ['Wi-Fi', 'آشپزخانه', 'نمازخانه', 'پارکینگ',
                             'مناسب ویلچر', 'آسانسور']],

            // Rasht
            ['city' => 'رشت', 'name' => 'ویلای سبز دریا', 'type' => 'villa',
             'price' => 2800000, 'capacity' => 8, 'rooms' => 4,
             'lat' => 37.2808, 'lng' => 49.5832,
             'address' => 'حومه رشت، گیلان',
             'description' => 'ویلای لوکس با فضای سبز فراوان و نمای جنگل، نزدیک به دریای خزر.',
             'amenities' => ['استخر', 'باربکیو', 'Wi-Fi', 'آشپزخانه', 'پارکینگ', 'زمین بازی']],

            // Kish
            ['city' => 'کیش', 'name' => 'هتل شایان کیش', 'type' => 'hotel',
             'price' => 4500000, 'capacity' => 2, 'rooms' => 1,
             'lat' => 26.5337, 'lng' => 53.9571,
             'address' => 'ساحل دریا، کیش',
             'description' => 'هتل ساحلی لوکس در جزیره کیش با چشم‌انداز مستقیم به دریا.',
             'amenities' => ['استخر', 'ساحل خصوصی', 'رستوران', 'Wi-Fi', 'اسپا', 'ورزش‌های آبی',
                             'مناسب ویلچر', 'آسانسور', 'رمپ دسترسی', 'سرویس بهداشتی ویژه معلولان']],

            // Yazd
            ['city' => 'یزد', 'name' => 'اقامتگاه سنتی کاروانسرا', 'type' => 'traditional',
             'price' => 750000, 'capacity' => 2, 'rooms' => 1,
             'lat' => 31.8974, 'lng' => 54.3678,
             'address' => 'بافت تاریخی، یزد',
             'description' => 'کاروانسرای دوران قاجار با معماری ایرانی اصیل در بافت تاریخی یزد.',
             'amenities' => ['Wi-Fi', 'صبحانه سنتی', 'حیاط', 'آشپزخانه مشترک']],

            // Nowshahr
            ['city' => 'نوشهر', 'name' => 'ویلای جنگلی نوشهر', 'type' => 'villa',
             'price' => 3500000, 'capacity' => 10, 'rooms' => 5,
             'lat' => 36.6502, 'lng' => 51.4987,
             'address' => 'جاده چالوس - نوشهر',
             'description' => 'ویلای بزرگ در دل جنگل‌های مازندران، ایده‌آل برای گروه‌های بزرگ.',
             'amenities' => ['استخر', 'جکوزی', 'باربکیو', 'Wi-Fi', 'آشپزخانه مجهز', 'پارکینگ',
                             'مناسب ویلچر', 'رمپ دسترسی']],

            // Tabriz
            ['city' => 'تبریز', 'name' => 'هتل تبریز', 'type' => 'hotel',
             'price' => 1600000, 'capacity' => 3, 'rooms' => 1,
             'lat' => 38.0962, 'lng' => 46.2738,
             'address' => 'خیابان شریعتی، تبریز',
             'description' => 'هتلی راحت در مرکز شهر تبریز، نزدیک به بازار تاریخی.',
             'amenities' => ['Wi-Fi', 'رستوران', 'پارکینگ', 'صبحانه',
                             'مناسب ویلچر', 'آسانسور']],

            // Bandar Abbas
            ['city' => 'بندرعباس', 'name' => 'هتل هرمز', 'type' => 'hotel',
             'price' => 1900000, 'capacity' => 2, 'rooms' => 1,
             'lat' => 27.1833, 'lng' => 56.2666,
             'address' => 'خیابان امام خمینی، بندرعباس',
             'description' => 'هتل مدرن در بندرعباس با نمای خلیج فارس.',
             'amenities' => ['Wi-Fi', 'رستوران', 'استخر', 'تهویه مطبوع', 'پارکینگ',
                             'مناسب ویلچر', 'آسانسور', 'رمپ دسترسی']],
        ];

        foreach ($accommodations as $data) {
            $city = City::whereHas('province')
                ->where('name', $data['city'])
                ->first();

            if (!$city) continue;

            Accommodation::firstOrCreate(
                ['name' => $data['name'], 'city_id' => $city->id],
                [
                    'type'           => $data['type'],
                    'price_per_night'=> $data['price'],
                    'capacity'       => $data['capacity'],
                    'rooms'          => $data['rooms'],
                    'lat'            => $data['lat'],
                    'lng'            => $data['lng'],
                    'address'        => $data['address'],
                    'description'    => $data['description'],
                    'amenities'      => $data['amenities'],
                    'is_active'      => true,
                ]
            );
        }
    }
}
