@props([
    'videos' => [],
    'variant' => 'card',
])

@if(config('tutorial_videos.enabled') && count($videos) > 0)
    @if($variant === 'inline')
    <div {{ $attributes->merge(['class' => 'd-flex align-items-center flex-wrap gap-1']) }}>
        <span class="text-muted small"><i class="bi bi-play-circle me-1"></i>آموزش:</span>
        @foreach($videos as $video)
        <a href="{{ asset('videos/' . $video['file']) }}" download="{{ $video['file'] }}" class="btn btn-xs btn-outline-info" style="padding:.2rem .5rem;font-size:.75rem;">
            <i class="bi bi-download me-1"></i>{{ $video['label'] }}
        </a>
        @endforeach
    </div>
    @else
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
@endif
