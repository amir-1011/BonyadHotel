<div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form wire:submit.prevent="import">
                    <p class="text-muted small mb-3">فایل CSV شامل اطلاعات اقامتگاه، اتاق‌ها و تعرفه‌ها را بارگذاری کنید.</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">فایل CSV</label>
                        <input type="file" wire:model="csvFile" class="form-control @error('csvFile') is-invalid @enderror" accept=".csv,.txt">
                        @error('csvFile')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div wire:loading wire:target="csvFile" class="form-text text-primary">در حال بارگذاری فایل...</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" wire:click="preview" wire:loading.attr="disabled" class="btn btn-outline-primary">
                            <span wire:loading.remove wire:target="preview,import"><i class="bi bi-search me-1"></i>بررسی فایل (بدون ثبت)</span>
                            <span wire:loading wire:target="preview,import"><i class="bi bi-hourglass-split me-1"></i>در حال پردازش...</span>
                        </button>
                        <button type="submit" wire:loading.attr="disabled" class="btn btn-primary"
                                onclick="return confirm('اقامتگاه‌های فایل در سایت ثبت شوند؟')">
                            <i class="bi bi-cloud-upload me-1"></i>ثبت نهایی در سایت
                        </button>
                        <a href="{{ route('admin.accommodations.import.sample') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-download me-1"></i>دانلود فایل نمونه
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @if($result)
        <div class="card shadow-sm mt-3 border-{{ $result['success'] ? 'success' : 'danger' }}">
            <div class="card-header bg-{{ $result['success'] ? 'success' : 'danger' }} text-white py-2">
                <i class="bi bi-{{ $result['success'] ? 'check-circle' : 'x-circle' }} me-1"></i>
                {{ $result['success'] ? 'فایل معتبر است' : 'خطا در پردازش فایل' }}
            </div>
            <div class="card-body">
                @if(!empty($result['summary']))
                <div class="row g-2 mb-3">
                    <div class="col-auto">
                        <span class="badge bg-light text-dark border">تعداد ردیف: {{ $result['summary']['rows'] ?? 0 }}</span>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-light text-dark border">تعداد اقامتگاه: {{ $result['summary']['accommodations'] ?? 0 }}</span>
                    </div>
                    <div class="col-auto">
                        <span class="badge bg-{{ $result['success'] ? 'success' : 'secondary' }}">آماده ثبت: {{ $result['imported'] }}</span>
                    </div>
                </div>
                @endif

                @if(!empty($result['warnings']))
                <div class="alert alert-warning mb-3">
                    <div class="fw-semibold mb-2">هشدارها:</div>
                    <ul class="mb-0 small">
                        @foreach(array_slice($result['warnings'], 0, 20) as $warning)
                        <li>{{ $warning }}</li>
                        @endforeach
                        @if(count($result['warnings']) > 20)
                        <li class="text-muted">و {{ count($result['warnings']) - 20 }} مورد دیگر...</li>
                        @endif
                    </ul>
                </div>
                @endif

                @if(!empty($result['errors']))
                <div class="alert alert-danger mb-0">
                    <div class="fw-semibold mb-2">خطاها:</div>
                    <ul class="mb-0 small">
                        @foreach($result['errors'] as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @elseif($result['success'])
                <p class="mb-0 text-success">فایل بدون خطا بررسی شد. برای ثبت نهایی روی دکمه «ثبت نهایی در سایت» کلیک کنید.</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header py-2 fw-semibold"><i class="bi bi-info-circle me-1"></i>راهنمای فرمت CSV</div>
            <div class="card-body small">
                <p class="mb-2">هر <strong>ردیف</strong> یک <strong>تعرفه</strong> است. اقامتگاه‌ها با <code>accommodation_code</code> و اتاق‌ها با <code>room_code</code> گروه‌بندی می‌شوند.</p>

                <p class="fw-semibold mb-1">اقامتگاه</p>
                <ul class="mb-2 ps-3">
                    <li>استان، شهر و شهرستان (اختیاری) در صورت نبودن در فهرست، به‌صورت خودکار اضافه می‌شوند</li>
                    <li>نوع: hotel, villa, apartment, hostel, traditional یا نام فارسی جدید</li>
                    <li>وضعیت اداره: outsourced یا self_governing</li>
                    <li>امکانات با <code>|</code> جدا شوند</li>
                    <li>تلفن‌ها: <code>mobile:0912...:توضیح|landline:021...</code></li>
                </ul>

                <p class="fw-semibold mb-1">اتاق</p>
                <ul class="mb-2 ps-3">
                    <li>ظرفیت، تعداد اتاق (خالی = ۱)، نوع تخت، متراژ</li>
                    <li>ظرفیت اضافه و قیمت نفر اضافه</li>
                    <li>سیگاری و حمام اختصاصی: 1 یا 0</li>
                </ul>

                <p class="fw-semibold mb-1">تعرفه</p>
                <ul class="mb-0 ps-3">
                    <li>قیمت هر شب به ازای هر تخت (ریال)</li>
                    <li>صبحانه رایگان: 1 یا 0</li>
                    <li>لغو: free یا non_refundable</li>
                    <li>پرداخت: pay_at_hotel یا prepay_online</li>
                </ul>

                <hr>
                <p class="text-muted mb-0">مسدودسازی تاریخ و سیاست‌های قیمتی در این فایل نیست و بعد از ثبت از پنل مدیریت می‌شود.</p>
            </div>
        </div>
    </div>
</div>

</div>
