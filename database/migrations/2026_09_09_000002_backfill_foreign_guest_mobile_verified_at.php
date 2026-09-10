<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('is_foreign_guest', true)
            ->whereNull('mobile_verified_at')
            ->whereNotNull('passport_number')
            ->update(['mobile_verified_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible: cannot distinguish staff-verified foreign guests from OTP-verified ones.
    }
};
