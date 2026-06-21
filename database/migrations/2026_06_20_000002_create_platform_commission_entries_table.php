<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('accommodation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category'); // accommodation | service
            $table->string('category_key'); // accommodation | service:catalog:{id} | service:custom:{slug}
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
            $table->string('service_name')->nullable();
            $table->string('entry_type'); // credit | reversal | adjustment
            $table->string('reason'); // booking_confirmed | booking_cancelled | amount_adjusted
            $table->unsignedBigInteger('transaction_amount')->default(0);
            $table->unsignedTinyInteger('commission_percentage')->default(5);
            $table->unsignedBigInteger('commission_cap')->default(50_000);
            $table->bigInteger('commission_amount'); // signed: positive = credit, negative = reversal/decrease
            $table->json('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['booking_id', 'category_key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_commission_entries');
    }
};
