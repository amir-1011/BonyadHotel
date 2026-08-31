@if($showCancellationRequestModal)
@php
    $preview = $this->cancellationRefundPreview();
    $isStaffPanel = in_array($panel ?? 'guest', ['admin', 'host'], true);
    $accountNumberRequired = $this->cancellationAccountNumberRequired();
@endphp
<div class="modal-backdrop fade show" style="z-index:1050;"></div>
<div class="modal fade show cancellation-livewire-modal" style="display:block;z-index:1055;" tabindex="-1" role="dialog" wire:keydown.escape="closeCancellationRequestModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content" style="border-top:4px solid #ef4444;">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle" style="width:36px;height:36px;background:#fde8ea;color:#e11d48;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </span>
                    درخواست کنسلی و استرداد وجه
                </h5>
                <button type="button" class="btn-close" wire:click="closeCancellationRequestModal" aria-label="بستن"></button>
            </div>
            <form wire:submit.prevent="submitCancellationRequest">
                <div class="modal-body">
                    <div class="alert alert-warning small d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle-fill mt-1"></i>
                        <div>
                            @if($this->booking->isMedicalAccommodation())
                                این رزرو اسکان درمانی است و سیاست کنسلی/جریمه اعمال نمی‌شود.
                                مهمان وجه اقامت را پرداخت نکرده، بنابراین مبلغ استرداد به مهمان <strong>۰ ریال</strong> است.
                                @if($preview['is_mid_stay'])
                                    بدهی کارفرما پس از تایید، معادل {{ $preview['nights_elapsed'] }} شب استفاده‌شده به‌علاوه خدمات خواهد بود
                                    ({{ \App\Support\PdfPersian::toPersianDigits(number_format($preview['employer_debt_after'] ?? 0)) }} ریال).
                                @else
                                    در صورت تایید قبل از ورود، بدهی کارفرما صفر می‌شود.
                                @endif
                            @elseif($preview['is_mid_stay'])
                                با توجه به اینکه از تاریخ ورود شما گذشته و <strong>{{ $preview['nights_elapsed'] }}</strong> شب از <strong>{{ $preview['nights_total'] }}</strong> شب اقامت استفاده شده است،
                                مبلغ استرداد فقط بر مبنای <strong>{{ $preview['nights_remaining'] }}</strong> شب باقی‌مانده (معادل <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($preview['basis_amount'])) }} ریال</strong>) محاسبه می‌شود.
                                با اعمال <strong>{{ $preview['percentage'] }}٪</strong> درصد بازگشت وجه، در صورت تایید این درخواست <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($preview['amount'])) }} ریال</strong> به حساب اعلامی شما بازگردانده خواهد شد.
                            @else
                                با توجه به سیاست بازگشت وجه، بر اساس <strong>{{ $preview['days'] }}</strong> روز باقی‌مانده تا تاریخ ورود، در صورت تایید این درخواست
                                <strong>{{ $preview['percentage'] }}٪</strong> از مبلغ رزرو، یعنی <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($preview['amount'])) }} ریال</strong> به حساب اعلامی شما بازگردانده خواهد شد.
                            @endif
                            این درخواست پس از بررسی توسط مدیریت/میزبان تایید یا رد می‌شود.
                        </div>
                    </div>

                    @if($isStaffPanel)
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">مبلغ بازگشتی (ریال) <span class="text-danger">*</span></label>
                        <x-money-input wire:model="cancellationRefundAmount" class="form-control @error('cancellationRefundAmount') is-invalid @enderror" min="0" />
                        @error('cancellationRefundAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text small">مبلغ پیشنهادی بر اساس سیاست بازگشت وجه: {{ \App\Support\PdfPersian::toPersianDigits(number_format($preview['amount'])) }} ریال ({{ $preview['percentage'] }}٪)</div>
                    </div>
                    @endif

                    <div class="mb-3" x-data="{ isCustom: false }">
                        <label class="form-label fw-semibold small">دلیل کنسلی <span class="text-danger">*</span></label>
                        <select wire:model="cancellationReasonId" class="form-select @error('cancellationReasonId') is-invalid @enderror"
                                x-on:change="isCustom = ($event.target.selectedOptions[0]?.dataset.custom === '1')">
                            <option value="">— انتخاب کنید —</option>
                            @foreach($this->cancellationReasonOptions() as $reason)
                            <option value="{{ $reason->id }}" data-custom="{{ $reason->is_custom ? 1 : 0 }}">{{ $reason->label }}</option>
                            @endforeach
                        </select>
                        @error('cancellationReasonId') <div class="invalid-feedback">{{ $message }}</div> @enderror

                        <div class="mt-2" x-show="isCustom" x-cloak>
                            <label class="form-label fw-semibold small">دلیل دلخواه <span class="text-danger">*</span></label>
                            <textarea wire:model="customReasonText" rows="2" class="form-control @error('customReasonText') is-invalid @enderror" placeholder="دلیل خود را بنویسید..."></textarea>
                            @error('customReasonText') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-2 mb-3 {{ $accountNumberRequired ? '' : 'opacity-50' }}">
                        <div class="col-12 col-md-7">
                            <label class="form-label fw-semibold small {{ $accountNumberRequired ? '' : 'text-muted' }}">
                                شماره حساب یا شماره کارت جهت استرداد
                                @if($accountNumberRequired)
                                    <span class="text-danger">*</span>
                                @else
                                    <span class="text-muted fw-normal">(اختیاری)</span>
                                @endif
                            </label>
                            <input type="text"
                                wire:model="refundAccountNumber"
                                inputmode="numeric"
                                dir="ltr"
                                class="form-control @error('refundAccountNumber') is-invalid @enderror {{ $accountNumberRequired ? '' : 'bg-light text-muted' }}"
                                placeholder="{{ $accountNumberRequired ? 'مثال: 6104337812345678' : 'برای مبلغ بازگشتی صفر نیازی به وارد کردن نیست' }}">
                            @error('refundAccountNumber') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label fw-semibold small {{ $accountNumberRequired ? '' : 'text-muted' }}">به نام (اختیاری)</label>
                            <input type="text" wire:model="refundAccountHolderName" class="form-control @error('refundAccountHolderName') is-invalid @enderror {{ $accountNumberRequired ? '' : 'bg-light text-muted' }}">
                            @error('refundAccountHolderName') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold small">توضیحات تکمیلی (اختیاری)</label>
                        <textarea wire:model="cancellationNotes" rows="2" class="form-control @error('cancellationNotes') is-invalid @enderror"></textarea>
                        @error('cancellationNotes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeCancellationRequestModal">انصراف</button>
                    <button type="submit" class="btn btn-danger" wire:loading.attr="disabled" wire:target="submitCancellationRequest">
                        <span wire:loading wire:target="submitCancellationRequest" class="spinner-border spinner-border-sm me-1"></span>
                        ثبت درخواست کنسلی
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
