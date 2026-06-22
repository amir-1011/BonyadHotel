<!DOCTYPE html>
<html lang="fa" dir="rtl" wire:navigate.loading-bar>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل میزبان') | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ asset('logo/site-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/tailadmin/tailadmin.css') }}">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
    {{-- Anti-flash: apply saved theme before first paint --}}
    <script>(function(){try{var t=localStorage.getItem('ta-theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');}catch(e){}}());</script>
    @stack('styles')
    @livewireStyles
</head>
<body>

@php
    $hostUser = Auth::user();
    $hostPerms = $hostUser->effectiveHostPermissions();
    $pendingReplies = $hostUser->hasHostPanelAccess('reviews')
        ? \App\Models\Review::whereIn('accommodation_id', $hostUser->managedAccommodationIds())->whereNull('host_reply')->count()
        : 0;
@endphp

{{-- ─── Sidebar ──────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="ta-sidebar">
    <div class="ta-sidebar__brand">
        <div class="ta-sidebar__logo"><i class="bi bi-house-heart-fill"></i></div>
        <div>
            <div class="ta-sidebar__title">پنل میزبان</div>
            <div class="ta-sidebar__subtitle">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
        </div>
    </div>

    <nav class="ta-sidebar__nav">
        <div class="ta-sidebar__section">منو</div>
        @if($hostUser->hasHostPanelAccess('dashboard'))
        <a href="{{ route('host.dashboard') }}" wire:navigate data-label="داشبورد"
           class="ta-nav-link {{ request()->routeIs('host.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2-fill"></i><span class="ta-nav-link__label">داشبورد</span>
        </a>
        @endif

        @if($hostUser->hasHostPanelAccess('accommodations'))
        <div class="ta-nav-group {{ request()->routeIs('host.accommodations.*') || request()->routeIs('host.room-types.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="اقامتگاه‌ها" onclick="window.taToggleGroup(this)">
                <i class="bi bi-building-fill"></i>
                <span class="ta-nav-link__label">اقامتگاه‌ها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <ul class="ta-submenu">
                <li><a href="{{ route('host.accommodations.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.accommodations.index') || request()->routeIs('host.accommodations.edit') || request()->routeIs('host.room-types.*') ? 'active' : '' }}">اقامتگاه‌های من</a></li>
                <li><a href="{{ route('host.accommodations.create') }}" wire:navigate
                       class="{{ request()->routeIs('host.accommodations.create') ? 'active' : '' }}">افزودن اقامتگاه</a></li>
            </ul>
        </div>
        @endif

        @if($hostUser->hasHostPanelAccess('bookings'))
        <a href="{{ route('host.bookings.index') }}" wire:navigate data-label="رزروها"
           class="ta-nav-link {{ request()->routeIs('host.bookings.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check-fill"></i><span class="ta-nav-link__label">رزروها</span>
        </a>
        @endif

        @if($hostUser->hasHostPanelAccess('programs'))
        <div class="ta-nav-group {{ request()->routeIs('host.programs.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="برنامه‌ها و اردوها" onclick="window.taToggleGroup(this)">
                <i class="bi bi-flag-fill"></i>
                <span class="ta-nav-link__label">برنامه‌ها و اردوها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <ul class="ta-submenu">
                <li><a href="{{ route('host.programs.index') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.index') || request()->routeIs('host.programs.show') || request()->routeIs('host.programs.edit') ? 'active' : '' }}">لیست برنامه‌ها</a></li>
                <li><a href="{{ route('host.programs.create') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.create') ? 'active' : '' }}">افزودن برنامه</a></li>
                <li><a href="{{ route('host.programs.supportive-report') }}" wire:navigate
                       class="{{ request()->routeIs('host.programs.supportive-report') ? 'active' : '' }}">خدمات حمایتی</a></li>
            </ul>
        </div>
        @endif

        @if($hostUser->hasHostPanelAccess('reviews'))
        <a href="{{ route('host.reviews.index') }}" wire:navigate data-label="نظرات مهمانان"
           class="ta-nav-link {{ request()->routeIs('host.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-star-fill"></i><span class="ta-nav-link__label">نظرات مهمانان</span>
            @if($pendingReplies > 0)
                <span class="badge bg-warning">{{ $pendingReplies }}</span>
            @endif
        </a>
        @endif

        @if($hostUser->hasHostPanelAccess('users'))
        <a href="{{ route('host.users.index') }}" wire:navigate data-label="کاربران"
           class="ta-nav-link {{ request()->routeIs('host.users.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i><span class="ta-nav-link__label">کاربران</span>
        </a>
        @endif

        <div class="ta-sidebar__section">دسترسی سریع</div>
        <a href="{{ route('host.profile') }}" wire:navigate data-label="پروفایل"
           class="ta-nav-link {{ request()->routeIs('host.profile') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i><span class="ta-nav-link__label">پروفایل</span>
        </a>
        @unless(config('staff_mode.enabled'))
        <a href="{{ route('home') }}" target="_blank" data-label="سایت اصلی" class="ta-nav-link">
            <i class="bi bi-house-door-fill"></i><span class="ta-nav-link__label">سایت اصلی</span>
        </a>
        @endunless
        <form action="{{ route('auth.logout') }}" method="POST">
            @csrf
            <button type="submit" class="ta-nav-link" data-label="خروج">
                <i class="bi bi-box-arrow-right"></i><span class="ta-nav-link__label">خروج</span>
            </button>
        </form>
    </nav>
</aside>
<div id="sidebarBackdrop" class="ta-backdrop" onclick="window.taToggleSidebar(false)"></div>

{{-- ─── Main ─────────────────────────────────────────────────────────── --}}
<div class="ta-main">
    <header class="ta-topbar">
        <div class="d-flex align-items-center gap-3 flex-grow-1">
            <button class="ta-hamburger d-lg-none" onclick="window.taToggleSidebar()">
                <i class="bi bi-list fs-5"></i>
            </button>
            <button class="ta-collapse-btn" type="button" title="جمع/باز کردن منو" onclick="window.taToggleCollapse()">
                <i class="bi bi-list fs-5"></i>
            </button>
            <div class="ta-search d-none d-md-block">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="جستجو یا تایپ دستور..." aria-label="جستجو">
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <button class="ta-icon-btn ta-theme-btn" type="button" title="حالت تیره" onclick="window.taToggleTheme && window.taToggleTheme()">
                <i class="bi bi-moon"></i>
            </button>
            @if($hostUser->hasHostPanelAccess('reviews'))
            <a href="{{ route('host.reviews.index') }}" wire:navigate class="ta-icon-btn" title="نظرات">
                <i class="bi bi-bell"></i>
                @if($pendingReplies > 0)<span class="ta-dot"></span>@endif
            </a>
            @endif
            <div class="ta-user dropdown">
                <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false" role="button">
                    <div class="ta-user__avatar">{{ mb_substr(Auth::user()->name ?? 'M', 0, 1) }}</div>
                    <div class="d-none d-sm-block text-start">
                        <div class="ta-user__name">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
                        <div class="ta-user__role">میزبان</div>
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

    <div class="ta-page">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </div>
</div>

<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/persian-date/persian-date.min.js') }}"></script>
<script src="{{ asset('vendor/persian-datepicker/persian-datepicker.min.js') }}"></script>
<script>
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
<script>
    window.taToggleSidebar = function (force) {
        var sb = document.getElementById('sidebar');
        var bd = document.getElementById('sidebarBackdrop');
        var show = typeof force === 'boolean' ? force : !sb.classList.contains('show');
        sb.classList.toggle('show', show);
        bd.classList.toggle('show', show);
    };
    window.taToggleGroup = function (btn) {
        var group = btn.closest('.ta-nav-group');
        if (!group) return;
        var isOpen = group.classList.contains('open');
        document.querySelectorAll('.ta-nav-group.open').forEach(function (g) {
            if (g !== group) g.classList.remove('open');
        });
        group.classList.toggle('open', !isOpen);
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
    document.addEventListener('livewire:navigated', function () {
        try {
            document.body.classList.toggle('ta-collapsed', localStorage.getItem('ta-sidebar-collapsed') === '1');
            // Re-apply theme (wire:navigate morphs <html> from server, wiping data-bs-theme)
            var saved = localStorage.getItem('ta-theme');
            document.documentElement.setAttribute('data-bs-theme', saved === 'dark' ? 'dark' : 'light');
        } catch (e) {}
        taSyncThemeBtn();
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
<script src="{{ Vite::asset('resources/js/money-input.js') }}"></script>
<script src="{{ Vite::asset('resources/js/room-type-form.js') }}"></script>
@livewireScripts
@stack('scripts')
@include('partials._btn_loader')
@include('partials._swal')
@include('partials._test_site_notice')
</body>
</html>
