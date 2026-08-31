@props([
    'images' => [],
    'keepImages' => [],
    'featuredImage' => '',
    'canEdit' => true,
    'showRemovalState' => false,
])

@if(!empty($images))
<div {{ $attributes->merge(['class' => 'col-12']) }}>
    <label class="form-label small fw-semibold">
        <i class="bi bi-images me-1"></i>تصاویر
        @if($canEdit)
            <span class="text-muted fw-normal">(برای انتخاب تصویر شاخص روی تصویر کلیک کنید — × برای حذف)</span>
        @endif
    </label>
    <div class="d-flex flex-wrap gap-3">
        @foreach($images as $img)
            @php
                $isKept = ! $showRemovalState || in_array($img, $keepImages, true);
                $isFeatured = $featuredImage === $img && $isKept;
            @endphp
            <div class="text-center" style="position:relative;width:110px;">
                @if($isKept && $canEdit)
                    <button
                        type="button"
                        wire:click="setFeaturedImage(@js($img))"
                        class="btn p-0 border-0 bg-transparent d-block"
                        title="انتخاب به عنوان تصویر شاخص"
                        style="width:110px;"
                    >
                        <img
                            src="{{ asset('storage/' . $img) }}"
                            style="width:110px;height:90px;object-fit:cover;border-radius:8px;border:2px solid {{ $isFeatured ? '#0d6efd' : '#dee2e6' }};{{ $isFeatured ? 'box-shadow:0 0 0 2px rgba(13,110,253,.25);' : '' }}"
                            alt="تصویر"
                        >
                    </button>
                @else
                    <img
                        src="{{ asset('storage/' . $img) }}"
                        style="width:110px;height:90px;object-fit:cover;border-radius:8px;border:2px solid {{ $isKept ? ($isFeatured ? '#0d6efd' : '#dee2e6') : '#dc3545' }};"
                        alt="تصویر"
                    >
                @endif

                @if($isFeatured)
                    <span class="badge bg-primary mt-1" style="font-size:.65rem;">تصویر شاخص</span>
                @endif

                @if($canEdit && $isKept)
                    <button
                        type="button"
                        wire:click="removeExistingImage(@js($img))"
                        data-swal-confirm="این تصویر حذف شود؟"
                        class="btn btn-xs btn-danger"
                        style="position:absolute;top:2px;{{ $showRemovalState ? 'right' : 'left' }}:2px;padding:.1rem .35rem;font-size:.75rem;"
                        title="حذف"
                    >×</button>
                @elseif($showRemovalState && ! $isKept)
                    <div class="text-danger small mt-1">حذف خواهد شد</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
