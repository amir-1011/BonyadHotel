@props([
    'panel' => 'admin',
    'pageTitle' => null,
    'breadcrumbs' => null,
])

@php
    use App\Support\PanelBreadcrumbBuilder;

    $resolvedBreadcrumbs = $breadcrumbs ?? PanelBreadcrumbBuilder::build($panel);

    $resolvedTitle = $pageTitle;
    if (!$resolvedTitle && trim($__env->yieldContent('pageTitle', '')) !== '') {
        $resolvedTitle = trim($__env->yieldContent('pageTitle'));
    }
    if (!$resolvedTitle && trim($__env->yieldContent('title', '')) !== '') {
        $resolvedTitle = trim($__env->yieldContent('title'));
    }

    if ($resolvedTitle) {
        if ($resolvedBreadcrumbs !== []) {
            $last = array_key_last($resolvedBreadcrumbs);
            $resolvedBreadcrumbs[$last]['label'] = $resolvedTitle;
            $resolvedBreadcrumbs[$last]['url'] = null;
        } else {
            $resolvedBreadcrumbs = [['label' => $resolvedTitle, 'url' => null]];
        }
    }
@endphp

@if($resolvedBreadcrumbs !== [])
<div class="ta-topbar__meta">
    <nav aria-label="breadcrumb" class="ta-topbar__breadcrumb">
        <div class="ta-breadcrumb-trail">
            @foreach($resolvedBreadcrumbs as $index => $crumb)
                @php $isCurrent = $index === array_key_last($resolvedBreadcrumbs); @endphp

                @if($index > 0)
                    <span class="ta-breadcrumb-sep" aria-hidden="true">
                        <i class="bi bi-chevron-right"></i>
                    </span>
                @endif

                @if($crumb['url'] && !$isCurrent)
                    <a href="{{ $crumb['url'] }}" wire:navigate class="ta-breadcrumb-btn">{{ $crumb['label'] }}</a>
                @else
                    <span class="ta-breadcrumb-btn ta-breadcrumb-btn--current" @if($isCurrent) aria-current="page" @endif>
                        {{ $crumb['label'] }}
                    </span>
                @endif
            @endforeach
        </div>
    </nav>
</div>
@endif
