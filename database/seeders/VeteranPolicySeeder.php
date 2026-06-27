<?php

namespace Database\Seeders;

use App\Models\Accommodation;
use App\Services\VeteranPolicyProvisioner;
use Illuminate\Database\Seeder;

class VeteranPolicySeeder extends Seeder
{
    public function run(): void
    {
        $provisioner = app(VeteranPolicyProvisioner::class);

        foreach (Accommodation::query()->cursor() as $accommodation) {
            $provisioner->seedForAccommodation($accommodation);
        }
    }
}
