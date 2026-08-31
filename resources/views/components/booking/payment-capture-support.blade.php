<div id="bnb-payment-doc-anchor" class="d-none">
    <div id="bnb-payment-doc-slot" class="d-none">
        <label class="form-label small">مستندات پرداخت (اختیاری)</label>
        <input type="file"
               wire:model="pendingPaymentDocuments"
               multiple
               accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*"
               class="form-control form-control-sm">
        <div wire:loading wire:target="pendingPaymentDocuments" class="small text-muted mt-1">در حال بارگذاری فایل...</div>
        @error('pendingPaymentDocuments')<div class="text-danger small">{{ $message }}</div>@enderror
        @error('pendingPaymentDocuments.*')<div class="text-danger small">{{ $message }}</div>@enderror
        @if($pendingPaymentDocuments !== [])
        <div class="small text-success mt-1">
            @foreach($pendingPaymentDocuments as $doc)
            <div><i class="bi bi-check-circle me-1"></i>{{ $doc->getClientOriginalName() }}</div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@if($showAddPosTerminal ?? false)
<div class="modal-backdrop fade show" style="z-index:10100;"></div>
<div class="modal fade show" style="display:block;z-index:10105;" tabindex="-1" wire:keydown.escape="closePosTerminalModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-top:4px solid #0ea5e9;">
            <div class="modal-header">
                <h5 class="modal-title">افزودن ترمینال پز</h5>
                <button type="button" class="btn-close" wire:click="closePosTerminalModal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <x-accounting.province-select
                        class="col-12"
                        :provinces="\App\Models\Province::query()->orderBy('name')->get()"
                        :show-code-preview="false"
                        hint="پیش‌فرض از استان اقامتگاه رزرو است."
                    />
                    <div class="col-md-6">
                        <label class="form-label small">شماره ترمینال <span class="text-danger">*</span></label>
                        <input type="text" wire:model="newPosTerminalNumber" dir="ltr" class="form-control form-control-sm">
                        @error('newPosTerminalNumber')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">عنوان (اختیاری)</label>
                        <input type="text" wire:model="newPosTerminalLabel" class="form-control form-control-sm">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" wire:click="closePosTerminalModal" class="btn btn-outline-secondary">انصراف</button>
                <button type="button" wire:click="addPosTerminalToCatalog" class="btn btn-info text-white">ذخیره و انتخاب</button>
            </div>
        </div>
    </div>
</div>
@endif
