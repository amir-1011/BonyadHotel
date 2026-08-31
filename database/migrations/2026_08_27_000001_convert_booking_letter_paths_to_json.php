<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->wrapLegacyPaths('medical_referral_letter_path');
        $this->wrapLegacyPaths('credit_letter_path');

        Schema::table('bookings', function (Blueprint $table) {
            $table->json('medical_referral_letter_path')->nullable()->change();
            $table->json('credit_letter_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('medical_referral_letter_path')->nullable()->change();
            $table->string('credit_letter_path')->nullable()->change();
        });

        $this->unwrapLegacyPaths('medical_referral_letter_path');
        $this->unwrapLegacyPaths('credit_letter_path');
    }

    private function wrapLegacyPaths(string $column): void
    {
        DB::table('bookings')
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $booking) use ($column): void {
                $value = $booking->{$column};
                if (!is_string($value) || $value === '') {
                    return;
                }

                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return;
                }

                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update([$column => json_encode([$value])]);
            });
    }

    private function unwrapLegacyPaths(string $column): void
    {
        DB::table('bookings')
            ->whereNotNull($column)
            ->orderBy('id')
            ->each(function (object $booking) use ($column): void {
                $value = $booking->{$column};
                if (!is_string($value) || $value === '') {
                    return;
                }

                $decoded = json_decode($value, true);
                if (!is_array($decoded)) {
                    return;
                }

                $first = collect($decoded)
                    ->filter(fn ($path) => is_string($path) && $path !== '')
                    ->first();

                DB::table('bookings')
                    ->where('id', $booking->id)
                    ->update([$column => $first]);
            });
    }
};
