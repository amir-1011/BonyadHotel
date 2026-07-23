<div>
    @php
        $serviceCatalog = $this->serviceCatalogOptions();
        $selectedNewCatalog = $newServiceCatalogId && $newServiceCatalogId !== 'custom'
            ? $serviceCatalog->firstWhere('id', (int) $newServiceCatalogId)
            : null;
        $newCatalogVariants = $selectedNewCatalog?->variants?->where('is_active', true) ?? collect();
        $isGuestScope = $guestSortOrder !== null;
        $veteranTypeApplied = !empty($booking->veteran_type_applied);
        $quotaEligibleCount = collect($editableServices)->filter(fn ($row) => empty($row['excluded_from_veteran_quota']))->count();
        $excludedCount = collect($editableServices)->filter(fn ($row) => !empty($row['excluded_from_veteran_quota']))->count();
    @endphp

    @if($isGuestScope)
    <div class="small text-muted mb-2" style="font-size:.72rem;">
        <i class="bi bi-bag-check me-1"></i>خدمات اختصاصی این مهمان
    </div>
    @endif

    @if($booking->services->isNotEmpty())
    <form wire:submit="saveServiceEdits" class="mb-3">
        <div class="d-flex flex-column gap-2">
            @foreach($editableServices as $id => $row)
            @php
                $displayQty = (int) ($row['saved_quantity'] ?? $row['quantity'] ?? 1);
                $pendingQty = (int) ($row['quantity'] ?? 1);
                $quantityPending = $pendingQty !== $displayQty;
                $subtotal = (int) ($row['unit_price'] ?? 0) * $displayQty;
                $discountAmt = (int) ($row['discount_amount'] ?? 0);
                $finalTotal = (int) ($row['total'] ?? $subtotal);
                $excludedFromQuota = !empty($row['excluded_from_veteran_quota']);
                $serviceManualPct = (int) ($row['manual_discount_percentage'] ?? 0);
                $discountReason = \App\Models\BookingService::describeDiscountFromAttributes($row);
            @endphp
            <div class="border rounded p-2 bg-light" wire:key="rsb-svc-{{ $id }}">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div class="flex-grow-1" style="min-width:140px;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <div class="small fw-semibold">{{ $row['name'] }}</div>
                            @if($veteranTypeApplied)
                                @if($excludedFromQuota)
                                <span class="badge text-bg-warning" style="font-size:.65rem;">خارج از سهمیه ایثارگری</span>
                                @else
                                <span class="badge text-bg-success" style="font-size:.65rem;">سهمیه مهمان اصلی</span>
                                @endif
                            @endif
                        </div>
                        <div class="text-muted" style="font-size:.7rem;">
                            واحد {{ number_format((int) ($row['unit_price'] ?? 0)) }} تومان
                            @if($discountAmt > 0)
                            · تخفیف −{{ number_format($discountAmt) }}
                            @if($discountReason !== '')
                            ({{ $discountReason }})
                            @endif
                            @elseif($excludedFromQuota)
                            · نرخ کامل
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
                            <span class="btn btn-light disabled px-3 {{ $quantityPending ? 'border border-primary text-primary fw-bold' : '' }}" style="opacity:1;">{{ $pendingQty }}</span>
                            <button type="button"
                                    class="btn btn-outline-secondary"
                                    wire:click="adjustServiceQuantity({{ $row['id'] }}, 1)"
                                    wire:loading.attr="disabled"
                                    title="زیاد کردن">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                        @if($quantityPending)
                        <button type="button"
                                wire:click="applyServiceQuantity({{ $row['id'] }})"
                                class="btn btn-sm btn-primary"
                                wire:loading.attr="disabled"
                                wire:target="applyServiceQuantity({{ $row['id'] }})">
                            <span wire:loading.remove wire:target="applyServiceQuantity({{ $row['id'] }})"><i class="bi bi-check2 me-1"></i>اعمال</span>
                            <span wire:loading wire:target="applyServiceQuantity({{ $row['id'] }})">...</span>
                        </button>
                        @endif
                        <div class="text-end text-nowrap">
                            @if($discountAmt > 0 || $subtotal !== $finalTotal)
                            <div class="small text-muted" style="font-size:.68rem;">
                                بدون تخفیف: {{ number_format($subtotal) }}
                            </div>
                            <div class="small fw-bold text-success">
                                با تخفیف: {{ number_format($finalTotal) }} <span class="text-muted fw-normal">تومان</span>
                            </div>
                            @else
                            <div class="small fw-bold">
                                {{ number_format($finalTotal) }} <span class="text-muted fw-normal">تومان</span>
                            </div>
                            @endif
                        </div>
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

                @if($veteranTypeApplied && !empty(trim($row['name'] ?? '')))
                <label class="d-flex align-items-start gap-2 rounded px-2 py-2 mt-2 mb-0 border user-select-none {{ $excludedFromQuota ? 'border-warning bg-warning-subtle' : 'border-secondary border-opacity-25' }}"
                       style="cursor:pointer;">
                    <input type="checkbox"
                           class="form-check-input flex-shrink-0 mt-1"
                           wire:model.live="editableServices.{{ $id }}.excluded_from_veteran_quota">
                    <span class="small lh-sm">
                        <span class="fw-semibold d-block">هزینه این خدمت از سهمیه ایثارگری مهمان اصلی کسر نشود</span>
                        <span class="text-muted" style="font-size:.75rem;">
                            {{ $excludedFromQuota ? 'این خدمت با نرخ کامل محاسبه می‌شود و سهمیه رایگان/تخفیف ایثارگری مصرف نمی‌کند.' : 'در صورت فعال‌سازی، می‌توانید تخفیف دستی با ذکر دلیل ثبت کنید.' }}
                        </span>
                    </span>
                </label>
                @endif

                @if($excludedFromQuota && !empty(trim($row['name'] ?? '')))
                <div class="row g-2 mt-2">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">تخفیف دستی ٪</label>
                        <input type="number"
                               wire:model.live="editableServices.{{ $id }}.manual_discount_percentage"
                               class="form-control form-control-sm @error('editableServices.'.$id.'.manual_discount_percentage') is-invalid @enderror"
                               min="0" max="100" placeholder="۰">
                        @error('editableServices.'.$id.'.manual_discount_percentage')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-9">
                        <label class="form-label small mb-1">دلیل تخفیف @if($serviceManualPct > 0)<span class="text-danger">*</span>@endif</label>
                        <input type="text"
                               wire:model.live="editableServices.{{ $id }}.manual_discount_reason"
                               class="form-control form-control-sm @error('editableServices.'.$id.'.manual_discount_reason') is-invalid @enderror"
                               placeholder="مثلاً: پرداخت مستقیم، توافق مدیر، ...">
                        @error('editableServices.'.$id.'.manual_discount_reason')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                </div>
                @endif

                <details class="mt-2">
                    <summary class="small text-muted" style="cursor:pointer;">ویرایش دستی قیمت/تعداد</summary>
                    <div class="row g-2 mt-1 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">نام</label>
                            <input type="text" wire:model="editableServices.{{ $id }}.name" class="form-control form-control-sm" placeholder="نام">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small mb-1">قیمت واحد</label>
                            <x-money-input wire:model="editableServices.{{ $id }}.unit_price" class="form-control form-control-sm" min="0" />
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small mb-1">تعداد</label>
                            <input type="number" wire:model="editableServices.{{ $id }}.quantity" class="form-control form-control-sm" min="1" max="99">
                        </div>
                        <div class="col-md-3">
                            @if(!empty($row['id']))
                            <button type="button"
                                    wire:click="applyServiceLineEdits({{ $row['id'] }})"
                                    class="btn btn-sm btn-primary w-100"
                                    wire:loading.attr="disabled"
                                    wire:target="applyServiceLineEdits({{ $row['id'] }})">
                                <span wire:loading.remove wire:target="applyServiceLineEdits({{ $row['id'] }})"><i class="bi bi-check2 me-1"></i>اعمال</span>
                                <span wire:loading wire:target="applyServiceLineEdits({{ $row['id'] }})">در حال اعمال...</span>
                            </button>
                            @endif
                        </div>
                    </div>
                </details>
            </div>
            @endforeach
        </div>

        @if($veteranTypeApplied && $quotaEligibleCount > 0)
        <div class="alert alert-info small py-2 mt-2 mb-2">
            <i class="bi bi-info-circle me-1"></i>
            @if($excludedCount > 0)
            {{ $quotaEligibleCount }} خدمت از سهمیه/تخفیف گروه <strong>{{ $booking->veteranLabelApplied() }}</strong> استفاده می‌کند؛
            {{ $excludedCount }} خدمت خارج از سهمیه با نرخ کامل (یا تخفیف دستی) محاسبه می‌شود.
            @else
            تخفیف/سهمیه خدمات علامت‌گذاری‌شده از گروه <strong>{{ $booking->veteranLabelApplied() }}</strong> مهمان اصلی محاسبه می‌شود.
            @endif
        </div>
        @endif

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
            <div class="small text-muted">
                @if(!$isGuestScope)
                جمع خدمات رزرو: <strong>{{ number_format($booking->services_subtotal) }}</strong> تومان
                ·
                @endif
                کل رزرو: <strong>{{ number_format($booking->total_price) }}</strong> تومان
            </div>
            @if(!$isGuestScope && !$booking->isProgram())
            <button type="submit" class="btn btn-sm btn-primary" wire:loading.attr="disabled">ذخیره ویرایش دستی</button>
            @endif
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

        @if($veteranTypeApplied)
        <label class="d-flex align-items-start gap-2 rounded px-2 py-2 mt-2 mb-0 border user-select-none {{ $newExcludedFromVeteranQuota ? 'border-warning bg-warning-subtle' : 'border-secondary border-opacity-25' }}"
               style="cursor:pointer;">
            <input type="checkbox"
                   class="form-check-input flex-shrink-0 mt-1"
                   wire:model.live="newExcludedFromVeteranQuota">
            <span class="small lh-sm">
                <span class="fw-semibold d-block">هزینه این خدمت از سهمیه ایثارگری مهمان اصلی کسر نشود</span>
                <span class="text-muted" style="font-size:.75rem;">
                    {{ $newExcludedFromVeteranQuota ? 'این خدمت با نرخ کامل محاسبه می‌شود.' : 'در صورت فعال‌سازی، می‌توانید تخفیف دستی با ذکر دلیل ثبت کنید.' }}
                </span>
            </span>
        </label>

        <div class="row g-2 mt-2">
            @if($newExcludedFromVeteranQuota)
            <div class="col-md-3">
                <label class="form-label small mb-1">تخفیف دستی ٪</label>
                <input type="number"
                       wire:model="newManualDiscountPercentage"
                       class="form-control form-control-sm @error('newManualDiscountPercentage') is-invalid @enderror"
                       min="0" max="100" placeholder="۰">
                @error('newManualDiscountPercentage')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-9">
                <label class="form-label small mb-1">دلیل تخفیف @if((int) $newManualDiscountPercentage > 0)<span class="text-danger">*</span>@endif</label>
                <input type="text"
                       wire:model="newManualDiscountReason"
                       class="form-control form-control-sm @error('newManualDiscountReason') is-invalid @enderror"
                       placeholder="مثلاً: پرداخت مستقیم، توافق مدیر، ...">
                @error('newManualDiscountReason')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
