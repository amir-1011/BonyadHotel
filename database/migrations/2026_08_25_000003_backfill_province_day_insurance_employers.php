<?php

use App\Services\MedicalAccommodationProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_employers')
            || ! Schema::hasTable('provinces')
            || ! Schema::hasTable('medical_accommodation_settings')
        ) {
            return;
        }

        app(MedicalAccommodationProvisioner::class)->syncDayInsuranceEmployers();
    }

    public function down(): void
    {
        // Irreversible: provincial Day Insurance employers are real catalog records.
    }
};
