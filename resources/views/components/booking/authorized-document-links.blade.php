@props(['urls' => [], 'btnClass' => 'btn-outline-primary', 'icon' => 'download', 'label' => 'دانلود'])

@if(collect($urls)->filter()->isNotEmpty())
<div {{ $attributes->merge(['class' => 'd-flex flex-wrap gap-2']) }}>
    @foreach($urls as $index => $url)
    @if($url)
    <a href="{{ $url }}" download class="btn btn-sm {{ $btnClass }}">
        <i class="bi bi-{{ $icon }} me-1"></i>{{ $label }}@if(count($urls) > 1) {{ $index + 1 }}@endif
    </a>
    @endif
    @endforeach
</div>
@endif
