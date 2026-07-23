@props([
    'action',
    'label' => 'ذخیره تغییرات',
    'class' => 'btn btn-primary px-4',
    'uploadTargets' => 'newImages,images',
])

<button
    type="button"
    wire:click="{{ $action }}"
    {{ $attributes->merge(['class' => $class]) }}
    wire:loading.attr="disabled"
    wire:target="{{ $action }},{{ $uploadTargets }}"
    :disabled="uploadsInFlight > 0"
    data-image-upload-submit
>
    <span wire:loading wire:target="{{ $uploadTargets }}" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    <span wire:loading wire:target="{{ $action }}" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
    {{ $label }}
</button>
