<!DOCTYPE html>
<html lang="fa" dir="rtl" class="ta-ios" wire:navigate.loading-bar>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل میزبان') | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/tailadmin/tailadmin.css') }}">
    <style>
        body { font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, sans-serif; }
        .ta-sidebar__nav { min-height: 0; }
        .col-index { display: none !important; }
    </style>
    {{-- Anti-flash: apply saved theme before first paint --}}
    <script>(function(){try{var t=localStorage.getItem('ta-theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');}catch(e){}}());</script>
    <link rel="stylesheet" href="{{ vasset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
    </style>
    @include('partials._jalali-date-today-styles')
    @include('partials._hidden-scrollbar-styles')
    @include('partials._scrollable-table-styles')
    @include('partials._panel-responsive-styles')
    @include('partials._panel-ios-styles')
    @stack('styles')
    @include('partials._persian-digits-script')
    @livewireStyles
</head>
<body class="ta-ios">

@php
    $hostUser = Auth::user();
    $hostPerms = $hostUser->effectiveHostPermissions();
    $pendingReplies = $hostUser->hostCan('reviews.list', 'read')
        ? \App\Models\Review::whereIn('accommodation_id', $hostUser->managedAccommodationIds())->whereNull('host_reply')->count()
        : 0;
@endphp

