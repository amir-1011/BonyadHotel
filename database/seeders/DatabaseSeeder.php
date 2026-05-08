<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ProvinceSeeder::class,
            AccommodationSeeder::class,
            RolesAndUsersSeeder::class,
            AssignHostsSeeder::class,
            BookingAndReviewSeeder::class,
            RichAccommodationSeeder::class,
        ]);
    }
}
