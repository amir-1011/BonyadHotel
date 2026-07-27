<!DOCTYPE html>
<html lang="fa" dir="rtl" wire:navigate.loading-bar>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ vasset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <link rel="stylesheet" href="{{ vasset('vendor/tailadmin/tailadmin.css') }}">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        .ta-sidebar { height: 100vh; overflow: hidden; }
        .ta-sidebar__nav { min-height: 0; }
        table:has(thead th.col-index) thead th.col-index,
        table:has(thead th.col-index) tbody tr > td:first-child { display: none !important; }
    </style>
    {{-- Anti-flash: apply saved theme before first paint --}}
    <script>(function(){try{var t=localStorage.getItem('ta-theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');}catch(e){}}());</script>
    <link rel="stylesheet" href="{{ vasset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
    <style>
        .datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
    </style>
    @include('partials._jalali-date-today-styles')
    @include('partials._scrollable-table-styles')
    @stack('styles')
    @livewireStyles
</head>
<body>

{{-- ─── Sidebar ──────────────────────────────────────────────────────── --}}
<aside id="sidebar" class="ta-sidebar">
    <div class="ta-sidebar__brand">
        <div class="ta-sidebar__logo"><i class="bi bi-buildings-fill"></i></div>
        <div class="ta-sidebar__brand-text">
            <div class="ta-sidebar__title">سامانه رزرو</div>
            <div class="ta-sidebar__subtitle">پنل مدیریت</div>
        </div>
        <button type="button" class="ta-sidebar__toggle d-none d-lg-inline-flex" title="جمع/باز کردن منو" aria-label="جمع/باز کردن منو" onclick="window.taToggleCollapse()">
            <i class="bi bi-chevron-right ta-toggle-collapse"></i>
            <i class="bi bi-chevron-left ta-toggle-expand"></i>
        </button>
    </div>

    <nav class="ta-sidebar__nav">
        <div class="ta-sidebar__section">منو</div>
        <a href="{{ route('admin.dashboard') }}" wire:navigate data-label="داشبورد"
           class="ta-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2-fill"></i><span class="ta-nav-link__label">داشبورد</span>
        </a>

        {{-- کاربران --}}
        <a href="{{ route('admin.users.index') }}" wire:navigate data-label="کاربران"
           class="ta-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-people-fill"></i><span class="ta-nav-link__label">کاربران</span>
        </a>

        <a href="{{ route('admin.host-positions.index') }}" wire:navigate data-label="سمت‌ها و دسترسی میزبان"
           class="ta-nav-link {{ request()->routeIs('admin.host-positions.*') ? 'active' : '' }}">
            <i class="bi bi-shield-lock"></i><span class="ta-nav-link__label">سمت‌ها و دسترسی میزبان</span>
        </a>

        {{-- اقامتگاه‌ها (شاخه) --}}
        <div class="ta-nav-group {{ request()->routeIs('admin.accommodations.*') || request()->routeIs('admin.room-types.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="اقامتگاه‌ها" onclick="window.taToggleGroup(this)">
                <i class="bi bi-building-fill"></i>
                <span class="ta-nav-link__label">اقامتگاه‌ها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <ul class="ta-submenu">
                <li><a href="{{ route('admin.accommodations.index') }}" wire:navigate
                       class="{{ request()->routeIs('admin.accommodations.index') || request()->routeIs('admin.accommodations.edit') || request()->routeIs('admin.accommodations.manual-booking') || request()->routeIs('admin.accommodations.import') || request()->routeIs('admin.room-types.*') ? 'active' : '' }}">لیست اقامتگاه‌ها</a></li>
                <li><a href="{{ route('admin.accommodations.create') }}" wire:navigate
                       class="{{ request()->routeIs('admin.accommodations.create') ? 'active' : '' }}">افزودن اقامتگاه</a></li>
                <li><a href="{{ route('admin.accommodations.import') }}" wire:navigate
                       class="{{ request()->routeIs('admin.accommodations.import') ? 'active' : '' }}">درون‌ریزی گروهی (CSV)</a></li>
            </ul>
        </div>

        {{-- رزروها --}}
        <a href="{{ route('admin.bookings.index') }}" wire:navigate data-label="رزروها"
           class="ta-nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check-fill"></i><span class="ta-nav-link__label">رزروها</span>
        </a>

        {{-- درخواست‌های کنسلی --}}
        <a href="{{ route('admin.cancellation-requests.index') }}" wire:navigate data-label="کنسلی و استرداد وجه"
           class="ta-nav-link {{ request()->routeIs('admin.cancellation-requests.*') || request()->routeIs('admin.cancellation-settings') ? 'active' : '' }}">
            <i class="bi bi-x-circle-fill"></i><span class="ta-nav-link__label">کنسلی و استرداد وجه</span>
        </a>

        {{-- کیف پول کارمزد --}}
        <a href="{{ route('admin.commission-wallet') }}" wire:navigate data-label="کیف پول کارمزد"
           class="ta-nav-link {{ request()->routeIs('admin.commission-wallet*') ? 'active' : '' }}">
            <i class="bi bi-wallet2"></i><span class="ta-nav-link__label">کیف پول کارمزد</span>
        </a>

        {{-- تعاریف اولیه (سراسری) --}}
        <a href="{{ route('admin.veteran-policy') }}" wire:navigate data-label="تعاریف اولیه"
           class="ta-nav-link {{ request()->routeIs('admin.veteran-policy') ? 'active' : '' }}">
            <i class="bi bi-shield-check"></i><span class="ta-nav-link__label">تعاریف اولیه</span>
        </a>

        {{-- استان‌ها، شهرها و انواع --}}
        {{-- <a href="{{ route('admin.location-catalog') }}" wire:navigate data-label="استان و شهر"
           class="ta-nav-link {{ request()->routeIs('admin.location-catalog') ? 'active' : '' }}">
            <i class="bi bi-geo-alt-fill"></i><span class="ta-nav-link__label">استان‌ها و انواع</span>
        </a> --}}

        {{-- برنامه‌ها (شاخه) --}}
        <div class="ta-nav-group {{ request()->routeIs('admin.programs.*') ? 'open' : '' }}">
            <button type="button" class="ta-nav-link" data-label="برنامه‌ها و اردوها" onclick="window.taToggleGroup(this)">
                <i class="bi bi-flag-fill"></i>
                <span class="ta-nav-link__label">برنامه‌ها و اردوها</span>
                <i class="bi bi-chevron-down ta-nav-link__arrow"></i>
            </button>
            <ul class="ta-submenu">
                <li><a href="{{ route('admin.programs.index') }}" wire:navigate
                       class="{{ request()->routeIs('admin.programs.index') || request()->routeIs('admin.programs.show') ? 'active' : '' }}">لیست برنامه‌ها</a></li>
                <li><a href="{{ route('admin.programs.create') }}" wire:navigate
                       class="{{ request()->routeIs('admin.programs.create') ? 'active' : '' }}">افزودن برنامه</a></li>
                <li><a href="{{ route('admin.programs.supportive-report') }}" wire:navigate
                       class="{{ request()->routeIs('admin.programs.supportive-report') ? 'active' : '' }}">خدمات حمایتی</a></li>
            </ul>
        </div>

        {{-- نظرات --}}
        <a href="{{ route('admin.reviews.index') }}" wire:navigate data-label="نظرات"
           class="ta-nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-star-fill"></i><span class="ta-nav-link__label">نظرات</span>
        </a>

        <div class="ta-sidebar__section">دسترسی سریع</div>
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
        <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
            <button class="ta-hamburger d-lg-none flex-shrink-0" onclick="window.taToggleSidebar()">
                <i class="bi bi-list fs-5"></i>
            </button>
            <x-panel.topbar-meta panel="admin" :page-title="$pageTitle ?? null" :breadcrumbs="$breadcrumbs ?? null" />
        </div>

        <div class="d-flex align-items-center gap-2 gap-lg-3">
            {{-- <button class="ta-icon-btn ta-theme-btn" type="button" title="حالت تیره" onclick="window.taToggleTheme && window.taToggleTheme()">
                <i class="bi bi-moon"></i>
            </button> --}}
            <a href="{{ route('admin.bookings.index') }}" wire:navigate class="ta-icon-btn" title="اعلان‌ها">
                <i class="bi bi-bell"></i>
                <span class="ta-dot"></span>
            </a>
            <div class="ta-user dropdown">
                <div class="d-flex align-items-center gap-2" onclick="window.taToggleUserDropdown(this, event)" aria-expanded="false" role="button">
                    <div class="ta-user__avatar">{{ mb_substr(Auth::user()->name ?? 'A', 0, 1) }}</div>
                    <div class="d-none d-sm-block text-start">
                        <div class="ta-user__name">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
                        <div class="ta-user__role">سوپر ادمین</div>
                    </div>
                    <i class="bi bi-chevron-down text-muted d-none d-sm-block" style="font-size:.8rem"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-start">
                    @unless(config('staff_mode.enabled'))
                    <li><a class="dropdown-item" href="{{ route('profile.index') }}" wire:navigate><i class="bi bi-person me-2"></i>پروفایل</a></li>
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

    <div class="ta-page @if(request()->routeIs('admin.dashboard')) flex-grow-1 @endif">
        @hasSection('content')
            @yield('content')
        @else
            {{ $slot ?? '' }}
        @endif
    </div>

    @if(request()->routeIs('admin.dashboard'))
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
    window.taToggleGroup = function (btn) {
        var group = btn.closest('.ta-nav-group');
        if (!group) return;
        var isOpen = group.classList.contains('open');
        // accordion: close siblings
        document.querySelectorAll('.ta-nav-group.open').forEach(function (g) {
            if (g !== group) g.classList.remove('open');
        });
        group.classList.toggle('open', !isOpen);
    };
    window.taToggleCollapse = function () {
        var collapsed = document.body.classList.toggle('ta-collapsed');
        try { localStorage.setItem('ta-sidebar-collapsed', collapsed ? '1' : '0'); } catch (e) {}
    };
    // Initial paint; subsequent wire:navigate restores via livewire:navigated below
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
<script src="{{ Vite::asset('resources/js/money-input.js') }}" data-navigate-once></script>
<script src="{{ Vite::asset('resources/js/bnb-room-picker.js') }}" data-navigate-once></script>
<script src="{{ Vite::asset('resources/js/dashboard-accommodation-filter.js') }}" data-navigate-once></script>
<script type="module" src="{{ Vite::asset('resources/js/image-upload-gate.js') }}" data-navigate-once></script>
@livewireScripts
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
@include('partials._btn_loader')
@include('partials._swal')
<script type="module" src="{{ Vite::asset('resources/js/room-type-form.js') }}" data-navigate-once></script>
@include('partials._test_site_notice')
</body>
</html>
