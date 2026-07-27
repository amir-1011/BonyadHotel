<?php

use App\Models\Province;
use App\Support\ProvinceAccountingCodeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provinces', function (Blueprint $table) {
            $table->char('accounting_code', 3)->nullable()->unique()->after('name');
        });

        Schema::table('program_employers', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('id')->constrained('provinces')->nullOnDelete();
        });

        Schema::table('program_beneficiaries', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('id')->constrained('provinces')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('host_position_title')->constrained('provinces')->nullOnDelete();
            $table->string('personnel_code', 12)->nullable()->unique()->after('province_id');
        });

        foreach (Province::query()->get() as $province) {
            $code = ProvinceAccountingCodeCatalog::resolveForName((string) $province->name);

            if ($code !== null) {
                $province->update(['accounting_code' => $code]);
            }
        }

        Province::query()->firstOrCreate(
            ['name' => 'ستاد مرکز'],
            ['accounting_code' => '500'],
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
            $table->dropUnique(['personnel_code']);
            $table->dropColumn('personnel_code');
        });

        Schema::table('program_beneficiaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::table('program_employers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('province_id');
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->dropUnique(['accounting_code']);
            $table->dropColumn('accounting_code');
        });
    }
};
