<div class="ta-page">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header py-2 fw-semibold small">استان‌ها</div>
                <div class="list-group list-group-flush">
                    @forelse($provinces as $province)
                    <button type="button"
                            wire:click="selectProvince({{ $province->id }})"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center {{ (int) $selectedProvinceId === (int) $province->id ? 'active' : '' }}">
                        <span>{{ $province->name }}</span>
                        @if($province->posSettlement?->is_active)
                        <span class="badge bg-success-subtle text-success">فعال</span>
                        @elseif($province->posSettlement)
                        <span class="badge bg-secondary-subtle text-secondary">غیرفعال</span>
                        @else
                        <span class="badge bg-warning-subtle text-warning">بدون تنظیم</span>
                        @endif
                    </button>
                    @empty
                    <div class="list-group-item text-muted small">استانی ثبت نشده است.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @if($selectedProvinceId)
            <form wire:submit="save" class="card shadow-sm">
                <div class="card-header py-2 fw-semibold small">تنظیم تسهیم وجه</div>
                <div class="card-body">
                    <div class="alert alert-info small">
                        مبلغ تراکنش به سه بخش تقسیم می‌شود:
                        <strong>حق سرویس</strong> (همان مبلغ کارمزد سامانه) به شبای حق سرویس می‌رود،
                        و <strong>باقیمانده</strong> طبق درصدهایی که اینجا می‌گذارید بین حساب‌های استان تقسیم می‌شود.
                        جمع درصد باقیمانده باید دقیقاً ۱۰۰ باشد و جمع مبالغ تسهیم با مبلغ کل تراکنش یکی می‌شود.
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">عنوان سهم حق سرویس</label>
                            <input wire:model="serviceFeeLabel" type="text" class="form-control form-control-sm">
                            @error('serviceFeeLabel')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm">شبا حق سرویس</label>
                            <input wire:model="serviceFeeIban" type="text" dir="ltr" class="form-control form-control-sm @error('serviceFeeIban') is-invalid @enderror" placeholder="IR000000000000000000000000">
                            @error('serviceFeeIban')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="fw-semibold small">حساب‌های باقیمانده مبلغ</div>
                        <div class="small {{ abs($remainderPercentTotal - 100) < 0.01 ? 'text-success' : 'text-danger' }}">
                            مجموع درصد: {{ \App\Support\PdfPersian::toPersianDigits(number_format($remainderPercentTotal, 2)) }}٪
                        </div>
                    </div>
                    @error('remainderAccounts')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

                    @foreach($remainderAccounts as $index => $account)
                    <div class="row g-2 align-items-end mb-2" wire:key="pos-share-{{ $index }}">
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">عنوان</label>
                            <input wire:model="remainderAccounts.{{ $index }}.label" type="text" class="form-control form-control-sm">
                            @error('remainderAccounts.'.$index.'.label')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-5">
                            <label class="form-label form-label-sm">شبا</label>
                            <input wire:model="remainderAccounts.{{ $index }}.iban" type="text" dir="ltr" class="form-control form-control-sm" placeholder="IR...">
                            @error('remainderAccounts.'.$index.'.iban')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm">درصد</label>
                            <input wire:model="remainderAccounts.{{ $index }}.percentage" type="text" dir="ltr" class="form-control form-control-sm" placeholder="50">
                            @error('remainderAccounts.'.$index.'.percentage')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-2">
                            <button type="button" wire:click="removeRemainderAccount({{ $index }})" class="btn btn-outline-danger btn-sm w-100">حذف</button>
                        </div>
                    </div>
                    @endforeach

                    <button type="button" wire:click="addRemainderAccount" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="bi bi-plus-lg me-1"></i>حساب باقیمانده دیگر
                    </button>

                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label form-label-sm">یادداشت</label>
                            <input wire:model="formNotes" type="text" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-4 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input wire:model="formIsActive" class="form-check-input" type="checkbox" id="pos-split-active">
                                <label class="form-check-label small" for="pos-split-active">فعال</label>
                            </div>
                        </div>
                    </div>

                    @if($previewShares)
                    <div class="border rounded-3 p-3 bg-light-subtle mb-3">
                        <div class="small fw-semibold mb-2">نمونه تقسیم برای مبلغ آزمایشی</div>
                        <div class="row g-2 align-items-end mb-2">
                            <div class="col-md-5">
                                <label class="form-label form-label-sm">مبلغ آزمایشی (ریال)</label>
                                <input wire:model.live="previewAmount" type="text" dir="ltr" class="form-control form-control-sm money-input">
                            </div>
                        </div>
                        <ul class="small mb-0">
                            @foreach($previewShares as $share)
                            <li>
                                {{ $share['label'] }}
                                @if(!empty($share['percentage'])) ({{ \App\Support\PdfPersian::toPersianDigits($share['percentage']) }}٪ از باقیمانده) @endif
                                —
                                <strong dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format((int) $share['amount'])) }}</strong>
                                ریال
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-lg me-1"></i>ذخیره تسهیم استان
                    </button>
                </div>
            </form>
            @endif
        </div>
    </div>
</div>
