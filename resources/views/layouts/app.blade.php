<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'سامانه رزرو اقامتگاه')</title>

    {{-- Bootstrap 5 RTL --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    {{-- Vazirmatn Font --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.min.css">
    {{-- Leaflet.js --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    {{-- Persian Datepicker (Jalali/Shamsi calendar) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    {{-- Select2 --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.rtl.min.css">

    <style>
        body { font-family: 'Vazirmatn', sans-serif; background: #f8f9fa; font-feature-settings: "ss02"; }
        * { font-feature-settings: "ss02"; }
        .navbar-brand { font-weight: 700; font-size: 1.3rem; }
        .card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.07); }
        .btn { border-radius: 8px; }
        #map { height: 280px; border-radius: 12px; }
        @media (min-width: 768px) { #map { height: 350px; } }
        .accommodation-card:hover { transform: translateY(-3px); transition: .2s; }
        .badge-veteran { background: linear-gradient(135deg,#1e7e34,#28a745); color:#fff; }
        .price-tag { font-size: 1.1rem; font-weight: 700; color: #0d6efd; }
        @media (min-width: 576px) { .price-tag { font-size: 1.3rem; } }
        .discount-tag { font-size: .85rem; }
        .tracking-code { letter-spacing: 1px; font-family: 'Vazirmatn', sans-serif; font-size: 1rem; word-break: break-all; font-feature-settings: "ss02"; }
        @media (min-width: 576px) { .tracking-code { letter-spacing: 2px; font-size: 1.2rem; } }
        /* Persian Datepicker */
        .datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; z-index: 9999 !important; }
        /* Inline Range Picker */
        .range-picker-trigger { cursor: pointer; user-select: none; transition: border-color .15s; min-height: 42px; }
        .range-picker-trigger:hover { border-color: #0d6efd !important; }
        .range-picker-cal .datepicker-plot-area { width: 100% !important; border-radius: 8px; border: 1px solid #dee2e6 !important; box-shadow: 0 4px 16px rgba(0,0,0,.1) !important; }
        .range-picker-phase { font-size: .75rem; margin-top: 4px; }
        @media (max-width: 575px) {
            .container { padding-left: 12px; padding-right: 12px; }
            .card { border-radius: 10px; }
            .nav-tabs .nav-link { padding: .4rem .6rem; font-size: .9rem; }
        }
    </style>

    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}">
            <i class="bi bi-house-door-fill me-1"></i> رزرو اقامتگاه
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('accommodations.index') }}">
                        <i class="bi bi-search me-1"></i>جستجوی اقامتگاه
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            {{ Auth::user()->name ?? Auth::user()->mobile }}
                            @if(Auth::user()->discount_percentage > 0)
                                <span class="badge badge-veteran ms-1">{{ Auth::user()->discount_percentage }}% تخفیف</span>
                            @endif
                        </a>
                        <ul class="dropdown-menu dropdown-menu-start">
                            @if(Auth::user()->hasRole('super_admin'))
                                <li><a class="dropdown-item text-danger" href="{{ route('admin.dashboard') }}"><i class="bi bi-shield-fill me-2"></i>پنل مدیریت</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            @if(Auth::user()->hasRole('host'))
                                <li><a class="dropdown-item text-success" href="{{ route('host.dashboard') }}"><i class="bi bi-house-heart me-2"></i>پنل میزبان</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('profile.index') }}"><i class="bi bi-person me-2"></i>پروفایل</a></li>
                            <li><a class="dropdown-item" href="{{ route('bookings.index') }}"><i class="bi bi-calendar-check me-2"></i>رزروهای من</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('auth.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-box-arrow-left me-2"></i>خروج
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link btn btn-outline-light px-3 ms-2" href="{{ route('auth.mobile') }}">
                            <i class="bi bi-phone me-1"></i>ورود / ثبت‌نام
                        </a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4">
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<footer class="bg-dark text-light py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0 small">سامانه رزرو اقامتگاه &copy; {{ date('Y') }}</p>
    </div>
</footer>

{{-- JS Libraries --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/fa.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
// ─── تبدیل اعداد لاتین به فارسی در تمام صفحه ───────────────────────────────
(function () {
    var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    var SKIP = ['SCRIPT','STYLE','INPUT','TEXTAREA','SELECT','CODE','PRE'];

    function convertNode(root) {
        var walker = document.createTreeWalker(
            root || document.body,
            NodeFilter.SHOW_TEXT,
            { acceptNode: function(n) {
                if (SKIP.indexOf(n.parentElement && n.parentElement.tagName) !== -1) return NodeFilter.FILTER_REJECT;
                return /[0-9]/.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }}
        );
        var nodes = [], node;
        while ((node = walker.nextNode())) nodes.push(node);
        nodes.forEach(function(n) {
            n.nodeValue = n.nodeValue.replace(/[0-9]/g, function(d) { return fa[d]; });
        });
    }

    // اجرا بعد از لود صفحه
    document.addEventListener('DOMContentLoaded', function () {
        convertNode();
        // نظارت بر تغییرات داینامیک (پیش‌نمایش قیمت، نتایج AJAX، ...)
        new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(added) {
                    if (added.nodeType === 1) convertNode(added);
                    else if (added.nodeType === 3 && /[0-9]/.test(added.nodeValue)) {
                        var tag = added.parentElement && added.parentElement.tagName;
                        if (SKIP.indexOf(tag) === -1)
                            added.nodeValue = added.nodeValue.replace(/[0-9]/g, function(d){ return fa[d]; });
                    }
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    });
})();
// Shared Jalali inline range picker — کلیک اول: ورود، کلیک دوم: خروج
function initJalaliRange(calSel, inSel, outSel, displaySel, initIn, initOut, onRangeSelected) {
    var phase = 0;
    var startUnix = null;
    function toGreg(unix) {
        var d = new Date(unix);
        return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    }
    function jalStr(gregStr) {
        return new persianDate(new Date(gregStr + 'T12:00:00')).format('YYYY/MM/DD');
    }
    function refreshDisplay() {
        var ci = $(inSel).val(), co = $(outSel).val();
        if (!ci) {
            $(displaySel).html('<span class="text-muted">تاریخ ورود را انتخاب کنید</span>');
        } else if (!co) {
            $(displaySel).html('<span class="fw-bold text-primary">' + jalStr(ci) + '</span><span class="text-muted mx-2 small">← حالا تاریخ خروج را انتخاب کنید</span>');
        } else {
            $(displaySel).html('<i class="bi bi-check-circle-fill text-success me-1"></i><span class="fw-bold">' + jalStr(ci) + '</span><span class="mx-2 text-muted">تا</span><span class="fw-bold">' + jalStr(co) + '</span>');
        }
    }
    if (initIn) { startUnix = new Date(initIn + 'T12:00:00').getTime(); phase = initOut ? 0 : 1; }
    refreshDisplay();
    $(calSel).persianDatepicker({
        inline: true,
        calendar: { persian: { locale: 'fa' } },
        format: 'YYYY/MM/DD',
        minDate: new persianDate().valueOf(),
        onSelect: function(unix) {
            if (phase === 0) {
                startUnix = unix;
                $(inSel).val(toGreg(unix)); $(outSel).val('');
                phase = 1; refreshDisplay();
            } else {
                if (unix > startUnix) {
                    $(outSel).val(toGreg(unix));
                    phase = 0; startUnix = null; refreshDisplay();
                    $(calSel).closest('.collapse').collapse('hide');
                    if (typeof onRangeSelected === 'function') onRangeSelected();
                } else {
                    startUnix = unix;
                    $(inSel).val(toGreg(unix)); $(outSel).val('');
                    refreshDisplay();
                }
            }
        }
    });
}
</script>

@stack('scripts')
</body>
</html>
