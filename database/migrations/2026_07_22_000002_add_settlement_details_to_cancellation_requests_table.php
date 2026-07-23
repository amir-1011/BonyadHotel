<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->unsignedInteger('settled_amount')->nullable()->after('settled_at');
            $table->string('settled_account_number')->nullable()->after('settled_amount');
            $table->text('settlement_notes')->nullable()->after('settled_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('cancellation_requests', function (Blueprint $table) {
            $table->dropColumn(['settled_amount', 'settled_account_number', 'settlement_notes']);
        });
    }
};
