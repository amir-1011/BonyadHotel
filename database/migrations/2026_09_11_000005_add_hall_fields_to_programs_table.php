<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programs')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            if (! Schema::hasColumn('programs', 'hall_id')) {
                $table->foreignId('hall_id')->nullable()->after('program_type')->constrained('halls')->nullOnDelete();
            }
            if (! Schema::hasColumn('programs', 'hall_start_time')) {
                $table->time('hall_start_time')->nullable()->after('hall_id');
            }
            if (! Schema::hasColumn('programs', 'hall_end_time')) {
                $table->time('hall_end_time')->nullable()->after('hall_start_time');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('programs')) {
            return;
        }

        Schema::table('programs', function (Blueprint $table) {
            if (Schema::hasColumn('programs', 'hall_id')) {
                $table->dropConstrainedForeignId('hall_id');
            }
            if (Schema::hasColumn('programs', 'hall_start_time')) {
                $table->dropColumn('hall_start_time');
            }
            if (Schema::hasColumn('programs', 'hall_end_time')) {
                $table->dropColumn('hall_end_time');
            }
        });
    }
};
