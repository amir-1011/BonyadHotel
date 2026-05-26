<!DOCTYPE html>
<html lang="fa" dir="rtl" wire:navigate.loading-bar>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل مدیریت') | سامانه رزرو</title>
    <link rel="icon" type="image/png" href="{{ asset('logo/site-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/site-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/vazirmatn/Vazirmatn-font-face.min.css') }}">
    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f1f5f9; }
        :root { --sidebar-w: 260px; }
        #sidebar { width: var(--sidebar-w); min-height: 100vh; background: linear-gradient(180deg,#1e3a5f 0%,#0d2137 100%); position: fixed; top:0; right:0; z-index: 1040; transition: transform .3s; }
        #sidebar .brand { padding: 1.2rem 1rem; border-bottom: 1px solid rgba(255,255,255,.1); }
        #sidebar .nav-link { color: rgba(255,255,255,.75); padding: .6rem 1rem; border-radius: 8px; margin: 2px 8px; font-size: .9rem; }
        #sidebar .nav-link:hover, #sidebar .nav-link.active { background: rgba(255,255,255,.12); color:#fff; }
        #sidebar .nav-link i { width: 22px; text-align: center; }
        #sidebar .section-label { font-size: .7rem; color: rgba(255,255,255,.4); text-transform: uppercase; padding: .5rem 1rem; margin-top: .5rem; letter-spacing: .5px; }
        #main-content { margin-right: var(--sidebar-w); min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e2e8f0; padding: .75rem 1.5rem; }
        .stat-card { border-radius: 14px; border: none; }
        .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        @media(max-width:991px){
            #sidebar { transform: translateX(100%); }
            #sidebar.show { transform: translateX(0); }
            #main-content { margin-right: 0; }
        }
        .table th { font-size: .82rem; color: #64748b; font-weight: 600; }
        .badge { font-size: .75rem; }
    </style>
    @stack('styles')
    @livewireStyles
</head>
<body>

{{-- Sidebar --}}
<div id="sidebar">
    <div class="brand d-flex align-items-center gap-2">
        <div style="background:rgba(255,255,255,.15);border-radius:8px;width:36px;height:36px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-shield-fill text-white"></i>
        </div>
        <div>
            <div class="text-white fw-bold">پنل مدیریت</div>
            <div style="font-size:.7rem;color:rgba(255,255,255,.5)">سوپر ادمین</div>
        </div>
    </div>
    <nav class="mt-2 pb-4">
        <div class="section-label">داشبورد</div>
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2 me-2"></i> داشبورد
        </a>

        <div class="section-label">مدیریت</div>
        <a href="{{ route('admin.users.index') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-people me-2"></i> کاربران
        </a>
        <a href="{{ route('admin.accommodations.index') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.accommodations.*') ? 'active' : '' }}">
            <i class="bi bi-building me-2"></i> اقامتگاه‌ها
        </a>
        <a href="{{ route('admin.bookings.index') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.bookings.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check me-2"></i> رزروها
        </a>
        <a href="{{ route('admin.programs.index') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.programs.*') ? 'active' : '' }}">
            <i class="bi bi-flag me-2"></i> برنامه‌ها و اردوها
        </a>
        <a href="{{ route('admin.programs.supportive-report') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.programs.supportive-report') ? 'active' : '' }}" style="padding-right:2rem;">
            <i class="bi bi-heart-fill me-2 text-danger"></i> خدمات حمایتی
        </a>
        <a href="{{ route('admin.reviews.index') }}" wire:navigate class="nav-link {{ request()->routeIs('admin.reviews.*') ? 'active' : '' }}">
            <i class="bi bi-star me-2"></i> نظرات
        </a>

        <div class="section-label">دسترسی سریع</div>
        <a href="{{ route('home') }}" wire:navigate class="nav-link" target="_blank">
            <i class="bi bi-house me-2"></i> سایت اصلی
        </a>
        <form action="{{ route('auth.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                <i class="bi bi-box-arrow-left me-2"></i> خروج
            </button>
        </form>
    </nav>
</div>

{{-- Main --}}
<div id="main-content">
    <div class="topbar d-flex align-items-center justify-content-between">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('show')">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="text-muted small">@yield('page-title', 'داشبورد')</div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-danger">سوپر ادمین</span>
            <span class="fw-semibold small">{{ Auth::user()->name ?? Auth::user()->mobile }}</span>
        </div>
    </div>

    <div class="p-3 p-lg-4">
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
@livewireScripts
@stack('scripts')
@include('partials._btn_loader')
@include('partials._swal')
</body>
</html>
