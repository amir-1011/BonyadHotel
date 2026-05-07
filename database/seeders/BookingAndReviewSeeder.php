<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\City;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BookingAndReviewSeeder extends Seeder
{
    public function run(): void
    {
        $accommodations = Accommodation::where('is_active', true)->get();
        $guests = User::role('guest')->get();

        if ($accommodations->isEmpty() || $guests->isEmpty()) {
            $this->command->warn('No accommodations or guests found. Run other seeders first.');
            return;
        }

        $statuses   = ['confirmed', 'confirmed', 'confirmed', 'confirmed', 'pending', 'cancelled'];
        $bookings   = [];

        foreach ($guests as $guest) {
            // Each guest gets 2-4 bookings
            $count = rand(2, 4);
            for ($i = 0; $i < $count; $i++) {
                $acc   = $accommodations->random();
                $daysBack = rand(1, 120);
                $nights   = rand(1, 7);
                $checkIn  = now()->subDays($daysBack)->toDateString();
                $checkOut = now()->subDays($daysBack - $nights)->toDateString();

                // Future bookings for some
                if ($i === 0) {
                    $daysFwd = rand(5, 60);
                    $nights  = rand(2, 5);
                    $checkIn = now()->addDays($daysFwd)->toDateString();
                    $checkOut = now()->addDays($daysFwd + $nights)->toDateString();
                }

                $base           = $acc->price_per_night * $nights;
                $discountPct    = $guest->discount_percentage;
                $discountAmt    = (int) ($base * $discountPct / 100);
                $total          = $base - $discountAmt;
                $status         = $statuses[array_rand($statuses)];

                // Future bookings shouldn't be cancelled
                if ($checkIn > now()->toDateString()) {
                    $status = rand(0,1) ? 'confirmed' : 'pending';
                }

                $tracking = strtoupper(Str::random(10));

                Booking::firstOrCreate(
                    ['tracking_code' => $tracking],
                    [
                        'user_id'            => $guest->id,
                        'accommodation_id'   => $acc->id,
                        'check_in'           => $checkIn,
                        'check_out'          => $checkOut,
                        'guests'             => rand(1, min($acc->capacity, 4)),
                        'nights'             => $nights,
                        'base_price'         => $base,
                        'discount_percentage'=> $discountPct,
                        'discount_amount'    => $discountAmt,
                        'total_price'        => $total,
                        'status'             => $status,
                    ]
                );
            }
        }

        $this->command->info('Bookings seeded.');
        $this->call(ReviewSeeder::class);
    }
}
