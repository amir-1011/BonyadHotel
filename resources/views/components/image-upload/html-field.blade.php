@props([
    'name' => 'new_images[]',
    'label' => 'تصاویر جدید',
    'previewTarget' => 'data-room-images-preview',
    'inputTarget' => 'data-room-images-input',
    'showHelp' => true,
])

<div class="image-upload-html-field">
    @if($label)
        <label class="form-label small fw-semibold">{{ $label }}</label>
    @endif

    <input
        type="file"
        name="{{ $name }}"
        {{ $inputTarget }}
        class="form-control"
        accept="{{ \App\Services\ImageUploadService::acceptAttribute() }}"
        multiple
        data-max-bytes="{{ \App\Services\ImageUploadService::maxBytes() }}"
        data-max-files="{{ \App\Services\ImageUploadService::MAX_FILES_PER_REQUEST }}"
    >

    <div {{ $previewTarget }} class="d-flex flex-wrap gap-2 mt-2"></div>

    @if($showHelp)
        <div class="form-text">فرمت‌های JPG, PNG, WebP — {{ \App\Services\ImageUploadService::helpText() }}</div>
    @endif
</div>
