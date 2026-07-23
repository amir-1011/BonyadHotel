@props([
    'model',
    'label' => 'افزودن تصاویر جدید',
    'id' => null,
    'showHelp' => true,
])

@php
    $inputId = $id ?? ('image-upload-' . $model);
    $errorKey = str_contains($model, '.') ? $model : ($model . '.*');
@endphp

<div
    class="image-upload-panel"
    data-image-upload-panel
    data-upload-property="{{ $model }}"
    x-data="imageUploadGate(@js($model))"
>
    <label class="form-label small fw-semibold" for="{{ $inputId }}">
        <i class="bi bi-plus-circle me-1"></i>{{ $label }}
        @if($showHelp)
            <span class="text-muted fw-normal">({{ \App\Services\ImageUploadService::helpText() }})</span>
        @endif
    </label>

    <input
        type="file"
        id="{{ $inputId }}"
        wire:model="{{ $model }}"
        class="form-control"
        accept="{{ \App\Services\ImageUploadService::acceptAttribute() }}"
        multiple
        data-max-bytes="{{ \App\Services\ImageUploadService::maxBytes() }}"
    >

    <div class="text-primary small mt-2" x-show="uploadsInFlight > 0" x-cloak>
        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        در حال آپلود تصویر… لطفاً تا پایان آپلود صبر کنید.
    </div>

    <div wire:loading wire:target="{{ $model }}" class="text-muted small mt-1">
        <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        در حال ارسال تصویر به سرور…
    </div>

    @error($errorKey)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror

    @error($model)
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror

    {{ $slot }}
</div>
