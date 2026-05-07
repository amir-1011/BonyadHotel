<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $comments = [
            'اقامتگاه بسیار عالی بود. تمیز، راحت و با امکانات کامل.',
            'موقعیت مکانی فوق‌العاده. به همه توصیه می‌کنم.',
            'خدمات خوبی داشت ولی قیمت کمی بالا بود.',
            'خیلی راضی بودیم. حتماً دوباره می‌آییم.',
            'برای خانواده بسیار مناسب است. بچه‌ها عاشقش شدند.',
            'نزدیک به جاذبه‌های گردشگری. دسترسی عالی.',
            'صبحانه فوق‌العاده بود. صاحب اقامتگاه بسیار مهمان‌نواز.',
            'تختخواب‌ها راحت و اتاق‌ها فضای کافی دارند.',
            'Wi-Fi خوبی داشت. مناسب برای کار از راه دور.',
            'ارزش پولش را دارد. پیشنهاد می‌کنم.',
            'یکی از بهترین سفرهای من بود.',
            'تجربه متفاوتی از اقامت داشتیم. دکوراسیون زیبا.',
        ];

        // Only review past confirmed bookings
        $pastBookings = Booking::where('status', 'confirmed')
            ->where('check_out', '<', now()->toDateString())
            ->with('user', 'accommodation')
            ->get();

        foreach ($pastBookings as $booking) {
            // 70% chance of leaving a review
            if (rand(1, 10) > 3) {
                Review::firstOrCreate(
                    ['user_id' => $booking->user_id, 'accommodation_id' => $booking->accommodation_id],
                    [
                        'booking_id' => $booking->id,
                        'rating'     => rand(3, 5),
                        'comment'    => $comments[array_rand($comments)],
                        'is_visible' => true,
                    ]
                );
            }
        }

        $this->command->info('Reviews seeded.');
    }
}
