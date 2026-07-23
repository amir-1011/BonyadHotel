@props(['paths' => [], 'compact' => false])

@php
    $paths = collect($paths)->filter(fn ($path) => is_string($path) && $path !== '')->values();
@endphp

@if($paths->isNotEmpty())
<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-2' . ($compact ? '' : ' mt-2')]) }}>
    @foreach($paths as $path)
    @php
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isPdf = $ext === 'pdf';
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $isSpreadsheet = in_array($ext, ['csv', 'txt', 'xlsx', 'xls'], true);
        $url = asset('storage/' . $path);
        $label = match (true) {
            $isPdf => 'فایل PDF',
            $isImage => 'تصویر',
            $isSpreadsheet => match ($ext) {
                'csv', 'txt' => 'فایل CSV',
                default => 'فایل Excel',
            },
            default => 'فایل پیوست',
        };
        $icon = match (true) {
            $isPdf => 'file-pdf',
            $isImage => 'file-image',
            $isSpreadsheet => 'file-earmark-spreadsheet',
            default => 'paperclip',
        };
    @endphp
    <a href="{{ $url }}" target="_blank" rel="noopener" download class="btn btn-sm btn-outline-primary">
        <i class="bi bi-{{ $icon }} me-1"></i>
        {{ $label }}@if($paths->count() > 1) {{ $loop->iteration }}@endif
    </a>
    @endforeach
</div>
@endif
