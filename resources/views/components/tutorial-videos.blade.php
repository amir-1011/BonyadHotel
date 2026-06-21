@props([
    'videos' => [],
])

@if(count($videos) > 0)
<div {{ $attributes->merge(['class' => 'card shadow-sm mb-3 border-info border-opacity-25']) }}>
    <div class="card-body py-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="text-muted small fw-semibold"><i class="bi bi-play-circle me-1"></i>ویدیوهای آموزشی:</span>
            @foreach($videos as $video)
            <a href="{{ asset('videos/' . $video['file']) }}" download="{{ $video['file'] }}" class="btn btn-sm btn-outline-info">
                <i class="bi bi-download me-1"></i>{{ $video['label'] }}
            </a>
            @endforeach
        </div>
    </div>
</div>
@endif
