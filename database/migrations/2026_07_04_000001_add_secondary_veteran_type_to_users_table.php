<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'secondary_veteran_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('secondary_veteran_type', 64)->nullable()->after('veteran_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'secondary_veteran_type')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('secondary_veteran_type');
            });
        }
    }
};
