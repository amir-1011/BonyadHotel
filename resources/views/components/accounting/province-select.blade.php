@props([
    'provinces' => [],
    'label' => 'استان (کدینگ حسابداری)',
    'hint' => 'پیش‌فرض از استان اقامتگاه انتخاب‌شده است؛ در صورت نیاز می‌توانید تغییر دهید.',
    'showCodePreview' => false,
    'previewCode' => '—',
    'indicatorLabel' => null,
])

<div {{ $attributes->class(['col-12']) }}>
    <label class="form-label small text-muted">{{ $label }} <span class="text-danger">*</span></label>
    <select wire:model.live="accountingProvinceId" class="form-select @error('accountingProvinceId') is-invalid @enderror">
        <option value="">انتخاب استان...</option>
        @foreach($provinces as $province)
            <option value="{{ $province->id }}">
                {{ $province->name }}
                @if($province->accounting_code)
                    ({{ $province->accounting_code }})
                @endif
            </option>
        @endforeach
    </select>
    @error('accountingProvinceId')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @if($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    @if($showCodePreview)
        <div class="alert alert-light border small mt-2 mb-0 py-2">
            <i class="bi bi-upc-scan me-1 text-primary"></i>
            پیش‌نمایش کد حسابداری:
            <strong dir="ltr">{{ $previewCode }}</strong>
            @if($indicatorLabel)
                <span class="text-muted">— {{ $indicatorLabel }}</span>
            @endif
        </div>
    @endif
</div>
