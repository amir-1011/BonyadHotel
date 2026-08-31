{{-- انواع / زیرمجموعه‌های یک خدمت — تخفیف ایثارگری روی خدمت والد اعمال می‌شود --}}
@props(['service', 'variantAccommodationsByKey' => []])

<div class="border-top bg-light px-3 py-3">
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="small fw-semibold text-muted">
            <i class="bi bi-diagram-3 me-1"></i>انواع «{{ $service['name'] }}»
            <span class="fw-normal">(تخفیف از تب «تخفیف خدمات» روی خدمت والد)</span>
        </div>
    </div>

    @if(!empty($service['variants']))
    <div class="table-responsive mb-2">
        <table class="table table-sm align-middle mb-0 bg-white">
            <thead class="table-light">
                <tr>
                    <th>نام نوع</th>
                    <th style="width:140px">قیمت (ریال)</th>
                    <th style="width:60px">فعال</th>
                    <th style="width:50px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($service['variants'] as $vi => $variant)
                <tr wire:key="svc-{{ $service['key'] }}-var-{{ $variant['key'] ?? $vi }}">
                    <td>
                        <input type="hidden" wire:model="services.{{ $service['key'] }}.variants.{{ $vi }}.key">
                        <input type="text"
                               wire:model="services.{{ $service['key'] }}.variants.{{ $vi }}.name"
                               class="form-control form-control-sm"
                               placeholder="مثلاً: استخر نشاط">
                        @if(!empty($variant['key']) && !empty($variantAccommodationsByKey))
                        <x-veteran-policy.accommodation-badges
                            :accommodations="$variantAccommodationsByKey[$variant['key']] ?? []"
                        />
                        @endif
                    </td>
                    <td>
                        <x-money-input wire:model="services.{{ $service['key'] }}.variants.{{ $vi }}.price"
                                       min="0" class="form-control form-control-sm" />
                    </td>
                    <td class="text-center">
                        <input type="checkbox"
                               wire:model="services.{{ $service['key'] }}.variants.{{ $vi }}.is_active"
                               class="form-check-input">
                    </td>
                    <td class="text-end">
                        @if(!empty($variant['id']))
                        <button type="button"
                                wire:click="removeServiceVariant({{ $variant['id'] }})"
                                data-swal-confirm="این نوع حذف شود؟"
                                class="btn btn-xs btn-outline-danger"
                                title="حذف">
                            <i class="bi bi-trash"></i>
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <p class="text-muted small mb-2">حداقل یک نوع با قیمت تعریف کنید — مثلاً «استخر نشاط» با قیمت ۵۰۰ هزار ریال.</p>
    @endif

    <div class="row g-2 align-items-end" wire:key="new-variant-draft-{{ $service['key'] }}">
        <div class="col-md-5">
            <label class="form-label small mb-1">نام نوع جدید</label>
            <input type="text"
                   class="form-control form-control-sm"
                   placeholder="مثلاً: استخر پارک آبی خورشید"
                   wire:model="newVariantDrafts.{{ $service['id'] }}.name"
                   wire:keydown.enter.prevent="$wire.call('addServiceVariant', {{ $service['id'] }})">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">قیمت</label>
            <x-money-input wire:model="newVariantDrafts.{{ $service['id'] }}.price" min="0" class="form-control form-control-sm" />
        </div>
        <div class="col-md-4">
            <button type="button"
                    wire:click="addServiceVariant({{ $service['id'] }})"
                    class="btn btn-sm btn-outline-success w-100">
                <i class="bi bi-plus-lg me-1"></i>افزودن نوع
            </button>
        </div>
    </div>
</div>
