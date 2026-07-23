@props(['hint' => 'تغییرات پس از «اعمال» روی داشبورد اعمال می‌شود'])

@if($this->showDashboardAccommodationFilter())
<div
    class="dropdown dashboard-acc-filter"
    wire:ignore
    x-data="dashboardAccommodationFilter()"
    x-init="boot(@js($this->dashboardAccommodationAllSelected), @js($this->selectedAccommodationIds))"
    x-on:dashboard-accommodation-filter-applied.window="syncFromApplied($event.detail)">
    <button
        type="button"
        class="btn btn-light dropdown-toggle d-inline-flex align-items-center gap-2"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        data-bs-offset="0,8"
        data-bs-placement="bottom-end"
        aria-expanded="false">
        <i class="bi bi-building"></i>
        <span class="text-truncate dashboard-acc-filter__label" style="max-width:220px">{{ $this->dashboardAccommodationFilterLabel() }}</span>
        <span
            class="badge rounded-pill bg-warning text-dark"
            style="font-size:.62rem"
            x-show="hasPending()"
            x-cloak>!</span>
    </button>
    <div
        class="dropdown-menu dropdown-menu-end shadow-sm p-0 dashboard-acc-filter__menu"
        data-bs-auto-close="outside"
        x-on:click.stop
        x-ref="menu">
        <div class="px-3 py-2 border-bottom bg-light">
            <div class="fw-semibold small">فیلتر اقامتگاه</div>
            <div class="text-muted" style="font-size:.72rem">{{ $hint }}</div>
        </div>
        <div class="p-2 border-bottom d-flex gap-2">
            <button
                type="button"
                x-on:click.stop="selectAll()"
                class="btn btn-sm btn-outline-primary flex-fill">
                <i class="bi bi-check-all me-1"></i>انتخاب همه
            </button>
            <button
                type="button"
                x-on:click.stop="clear()"
                class="btn btn-sm btn-outline-secondary flex-fill">
                <i class="bi bi-eraser me-1"></i>پاک کردن
            </button>
        </div>
        <div class="px-2 pt-2" x-show="hasPending()" x-cloak>
            <button
                type="button"
                x-on:click.stop="resetDraft()"
                class="btn btn-sm btn-link text-decoration-none w-100 p-0"
                style="font-size:.75rem">
                <i class="bi bi-arrow-counterclockwise me-1"></i>بازگشت به فیلتر فعلی
            </button>
        </div>
        <div class="dashboard-acc-filter__list py-1">
            @foreach($dashboardAccommodationOptions as $option)
            <label
                class="dashboard-acc-filter__item d-flex align-items-center gap-2 py-2 px-3 small mb-0"
                style="cursor:pointer"
                data-acc-id="{{ $option['id'] }}"
                data-acc-name="{{ $option['name'] }}">
                <input
                    type="checkbox"
                    class="form-check-input flex-shrink-0 m-0"
                    :checked="isSelected({{ $option['id'] }})"
                    x-on:click.stop="toggle({{ $option['id'] }})">
                <span class="text-truncate" title="{{ $option['name'] }}">{{ $option['name'] }}</span>
            </label>
            @endforeach
        </div>
        <div class="p-2 border-top bg-light">
            <button
                type="button"
                x-on:click.stop="apply()"
                class="btn btn-sm btn-primary w-100"
                :disabled="!hasPending()">
                <i class="bi bi-funnel me-1"></i>اعمال فیلتر
            </button>
        </div>
    </div>
</div>
@endif

@once
@push('styles')
<style>
    .dashboard-acc-filter {
        position: relative;
    }

    .dashboard-acc-filter .dropdown-menu,
    .dashboard-acc-filter .dropdown-menu.show {
        z-index: 1065 !important;
        width: min(360px, calc(100vw - var(--ta-sidebar-w, 290px) - 24px));
        min-width: min(300px, calc(100vw - var(--ta-sidebar-w, 290px) - 24px));
        max-width: calc(100vw - var(--ta-sidebar-w, 290px) - 24px);
    }

    @media (min-width: 992px) {
        body.ta-collapsed .dashboard-acc-filter .dropdown-menu,
        body.ta-collapsed .dashboard-acc-filter .dropdown-menu.show {
            width: min(360px, calc(100vw - var(--ta-sidebar-collapsed-w, 90px) - 24px));
            min-width: min(300px, calc(100vw - var(--ta-sidebar-collapsed-w, 90px) - 24px));
            max-width: calc(100vw - var(--ta-sidebar-collapsed-w, 90px) - 24px);
        }
    }

    .dashboard-acc-filter__list {
        max-height: min(320px, calc(100vh - 240px));
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .dashboard-acc-filter__item:hover {
        background-color: #f9fafb;
    }

    @media (max-width: 575.98px) {
        .dashboard-acc-filter .dropdown-menu.dashboard-acc-filter__menu--fixed {
            left: 12px !important;
            right: 12px !important;
            width: auto !important;
            min-width: 0 !important;
            max-width: none !important;
        }
    }

    [x-cloak] {
        display: none !important;
    }
</style>
@endpush
@endonce
