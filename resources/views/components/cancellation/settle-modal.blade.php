@if($showSettleModal)
<div id="cancellation-settle-modal-root">
<div class="modal-backdrop fade show" style="z-index:1050;"></div>
<div class="modal fade show" style="display:block;z-index:1055;" tabindex="-1" role="dialog" wire:keydown.escape="closeSettleModal">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content" style="border-top:4px solid #2563eb;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash-coin text-primary me-2"></i>ثبت تسویه استرداد وجه</h5>
                <button type="button" class="btn-close" wire:click="closeSettleModal" aria-label="بستن"></button>
            </div>
            <form wire:submit.prevent="submitSettle">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">تاریخ و زمان واریز <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <div class="col-12 col-md-7">
                                <input type="text"
                                    wire:model="settleDepositedDate"
                                    data-wire-prop="settleDepositedDate"
                                    class="form-control cancellation-settle-jalali-date @error('settleDepositedDate') is-invalid @enderror"
                                    placeholder="۱۴۰۴/۰۴/۳۱"
                                    autocomplete="off"
                                    dir="ltr">
                                @error('settleDepositedDate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-5">
                                <input type="text"
                                    wire:model="settleDepositedTime"
                                    class="form-control @error('settleDepositedTime') is-invalid @enderror"
                                    placeholder="14:30"
                                    dir="ltr"
                                    inputmode="numeric"
                                    autocomplete="off">
                                @error('settleDepositedTime') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="form-text small">تاریخ به‌صورت شمسی (مثال 1404/04/31) و زمان به‌صورت 24 ساعته (مثال 14:30)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">مبلغ واریز (تومان) <span class="text-danger">*</span></label>
                        <x-money-input wire:model="settleAmount" class="form-control @error('settleAmount') is-invalid @enderror" min="1" />
                        @error('settleAmount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold small">شماره حساب مقصد <span class="text-danger">*</span></label>
                        <input type="text" wire:model="settleAccountNumber" inputmode="numeric" dir="ltr" class="form-control @error('settleAccountNumber') is-invalid @enderror" placeholder="شماره حساب یا کارت مقصد واریز">
                        @error('settleAccountNumber') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-1">
                        <label class="form-label fw-semibold small">توضیحات (اختیاری)</label>
                        <textarea wire:model="settleNotes" rows="3" class="form-control @error('settleNotes') is-invalid @enderror" placeholder="توضیحات تکمیلی درباره واریز..."></textarea>
                        @error('settleNotes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" wire:click="closeSettleModal">انصراف</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submitSettle">
                        <span wire:loading wire:target="submitSettle" class="spinner-border spinner-border-sm me-1"></span>
                        ثبت تسویه
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
@endif
