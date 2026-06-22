{{-- Child under 6 policy fields for accommodation create/edit forms --}}
<div class="col-12">
    <div class="card border-0 bg-light">
        <div class="card-body py-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-emoji-smile me-1"></i>سیاست کودک زیر ۶ سال</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="checkbox"
                               wire:model="childrenUnder6AllocateBed"
                               class="form-check-input"
                               id="children_under_6_allocate_bed">
                        <label class="form-check-label small" for="children_under_6_allocate_bed">
                            به کودک زیر ۶ سال تخت اختصاص داده شود
                        </label>
                    </div>
                    <div class="form-text">اگر غیرفعال باشد، کودک در محاسبه تعداد اتاق/تخت لحاظ نمی‌شود؛ قیمت همچنان بر اساس تعداد کودک محاسبه می‌شود.</div>
                    @error('childrenUnder6AllocateBed')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold" for="children_under_6_discount_percentage">درصد تخفیف نرخ اقامت کودک زیر ۶ سال</label>
                    <div class="input-group input-group-sm" style="max-width:12rem;">
                        <input type="number"
                               wire:model="childrenUnder6DiscountPercentage"
                               id="children_under_6_discount_percentage"
                               class="form-control @error('childrenUnder6DiscountPercentage') is-invalid @enderror"
                               min="0"
                               max="100"
                               required>
                        <span class="input-group-text">٪</span>
                    </div>
                    <div class="form-text">مثلاً ۵۰ یعنی کودک نصف نرخ بزرگسال را می‌پردازد.</div>
                    @error('childrenUnder6DiscountPercentage')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>
</div>
