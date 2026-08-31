<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Services\VeteranPolicyProvisioner;
use Illuminate\Database\Seeder;

class VeteranPolicySeeder extends Seeder
{
    public function run(): void
    {
        $veteran = app(VeteranPolicyProvisioner::class);
        $medical = app(\App\Services\MedicalAccommodationProvisioner::class);

        foreach (Accommodation::query()->cursor() as $accommodation) {
            $veteran->seedForAccommodation($accommodation);
        }

        $medical->syncDayInsuranceEmployers();
    }
}
