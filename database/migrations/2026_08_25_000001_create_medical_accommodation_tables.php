<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodations', function (Blueprint $table) {
            if (! Schema::hasColumn('accommodations', 'medical_accommodation_auto_seed')) {
                $table->boolean('medical_accommodation_auto_seed')->default(true)->after('cancellation_policy_auto_seed');
            }
        });

        Schema::create('medical_accommodation_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('program_employer_id')->nullable()->constrained('program_employers')->nullOnDelete();
            $table->date('contract_starts_on')->nullable();
            $table->date('contract_ends_on')->nullable();
            $table->boolean('skip_cancellation_penalties')->default(true);
            $table->boolean('require_overnight')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('medical_accommodation_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->unsignedBigInteger('nightly_rate')->default(0);
            $table->unsignedBigInteger('companion_nightly_rate')->default(0);
            $table->unsignedTinyInteger('companions_included')->default(0);
            $table->unsignedTinyInteger('max_companions')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['accommodation_id', 'key']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('medical_tariff_id')->nullable()->after('is_credit')->constrained('medical_accommodation_tariffs')->nullOnDelete();
            $table->json('medical_tariff_snapshot')->nullable()->after('medical_tariff_id');
            $table->unsignedInteger('medical_companion_count')->default(0)->after('medical_tariff_snapshot');
            $table->foreignId('program_employer_id')->nullable()->after('medical_companion_count')->constrained('program_employers')->nullOnDelete();
            $table->unsignedBigInteger('employer_debt_amount')->default(0)->after('program_employer_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_employer_id');
            $table->dropConstrainedForeignId('medical_tariff_id');
            $table->dropColumn([
                'medical_tariff_snapshot',
                'medical_companion_count',
                'employer_debt_amount',
            ]);
        });

        Schema::dropIfExists('medical_accommodation_tariffs');
        Schema::dropIfExists('medical_accommodation_settings');

        Schema::table('accommodations', function (Blueprint $table) {
            if (Schema::hasColumn('accommodations', 'medical_accommodation_auto_seed')) {
                $table->dropColumn('medical_accommodation_auto_seed');
            }
        });
    }
};
