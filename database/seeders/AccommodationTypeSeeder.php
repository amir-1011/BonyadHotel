<?php

namespace Database\Seeders;

use App\Models\AccommodationType;
use Illuminate\Database\Seeder;

class AccommodationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'hotel', 'label' => 'هتل'],
            ['key' => 'villa', 'label' => 'ویلا'],
            ['key' => 'apartment', 'label' => 'آپارتمان'],
            ['key' => 'hostel', 'label' => 'باغ ویلا'],
            ['key' => 'traditional', 'label' => 'اقامتگاه سنتی'],
        ];

        foreach ($defaults as $type) {
            AccommodationType::updateOrCreate(
                ['key' => $type['key']],
                ['label' => $type['label'], 'is_system' => true]
            );
        }
    }
}
