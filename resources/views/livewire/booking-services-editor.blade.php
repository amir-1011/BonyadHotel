<div>
    @php
        $serviceCatalog = $this->serviceCatalogOptions();
        $selectedNewCatalog = $newServiceCatalogId && $newServiceCatalogId !== 'custom'
            ? $serviceCatalog->firstWhere('id', (int) $newServiceCatalogId)
            : null;
        $newCatalogVariants = $selectedNewCatalog?->variants?->where('is_active', true) ?? collect();
    @endphp

    @if($booking->services->isNotEmpty())
    <form wire:submit="saveServiceEdits" class="mb-3">
        <div class="d-flex flex-column gap-2">
            @foreach($editableServices as $id => $row)
            @php
                $subtotal = (int) ($row['unit_price'] ?? 0) * (int) ($row['quantity'] ?? 1);
                $discountAmt = (int) ($row['discount_amount'] ?? 0);
                $finalTotal = (int) ($row['total'] ?? $subtotal);
            @endphp
            <div class="border rounded p-2 bg-light" wire:key="rsb-svc-{{ $id }}">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="flex-grow-1" style="min-width:140px;">
                        <div class="small fw-semibold">{{ $row['name'] }}</div>
                        <div class="text-muted" style="font-size:.7rem;">
                            واحد {{ number_format((int) ($row['unit_price'] ?? 0)) }} تومان
                            @if($discountAmt > 0)
                            · تخفیف −{{ number_format($discountAmt) }}
                            @endif
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        @if(!empty($row['id']))
                        <div class="btn-group btn-group-sm">
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    wire:click="adjustServiceQuantity({{ $row['id'] }}, -1)"
                                    wire:loading.attr="disabled"
                                    title="کم کردن">
                                <i class="bi bi-dash"></i>
                            </button>
                            <span class="btn btn-light disabled px-3" style="opacity:1;">{{ (int) ($row['quantity'] ?? 1) }}</span>
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    wire:click="adjustServiceQuantity({{ $row['id'] }}, 1)"
                                    wire:loading.attr="disabled"
                                    title="زیاد کردن">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        <div class="small fw-bold text-nowrap">{{ number_format($finalTotal) }} <span class="text-muted fw-normal">تومان</span></div>
                        <button type="button"
                                wire:click="removeServiceLine({{ $row['id'] }})"
                                data-swal-confirm="این خدمت حذف شود؟"
                                class="btn btn-sm btn-outline-danger"
                                title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                        @endif
                    </div>
                </div>
                <details class="mt-2">
                    <summary class="small text-muted" style="cursor:pointer;">ویرایش دستی قیمت/تعداد</summary>
                    <div class="row g-2 mt-1">
                        <div class="col-md-5">
                            <input type="text" wire:model="editableServices.{{ $id }}.name" class="form-control form-control-sm" placeholder="نام">
                        </div>
                        <div class="col-md-3">
                            <x-money-input wire:model="editableServices.{{ $id }}.unit_price" class="form-control form-control-sm" min="0" />
                        </div>
                        <div class="col-md-2">
                            <input type="number" wire:model="editableServices.{{ $id }}.quantity" class="form-control form-control-sm" min="1" max="99">
                        </div>
                    </div>
                </details>
            </div>
            @endforeach
        </div>

        @if($booking->veteran_type_applied)
        <div class="alert alert-info small py-2 mt-2 mb-2">
            <i class="bi bi-info-circle me-1"></i>
            تخفیف خدمات بر اساس گروه <strong>{{ $booking->veteranLabelApplied() }}</strong> محاسبه می‌شود.
        </div>
        @endif

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <div class="small text-muted">
                جمع خدمات رزرو: <strong>{{ number_format($booking->services_subtotal) }}</strong> تومان
                · کل رزرو: <strong>{{ number_format($booking->total_price) }}</strong> تومان
            </div>
            <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled">ذخیره ویرایش دستی</button>
        </div>
    </form>
    @else
    <div class="alert alert-light border small py-2 mb-3">هنوز خدمتی برای این رزرو ثبت نشده است.</div>
    @endif

    <div class="border rounded p-2 bg-white">
        <div class="small fw-semibold mb-2"><i class="bi bi-plus-circle me-1"></i>افزودن خدمت</div>
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select wire:model.live="newServiceCatalogId" class="form-select form-select-sm @error('newServiceCatalogId') is-invalid @enderror">
                    <option value="">— انتخاب —</option>
                    @foreach($serviceCatalog as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                    <option value="custom">سایر (دستی)</option>
                </select>
                @error('newServiceCatalogId')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            @if($newCatalogVariants->isNotEmpty())
            <div class="col-md-2">
                <select wire:model.live="newServiceCatalogVariantId" class="form-select form-select-sm @error('newServiceCatalogVariantId') is-invalid @enderror">
                    <option value="">— نوع —</option>
                    @foreach($newCatalogVariants as $variant)
                    <option value="{{ $variant->id }}">{{ $variant->name }}</option>
                    @endforeach
                </select>
                @error('newServiceCatalogVariantId')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            @endif
            <div class="col-md-{{ $newCatalogVariants->isNotEmpty() ? 3 : 4 }}">
                <input type="text" wire:model="newServiceName" class="form-control form-control-sm" placeholder="نام خدمت">
                @error('newServiceName')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <x-money-input wire:model="newServicePrice" class="form-control form-control-sm" placeholder="قیمت" min="0" />
                @error('newServicePrice')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-1">
                <input type="number" wire:model="newServiceQty" class="form-control form-control-sm" min="1" max="99">
            </div>
            <div class="col-md-1">
                <button type="button" wire:click="addServiceLine" class="btn btn-sm btn-success w-100" wire:loading.attr="disabled">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>
    </div>
</div>
