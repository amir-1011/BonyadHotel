<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accommodation_id')->constrained()->cascadeOnDelete();
            $table->string('title');                              // عنوان برنامه / اردو
            $table->text('description')->nullable();              // توضیحات
            $table->string('program_type')->default('camp');      // camp | event | other
            $table->date('start_date');                           // شروع اردو
            $table->date('end_date');                             // پایان اردو
            $table->unsignedSmallInteger('rooms_allocated');      // تعداد اتاق‌های رزرو‌شده
            $table->unsignedSmallInteger('guest_count');          // تعداد نفرات
            $table->string('employer')->nullable();               // کارفرما
            $table->string('contractor')->nullable();             // پیمانکار
            $table->unsignedBigInteger('total_amount')->default(0);    // مبلغ کل (ریال)
            $table->unsignedBigInteger('deposit_amount')->default(0);  // بیعانه (ریال)
            $table->unsignedBigInteger('discount_amount')->default(0); // مبلغ تخفیف (ریال)
            $table->unsignedTinyInteger('discount_percentage')->default(0); // درصد تخفیف
            // خدمات حمایتی – آیا تخفیف مربوط به بنیاد شهید است؟
            $table->boolean('is_supportive_service')->default(false);
            $table->string('supportive_service_type')->nullable(); // نوع خدمت حمایتی
            $table->text('notes')->nullable();                    // یادداشت
            $table->string('status')->default('active');         // active | cancelled | completed
            $table->timestamps();
        });

        // pivot: کدام نوع اتاق به این برنامه اختصاص دارد
        Schema::create('program_room_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained('room_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('rooms_count')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_room_types');
        Schema::dropIfExists('programs');
    }
};
