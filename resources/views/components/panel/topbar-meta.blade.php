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

    $crumbCount = count($resolvedBreadcrumbs);
    $currentCrumb = $crumbCount > 0 ? $resolvedBreadcrumbs[$crumbCount - 1] : null;
    $parentCrumb = null;
    for ($i = $crumbCount - 2; $i >= 0; $i--) {
        if (!empty($resolvedBreadcrumbs[$i]['url'])) {
            $parentCrumb = $resolvedBreadcrumbs[$i];
            break;
        }
    }
@endphp

@if($resolvedBreadcrumbs !== [])
<div class="ta-topbar__meta">
    <nav aria-label="breadcrumb" class="ta-topbar__breadcrumb">
        <div class="ta-breadcrumb-compact">
            @if($parentCrumb)
                <a
                    href="{{ $parentCrumb['url'] }}"
                    wire:navigate
                    class="ta-breadcrumb-back"
                    title="{{ $parentCrumb['label'] }}"
                    aria-label="بازگشت به {{ $parentCrumb['label'] }}"
                >
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            @endif

            @if($crumbCount > 2)
                <div class="dropdown">
                    <button
                        type="button"
                        class="ta-breadcrumb-more"
                        data-bs-toggle="dropdown"
                        data-bs-display="static"
                        aria-expanded="false"
                        aria-label="مسیر صفحات"
                    >
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-start">
                        @foreach($resolvedBreadcrumbs as $index => $crumb)
                            @if($index === array_key_last($resolvedBreadcrumbs))
                                @continue
                            @endif
                            <li>
                                @if(!empty($crumb['url']))
                                    <a href="{{ $crumb['url'] }}" wire:navigate class="dropdown-item">{{ $crumb['label'] }}</a>
                                @else
                                    <span class="dropdown-item disabled">{{ $crumb['label'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <span class="ta-breadcrumb-sep" aria-hidden="true">
                    <i class="bi bi-chevron-right"></i>
                </span>
            @endif

            @if($currentCrumb)
                <span class="ta-breadcrumb-btn ta-breadcrumb-btn--current" aria-current="page" title="{{ $currentCrumb['label'] }}">
                    <i class="bi bi-house ta-breadcrumb-btn__icon" aria-hidden="true"></i>
                    <span class="ta-breadcrumb-current-label">{{ $currentCrumb['label'] }}</span>
                </span>
            @endif
        </div>

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
                    <span
                        class="ta-breadcrumb-btn{{ $isCurrent ? ' ta-breadcrumb-btn--current' : '' }}"
                        @if($isCurrent) aria-current="page" @endif
                    >
                        @if($isCurrent)
                            <i class="bi bi-house ta-breadcrumb-btn__icon" aria-hidden="true"></i>
                        @endif
                        {{ $crumb['label'] }}
                    </span>
                @endif
            @endforeach
        </div>
    </nav>
</div>
@endif
