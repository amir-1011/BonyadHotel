<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_employers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('employer_code')->unique();
            $table->string('national_or_economic_id', 20);
            $table->string('mobile', 15);
            $table->timestamps();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('program_employer_id')->nullable()->after('program_type')->constrained()->nullOnDelete();
        });

        if (Schema::hasColumn('programs', 'employer')) {
            $employers = DB::table('programs')
                ->whereNotNull('employer')
                ->where('employer', '!=', '')
                ->distinct()
                ->pluck('employer');

            foreach ($employers as $name) {
                $code = 'EMP-' . substr(md5((string) $name), 0, 8);
                $suffix = 1;

                while (DB::table('program_employers')->where('employer_code', $code)->exists()) {
                    $code = 'EMP-' . substr(md5((string) $name . $suffix), 0, 8);
                    $suffix++;
                }

                $employerId = DB::table('program_employers')->insertGetId([
                    'name'                    => $name,
                    'employer_code'           => $code,
                    'national_or_economic_id' => '0000000000',
                    'mobile'                  => '09000000000',
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);

                DB::table('programs')
                    ->where('employer', $name)
                    ->update(['program_employer_id' => $employerId]);
            }
        }

        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'counterparty')) {
                $table->dropColumn('counterparty');
            }

            if (Schema::hasColumn('programs', 'employer')) {
                $table->dropColumn('employer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('counterparty')->nullable();
            $table->string('employer')->nullable();
            $table->dropConstrainedForeignId('program_employer_id');
        });

        Schema::dropIfExists('program_employers');
    }
};