{{-- ─── Sidebar ──────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="ta-sidebar">
    <div class="ta-sidebar__brand">
        <x-panel.brand-logo />
        <div class="ta-sidebar__brand-text">
            <div class="ta-sidebar__kicker">پنل میزبان</div>
            <div class="ta-sidebar__title">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
        </div>
        <x-panel.sidebar-toggle />
    </div>

    <nav class="ta-sidebar__nav">
        <div class="ta-sidebar__section">اصلی</div>
        @if(\App\Support\HostPermissions::grantsHaveDashboardReadAccess($hostUser->effectiveHostPermissionGrants()))
        <a href="{{ route('host.dashboard') }}" wire:navigate data-label="داشبورد"
           class="ta-nav-link {{ request()->routeIs('host.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid"></i><span class="ta-nav-link__label">داشبورد</span>
        </a>
        @endif

        <div class="ta-sidebar__section">مدیریت</div>

        @if($hostUser->hasHostPanelAccess('accommodations'))
        <div class="ta-nav-group {{ request()->routeIs('host.accommodations.*') || request()->routeIs('host.room-types.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="اقامتگاه‌ها" aria-expanded="{{ request()->routeIs('host.accommodations.*') || request()->routeIs('host.room-types.*') ? 'true' : 'false' }}" onclick="window.taToggleGroup(this)">
                <i class="bi bi-building"></i>
                <span class="ta-nav-link__label">اقامتگاه‌ها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <div class="ta-submenu-panel">
            <ul class="ta-submenu">
                @if($hostUser->hostCan('accommodations.list', 'read'))
                <li><a href="{{ route('host.accommodations.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.accommodations.index') || request()->routeIs('host.accommodations.edit') || request()->routeIs('host.room-types.*') ? 'active' : '' }}">اقامتگاه‌های من</a></li>
                @endif
                @if($hostUser->hostCan('accommodations.create', 'write'))
                <li><a href="{{ route('host.accommodations.create') }}" wire:navigate
                       class="{{ request()->routeIs('host.accommodations.create') ? 'active' : '' }}">افزودن اقامتگاه</a></li>
                @endif
            </ul>
            </div>
        </div>
        @endif

        @php
            $hostHasBookingsList = $hostUser->hasHostPanelAccess('bookings') && $hostUser->hostCan('bookings.list', 'read');
            $hostHasPaymentRecords = $hostUser->hasHostPanelAccess('bookings') && $hostUser->hostCan('bookings.list', 'read');
            $hostHasMedicalReport = $hostUser->hasHostPanelAccess('bookings') && $hostUser->hostCan('bookings.medical-accommodation-report', 'read');
            $hostHasCancellation = $hostUser->hasHostPanelAccess('bookings') && $hostUser->hostCan('cancellation-requests.list', 'read');
            $hostBookingsNavOpen = request()->routeIs('host.bookings.*')
                || request()->routeIs('host.booking-payment-records.*')
                || request()->routeIs('host.medical-accommodation-report')
                || request()->routeIs('host.cancellation-requests.*');
        @endphp
        @if($hostHasBookingsList || $hostHasMedicalReport || $hostHasCancellation)
        <div class="ta-nav-group {{ $hostBookingsNavOpen ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="رزروها" aria-expanded="{{ $hostBookingsNavOpen ? 'true' : 'false' }}" onclick="window.taToggleGroup(this)">
                <i class="bi bi-calendar-check"></i>
                <span class="ta-nav-link__label">رزروها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <div class="ta-submenu-panel">
            <ul class="ta-submenu">
                @if($hostHasBookingsList)
                <li><a href="{{ route('host.bookings.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.bookings.*') ? 'active' : '' }}">لیست رزروها</a></li>
                @endif
                @if($hostHasPaymentRecords)
                <li><a href="{{ route('host.booking-payment-records.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.booking-payment-records.*') ? 'active' : '' }}">تراکنش‌های مالی</a></li>
                @endif
                @if($hostHasMedicalReport)
                <li><a href="{{ route('host.medical-accommodation-report') }}" wire:navigate
                       class="{{ request()->routeIs('host.medical-accommodation-report') ? 'active' : '' }}">اسکان درمانی</a></li>
                @endif
                @if($hostHasCancellation)
                <li><a href="{{ route('host.cancellation-requests.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.cancellation-requests.*') ? 'active' : '' }}">کنسلی و استرداد وجه</a></li>
                @endif
            </ul>
            </div>
        </div>
        @endif

        <div class="ta-sidebar__section">خدمات</div>

        @if($hostUser->hasHostPanelAccess('programs'))
        <div class="ta-nav-group {{ request()->routeIs('host.programs.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="برنامه‌ها و اردوها" aria-expanded="{{ request()->routeIs('host.programs.*') ? 'true' : 'false' }}" onclick="window.taToggleGroup(this)">
                <i class="bi bi-flag"></i>
                <span class="ta-nav-link__label">برنامه‌ها و اردوها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <div class="ta-submenu-panel">
            <ul class="ta-submenu">
                @if($hostUser->hostCan('programs.list', 'read'))
                <li><a href="{{ route('host.programs.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.index') || request()->routeIs('host.programs.show') || request()->routeIs('host.programs.edit') ? 'active' : '' }}">لیست برنامه‌ها</a></li>
                @endif
                @if($hostUser->hostCan('programs.create', 'write'))
                <li><a href="{{ route('host.programs.create') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.create') ? 'active' : '' }}">افزودن برنامه</a></li>
                @endif
                @if($hostUser->hostCan('programs.supportive-report', 'read'))
                <li><a href="{{ route('host.programs.supportive-report') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.supportive-report') ? 'active' : '' }}">خدمات حمایتی</a></li>
                @endif
            </ul>
            </div>
        </div>
        @endif

        @if($hostUser->hostCan('reviews.list', 'read'))
        <a href="{{ route('host.reviews.index') }}" wire:navigate data-label="نظرات مهمانان"
           class="ta-nav-link {{ request()->routeIs('host.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-star"></i><span class="ta-nav-link__label">نظرات مهمانان</span>
            @if($pendingReplies > 0)
                <span class="badge bg-warning">{{ $pendingReplies }}</span>
            @endif
        </a>
        @endif

        @if($hostUser->hostCan('users.list', 'read'))
        <a href="{{ route('host.users.index') }}" wire:navigate data-label="کاربران"
           class="ta-nav-link {{ request()->routeIs('host.users.*') ? 'active' : '' }}">
            <i class="bi bi-people"></i><span class="ta-nav-link__label">کاربران</span>
        </a>
        @endif

        @if($hostUser->hasHostPanelAccess('facility-management'))
        <div class="ta-nav-group {{ request()->routeIs('host.facility.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="مدیریت اماکن" aria-expanded="{{ request()->routeIs('host.facility.*') ? 'true' : 'false' }}" onclick="window.taToggleGroup(this)">
                <i class="bi bi-box-seam"></i>
                <span class="ta-nav-link__label">مدیریت اماکن</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <div class="ta-submenu-panel">
            <ul class="ta-submenu">
                @if($hostUser->hostCan('facility-surplus.list', 'read'))
                <li><a href="{{ route('host.facility.surplus.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.facility.surplus.*') ? 'active' : '' }}">اقلام مازاد</a></li>
                @endif
                @if($hostUser->hostCan('facility-needed.list', 'read'))
                <li><a href="{{ route('host.facility.needed.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.facility.needed.*') ? 'active' : '' }}">اقلام مورد نیاز</a></li>
                @endif
            </ul>
            </div>
        </div>
        @endif

        <div class="ta-sidebar__section">دسترسی سریع</div>
        <a href="{{ route('host.profile') }}" wire:navigate data-label="پروفایل"
           class="ta-nav-link {{ request()->routeIs('host.profile') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i><span class="ta-nav-link__label">پروفایل</span>
        </a>
        @unless(config('staff_mode.enabled'))
        <a href="{{ route('home') }}" target="_blank" data-label="سایت اصلی" class="ta-nav-link">
            <i class="bi bi-house-door"></i><span class="ta-nav-link__label">سایت اصلی</span>
        </a>
        @endunless
    </nav>
    <div class="ta-sidebar__foot">
        <form action="{{ route('auth.logout') }}" method="POST">
            @csrf
            <button type="submit" class="ta-nav-link ta-nav-link--logout" data-label="خروج">
                <i class="bi bi-box-arrow-right"></i><span class="ta-nav-link__label">خروج</span>
            </button>
        </form>
    </div>
</aside>
<div id="sidebarBackdrop" class="ta-backdrop" onclick="window.taToggleSidebar(false)"></div>

{{-- ─── Main ─────────────────────────────────────────────────────────── --}}
<div class="ta-main">
    <header class="ta-topbar">
        <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
            <button type="button" class="ta-hamburger d-lg-none flex-shrink-0" onclick="window.taToggleSidebar()" aria-label="باز کردن منو">
                <i class="bi bi-list fs-5"></i>
            </button>
            <x-panel.topbar-meta panel="host" :page-title="$pageTitle ?? null" :breadcrumbs="$breadcrumbs ?? null" />
        </div>

        <div class="d-flex align-items-center gap-2 gap-lg-3">
            {{-- <button class="ta-icon-btn ta-theme-btn" type="button" title="حالت تیره" onclick="window.taToggleTheme && window.taToggleTheme()">
                <i class="bi bi-moon"></i>
            </button> --}}
            @if($hostUser->hostCan('reviews.list', 'read'))
            <a href="{{ route('host.reviews.index') }}" wire:navigate class="ta-icon-btn" title="نظرات">
                <i class="bi bi-bell"></i>
                @if($pendingReplies > 0)<span class="ta-dot"></span>@endif
            </a>
            @endif
            <div class="ta-user dropdown">
                <div class="d-flex align-items-center gap-2" onclick="window.taToggleUserDropdown(this, event)" aria-expanded="false" role="button">
                    <div class="ta-user__avatar">{{ mb_substr(Auth::user()->name ?? 'M', 0, 1) }}</div>
                    <div class="d-none d-sm-block text-start">
                        <div class="ta-user__name">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
                        <div class="ta-user__role">{{ Auth::user()->hostRoleLabel() }}</div>
                    </div>
                    <i class="bi bi-chevron-down text-muted d-none d-sm-block" style="font-size:.8rem"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-start">
                    <li><a class="dropdown-item" href="{{ route('host.profile') }}" wire:navigate><i class="bi bi-person me-2"></i>پروفایل</a></li>
                    @unless(config('staff_mode.enabled'))
                    <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank"><i class="bi bi-house-door me-2"></i>سایت اصلی</a></li>
                    <li><hr class="dropdown-divider"></li>
                    @endunless
                    <li>
                        <form action="{{ route('auth.logout') }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>خروج</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <div class="ta-page @if(request()->routeIs('host.dashboard') || request()->routeIs('host.medical-accommodation-report')) flex-grow-1 @endif">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </div>

    @if(request()->routeIs('host.dashboard') || request()->routeIs('host.medical-accommodation-report'))
        @include('partials._panel_footer')
    @endif
</div>

<script src="{{ vasset('vendor/jquery/jquery.min.js') }}" data-navigate-once></script>
<script src="{{ vasset('vendor/bootstrap/bootstrap.bundle.min.js') }}" data-navigate-once></script>
<script type="module" src="{{ Vite::asset('resources/js/bootstrap-collapse-navigate.js') }}" data-navigate-once></script>
<script src="{{ vasset('vendor/persian-date/persian-date.min.js') }}" data-navigate-once></script>
<script src="{{ vasset('vendor/persian-datepicker/persian-datepicker.min.js') }}" data-navigate-once></script>
<script type="module" src="{{ Vite::asset('resources/js/jalali-date-today.js') }}" data-navigate-once></script>
<script src="{{ Vite::asset('resources/js/cancellation-settle-datepicker.js') }}" data-navigate-once></script>
<script data-navigate-once>
window.bnbJalaliCal = window.bnbJalaliCal || {
    satFirstColumnForJsDow(jsGetDay) { return (jsGetDay + 1) % 7; },
    monthStartOffset(jYear, jMonth) {
        const greg = this.toGregorian(jYear, jMonth, 1);
        return this.satFirstColumnForJsDow(new Date(greg + 'T12:00:00').getDay());
    },
    toGregorian(jYear, jMonth, jDay) {
        const dt = new persianDate([jYear, jMonth, jDay]).toDate();
        const d = new Date(dt.getFullYear(), dt.getMonth(), dt.getDate(), 12, 0, 0);
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    },
    toGregorianYm(jYear, jMonth) {
        return this.toGregorian(jYear, jMonth, 1).slice(0, 7);
    },
};
</script>
<script data-navigate-once>
    window.taToggleSidebar = function (force) {
        var sb = document.getElementById('sidebar');
        var bd = document.getElementById('sidebarBackdrop');
        var show = typeof force === 'boolean' ? force : !sb.classList.contains('show');
        sb.classList.toggle('show', show);
        bd.classList.toggle('show', show);
    };
    window.taScrollNavGroup = function (group) {
        var nav = document.querySelector('.ta-sidebar__nav');
        if (!nav || !group || document.body.classList.contains('ta-collapsed')) return;
        var navRect = nav.getBoundingClientRect();
        var groupRect = group.getBoundingClientRect();
        var pad = 12;
        var delta = 0;
        if (groupRect.bottom > navRect.bottom - pad) {
            delta = groupRect.bottom - navRect.bottom + pad;
        } else if (groupRect.top < navRect.top + pad) {
            delta = groupRect.top - navRect.top - pad;
        }
        if (Math.abs(delta) > 1) {
            nav.scrollBy({ top: delta, behavior: 'smooth' });
        }
    };
    window.taToggleGroup = function (btn) {
        var group = btn.closest('.ta-nav-group');
        if (!group) return;
        var willOpen = !group.classList.contains('open');
        document.querySelectorAll('.ta-nav-group.open').forEach(function (g) {
            if (g !== group) {
                g.classList.remove('open');
                var otherBtn = g.querySelector(':scope > .ta-nav-link');
                if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
            }
        });
        group.classList.toggle('open', willOpen);
        btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        if (!willOpen) return;
        var panel = group.querySelector('.ta-submenu-panel');
        var done = function () { window.taScrollNavGroup(group); };
        requestAnimationFrame(function () { requestAnimationFrame(done); });
        if (!panel) return;
        var onEnd = function (e) {
            if (e.propertyName && e.propertyName !== 'grid-template-rows' && e.propertyName !== 'max-height') return;
            panel.removeEventListener('transitionend', onEnd);
            done();
        };
        panel.addEventListener('transitionend', onEnd);
        setTimeout(function () {
            panel.removeEventListener('transitionend', onEnd);
            done();
        }, 500);
    };
    window.taToggleCollapse = function () {
        var collapsed = document.body.classList.toggle('ta-collapsed');
        try { localStorage.setItem('ta-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
    };
    (function () {
        try {
            if (localStorage.getItem('ta-sidebar-collapsed') === '1') {
                document.body.classList.add('ta-collapsed');
            }
        } catch (e) {}
    })();
    window.taToggleUserDropdown = function (el, e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        bootstrap.Dropdown.getOrCreateInstance(el).toggle();
    };
    function taResetUserDropdowns() {
        document.querySelectorAll('.ta-topbar .ta-user.dropdown > [role="button"]').forEach(function (el) {
            var inst = bootstrap.Dropdown.getInstance(el);
            if (inst) inst.dispose();
        });
    }
    document.addEventListener('livewire:navigated', function () {
        try {
            document.body.classList.toggle('ta-collapsed', localStorage.getItem('ta-sidebar-collapsed') === '1');
            // Re-apply theme (wire:navigate morphs <html> from server, wiping data-bs-theme)
            var saved = localStorage.getItem('ta-theme');
            document.documentElement.setAttribute('data-bs-theme', saved === 'dark' ? 'dark' : 'light');
        } catch (e) {}
        taSyncThemeBtn();
        taResetUserDropdowns();
    });
    // ── Dark / Light theme ──
    function taSyncThemeBtn() {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        document.querySelectorAll('.ta-theme-btn').forEach(function(btn) {
            btn.title = dark ? 'حالت روشن' : 'حالت تیره';
            var icon = btn.querySelector('i');
            if (icon) icon.className = dark ? 'bi bi-sun' : 'bi bi-moon';
        });
    }
    window.taToggleTheme = function () {
        var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        var next = dark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem('ta-theme', next); } catch (e) {}
        taSyncThemeBtn();
    };
    taSyncThemeBtn();
</script>
<script src="{{ Vite::asset('resources/js/bnb-room-picker.js') }}" data-navigate-once></script>
<script src="{{ Vite::asset('resources/js/dashboard-accommodation-filter.js') }}" data-navigate-once></script>
<script type="module" src="{{ Vite::asset('resources/js/image-upload-gate.js') }}" data-navigate-once></script>
@livewireScripts
<script src="{{ Vite::asset('resources/js/money-input.js') }}" data-navigate-once></script>
<script data-navigate-once>
(function () {
    function restoreJquery$() {
        if (window.jQuery) window.$ = window.jQuery;
    }
    restoreJquery$();
    document.addEventListener('livewire:initialized', restoreJquery$);
    document.addEventListener('livewire:navigated', restoreJquery$);
})();
</script>
@stack('scripts')
@include('partials._bootstrap_modal_livewire_guard')
@include('partials._stay_extension_scripts')
@include('partials._btn_loader')
@include('partials._manual-booking-slide')
@include('partials._swal')
<script type="module" src="{{ Vite::asset('resources/js/room-type-form.js') }}" data-navigate-once></script>
@include('partials._test_site_notice')
@include('partials._panel-page-transition')
</body>
</html>
