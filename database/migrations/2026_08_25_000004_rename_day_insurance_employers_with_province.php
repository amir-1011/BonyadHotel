<?php

use App\Services\MedicalAccommodationProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_employers') || ! Schema::hasTable('provinces')) {
            return;
        }

        app(MedicalAccommodationProvisioner::class)->syncDayInsuranceEmployers();
    }

    public function down(): void
    {
        // Irreversible: provincial names on Day Insurance employers are the intended catalog values.
    }
};
