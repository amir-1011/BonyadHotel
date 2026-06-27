{{-- Shared booking management: services + form upload --}}
<div class="card shadow-sm mt-3">
    <div class="card-header bg-white fw-semibold small d-flex justify-content-between align-items-center">
        <span><i class="bi bi-bag-check me-2"></i>مدیریت خدمات و فرم رزرو</span>
        <a href="{{ route($panel . '.bookings.pdf', $booking) }}" target="_blank" class="btn btn-xs btn-outline-success" style="font-size:.75rem;">
            <i class="bi bi-file-pdf me-1"></i>دانلود PDF
        </a>
    </div>
    <div class="card-body">
        {{-- Existing services --}}
        @if($booking->services->isNotEmpty())
        <form wire:submit="saveServiceEdits">
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>خدمت</th>
                            <th>قیمت واحد</th>
                            <th>تعداد</th>
                            <th>جمع (قبل تخفیف)</th>
                            <th>تخفیف</th>
                            <th>مبلغ نهایی</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($editableServices as $id => $row)
                        @php
                            $subtotal = (int)($row['unit_price'] ?? 0) * (int)($row['quantity'] ?? 1);
                            $discountAmt = (int)($row['discount_amount'] ?? 0);
                            $finalTotal = (int)($row['total'] ?? $subtotal);
                        @endphp
                        <tr wire:key="es-{{ $id }}">
                            <td><input type="text" wire:model="editableServices.{{ $id }}.name" class="form-control form-control-sm"></td>
                            <td><x-money-input wire:model="editableServices.{{ $id }}.unit_price" class="form-control form-control-sm" min="0" /></td>
                            <td><input type="number" wire:model="editableServices.{{ $id }}.quantity" class="form-control form-control-sm" min="1"></td>
                            <td class="small text-muted">{{ number_format($subtotal) }}</td>
                            <td class="small text-danger">
                                @if($discountAmt > 0)
                                − {{ number_format($discountAmt) }}
                                @else
                                —
                                @endif
                            </td>
                            <td class="small fw-semibold">{{ number_format($finalTotal) }}</td>
                            <td>
                                @if(!empty($row['id']))
                                <button type="button" wire:click="removeServiceLine({{ $row['id'] }})" data-swal-confirm="این خدمت حذف شود؟" class="btn btn-xs btn-outline-danger"><i class="bi bi-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($booking->veteran_type_applied)
            <div class="alert alert-info small py-2 mb-3">
                <i class="bi bi-info-circle me-1"></i>
                تخفیف خدمات بر اساس گروه ایثارگری <strong>{{ $booking->veteranLabelApplied() }}</strong> محاسبه می‌شود.
                با ذخیره تغییرات، مبالغ تخفیف خودکار به‌روز می‌گردند.
            </div>
            @endif
            <button type="submit" class="btn btn-sm btn-primary mb-3">ذخیره تغییرات خدمات</button>
        </form>
        @endif

        {{-- Add service --}}
        <div class="border rounded p-3 bg-light mb-3">
            <div class="small fw-semibold mb-2">افزودن خدمت جدید</div>
            @php
                $serviceCatalog = app(\App\Services\VeteranPolicyService::class)->forAccommodation($booking->accommodation_id)->activeServices();
                $selectedNewCatalog = $newServiceCatalogId && $newServiceCatalogId !== 'custom'
                    ? $serviceCatalog->firstWhere('id', (int) $newServiceCatalogId)
                    : null;
                $newCatalogVariants = $selectedNewCatalog?->variants ?? collect();
            @endphp
            <div class="row g-2 align-items-end">
                <div class="col-md-2">
                    <select wire:model.live="newServiceCatalogId" class="form-select form-select-sm">
                        <option value="">— انتخاب —</option>
                        @foreach($serviceCatalog as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                        <option value="custom">سایر (دستی)</option>
                    </select>
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
                <div class="col-md-{{ $newCatalogVariants->isNotEmpty() ? 3 : 4 }}"><input type="text" wire:model="newServiceName" class="form-control form-control-sm" placeholder="نام خدمت"></div>
                <div class="col-md-2"><x-money-input wire:model="newServicePrice" class="form-control form-control-sm" placeholder="قیمت" min="0" /></div>
                <div class="col-md-1"><input type="number" wire:model="newServiceQty" class="form-control form-control-sm" min="1"></div>
                <div class="col-md-2"><button type="button" wire:click="addServiceLine" class="btn btn-sm btn-success w-100">افزودن</button></div>
            </div>
        </div>

        {{-- Form upload --}}
        <div class="border rounded p-3">
            <div class="small fw-semibold mb-2"><i class="bi bi-upload me-1"></i>فرم رزرو امضا‌شده</div>
            @if($booking->form_file_path)
            <div class="d-flex align-items-center gap-2 mb-2">
                <a href="{{ asset('storage/' . $booking->form_file_path) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye me-1"></i>مشاهده فایل</a>
                <button type="button" wire:click="deleteBookingForm" class="btn btn-sm btn-outline-danger" data-swal-confirm="فایل حذف شود؟">حذف</button>
            </div>
            @endif
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <input type="file" wire:model="uploadedForm" class="form-control form-control-sm" style="max-width:280px" accept=".pdf,.jpg,.jpeg,.png">
                <button type="button" wire:click="uploadBookingForm" class="btn btn-sm btn-primary" wire:loading.attr="disabled" wire:target="uploadedForm,uploadBookingForm">آپلود</button>
            </div>
            @error('uploadedForm')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
