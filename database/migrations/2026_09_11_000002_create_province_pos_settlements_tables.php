<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('province_pos_settlements')) {
            Schema::create('province_pos_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('province_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('service_fee_label')->default('حق سرویس');
                $table->string('service_fee_iban', 32);
                $table->boolean('is_active')->default(true);
                $table->string('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('province_pos_settlement_accounts')) {
            Schema::create('province_pos_settlement_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('province_pos_settlement_id');
                $table->foreign('province_pos_settlement_id', 'pos_split_acct_settlement_fk')
                    ->references('id')
                    ->on('province_pos_settlements')
                    ->cascadeOnDelete();
                $table->string('label');
                $table->string('iban', 32);
                $table->decimal('percentage', 6, 2);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('province_pos_settlement_accounts');
        Schema::dropIfExists('province_pos_settlements');
    }
};
