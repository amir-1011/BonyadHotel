<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'بنیاد — رزرو اقامتگاه')</title>

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
    {{-- Vite compiled CSS (Swiper + AOS + custom) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body class="bnb-page">

{{-- ═══════════════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════════════ --}}
<nav class="bnb-navbar" id="bnbNavbar">
    <div class="container-fluid px-3 px-lg-5">
        <div class="d-flex align-items-center gap-3">

            {{-- Logo (right in RTL) --}}
            <a href="{{ route('home') }}" class="bnb-logo flex-shrink-0">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 1C11.5 1 8 5.5 8 10.5C8 14 9.8 17 12.7 18.8L16 31L19.3 18.8C22.2 17 24 14 24 10.5C24 5.5 20.5 1 16 1Z" fill="#FF385C"/>
                </svg>
                بنیاد
            </a>

            {{-- Compact Search Pill (always visible on md+) --}}
            <div class="bnb-search-pill d-none d-md-flex flex-grow-1 mx-auto" style="max-width:460px;">
                <a href="{{ route('accommodations.index') }}" class="pill-item text-decoration-none" style="border-left:none;border-right:1px solid #DDDDDD;">
                    <span class="pill-label">کجا</span>
                    <span class="pill-value">جستجوی مقصد</span>
                </a>
                <a href="{{ route('accommodations.index') }}" class="pill-item text-decoration-none" style="border-left:none;border-right:1px solid #DDDDDD;">
                    <span class="pill-label">چه زمانی</span>
                    <span class="pill-value">افزودن تاریخ</span>
                </a>
                <a href="{{ route('accommodations.index') }}" class="pill-item text-decoration-none">
                    <span class="pill-label">چند نفر</span>
                    <span class="pill-value">افزودن مهمان</span>
                </a>
                <a href="{{ route('accommodations.index') }}" class="bnb-search-btn text-decoration-none">
                    <i class="bi bi-search" style="color:#fff;font-size:14px;"></i>
                </a>
            </div>

            {{-- Right actions --}}
            <div class="bnb-nav-right me-auto ms-0">
                @auth
                    @if(Auth::user()->hasRole('super_admin'))
                        <a href="{{ route('admin.dashboard') }}" class="bnb-become-host d-none d-lg-inline-block">
                            <i class="bi bi-shield-fill me-1"></i>مدیریت
                        </a>
                    @elseif(Auth::user()->hasRole('host'))
                        <a href="{{ route('host.dashboard') }}" class="bnb-become-host d-none d-lg-inline-block">
                            <i class="bi bi-house-heart me-1"></i>پنل میزبان
                        </a>
                    @else
                        <a href="#" class="bnb-become-host d-none d-lg-inline-block">میزبان شوید</a>
                    @endif
                @else
                    <a href="#" class="bnb-become-host d-none d-lg-inline-block">میزبان شوید</a>
                @endauth

                {{-- User menu --}}
                <div class="dropdown">
                    <button class="bnb-user-menu-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-list" style="font-size:16px;color:var(--bnb-dark);"></i>
                        <div class="bnb-user-avatar">
                            @auth
                                <span>{{ mb_substr(Auth::user()->name ?? Auth::user()->mobile, 0, 1) }}</span>
                            @else
                                <i class="bi bi-person-fill" style="font-size:15px;"></i>
                            @endauth
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-start shadow" style="min-width:200px;border-radius:12px;border:1px solid #DDDDDD;padding:8px 0;">
                        @auth
                            <li class="px-3 py-2">
                                <div class="fw-bold" style="font-size:14px;">{{ Auth::user()->name ?? Auth::user()->mobile }}</div>
                                @if(Auth::user()->discount_percentage > 0)
                                    <span class="badge-bnb mt-1 d-inline-block">{{ Auth::user()->discount_percentage }}٪ تخفیف</span>
                                @endif
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li><a class="dropdown-item py-2" href="{{ route('bookings.index') }}"><i class="bi bi-calendar-check me-2"></i>رزروهای من</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('profile.index') }}"><i class="bi bi-person me-2"></i>پروفایل</a></li>
                            @if(Auth::user()->hasRole('host'))
                                <li><a class="dropdown-item py-2" href="{{ route('host.dashboard') }}"><i class="bi bi-house-heart me-2" style="color:var(--bnb-red);"></i>پنل میزبان</a></li>
                            @endif
                            @if(Auth::user()->hasRole('super_admin'))
                                <li><a class="dropdown-item py-2" href="{{ route('admin.dashboard') }}"><i class="bi bi-shield-fill me-2" style="color:var(--bnb-red);"></i>پنل مدیریت</a></li>
                            @endif
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <form action="{{ route('auth.logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="bi bi-box-arrow-left me-2"></i>خروج
                                    </button>
                                </form>
                            </li>
                        @else
                            <li><a class="dropdown-item py-2 fw-bold" href="{{ route('auth.mobile') }}"><i class="bi bi-phone me-2"></i>ورود / ثبت‌نام</a></li>
                            <li><a class="dropdown-item py-2" href="#"><i class="bi bi-house-add me-2"></i>میزبان شوید</a></li>
                        @endauth
                    </ul>
                </div>
            </div>

        </div>
    </div>
</nav>

{{-- ═══════════════════════════════════════════════════
     FLASH MESSAGES
═══════════════════════════════════════════════════ --}}
@if(session('status') || session('success') || session('error') || $errors->any())
<div class="container-fluid px-3 px-lg-5 pt-3">
    @if(session('status') || session('success'))
        <div class="bnb-alert bnb-alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('status') ?? session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="bnb-alert bnb-alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="bnb-alert bnb-alert-danger">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <ul class="mb-0 mt-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endif

{{-- ═══════════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════════ --}}
@yield('content')

{{-- ═══════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════ --}}
<footer class="bnb-footer">
    <div class="container-fluid px-3 px-lg-5">
        <div class="bnb-footer-grid">
            <div>
                <h6>پشتیبانی</h6>
                <ul>
                    <li><a href="#">مرکز کمک</a></li>
                    <li><a href="#">اطلاعات ایمنی</a></li>
                    <li><a href="#">گزینه‌های لغو</a></li>
                    <li><a href="#">اقامتگاه‌های معلولین</a></li>
                </ul>
            </div>
            <div>
                <h6>اجتماع</h6>
                <ul>
                    <li><a href="#">بنیاد علیه تبعیض</a></li>
                    <li><a href="#">اقامتگاه‌های مناسب</a></li>
                    <li><a href="#">آپارتمان‌های مهمان‌دوست</a></li>
                    <li><a href="#">تجربیات مسافران</a></li>
                </ul>
            </div>
            <div>
                <h6>میزبانی</h6>
                <ul>
                    <li><a href="#">میزبان شوید</a></li>
                    <li><a href="#">منابع میزبانی</a></li>
                    <li><a href="#">انجمن میزبانان</a></li>
                    <li><a href="#">میزبانی مسئولانه</a></li>
                </ul>
            </div>
            <div>
                <h6>بنیاد</h6>
                <ul>
                    <li><a href="#">اخبار</a></li>
                    <li><a href="#">درباره ما</a></li>
                    <li><a href="#">فرصت‌های شغلی</a></li>
                    <li><a href="#">تماس با ما</a></li>
                </ul>
            </div>
        </div>
        <div class="bnb-footer-bottom">
            <div>
                &copy; {{ date('Y') }} بنیاد — سامانه رزرو اقامتگاه
            </div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <a href="#" class="text-decoration-none" style="color:var(--bnb-gray);">شرایط استفاده</a>
                <span style="color:var(--bnb-gray);">·</span>
                <a href="#" class="text-decoration-none" style="color:var(--bnb-gray);">حریم خصوصی</a>
                <span style="color:var(--bnb-gray);">·</span>
                <a href="#" class="text-decoration-none" style="color:var(--bnb-gray);">نقشه سایت</a>
            </div>
            <div class="bnb-footer-social">
                <a href="#" title="اینستاگرام"><i class="bi bi-instagram"></i></a>
                <a href="#" title="تلگرام"><i class="bi bi-telegram"></i></a>
                <a href="#" title="توییتر"><i class="bi bi-twitter-x"></i></a>
            </div>
        </div>
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
// ─── تبدیل اعداد لاتین به فارسی ────────────────────────────────────────────
(function () {
    var fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    var SKIP = ['SCRIPT','STYLE','INPUT','TEXTAREA','SELECT','CODE','PRE'];
    function convertNode(root) {
        var walker = document.createTreeWalker(root || document.body, NodeFilter.SHOW_TEXT, {
            acceptNode: function(n) {
                if (SKIP.indexOf(n.parentElement && n.parentElement.tagName) !== -1) return NodeFilter.FILTER_REJECT;
                return /[0-9]/.test(n.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            }
        });
        var nodes = [], node;
        while ((node = walker.nextNode())) nodes.push(node);
        nodes.forEach(function(n) {
            n.nodeValue = n.nodeValue.replace(/[0-9]/g, function(d) { return fa[d]; });
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        convertNode();
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

// ─── Shared Jalali inline range picker ───────────────────────────────────────
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
            $(displaySel).html('<span class="fw-bold" style="color:var(--bnb-red)">' + jalStr(ci) + '</span><span class="text-muted mx-2 small">← حالا تاریخ خروج را انتخاب کنید</span>');
        } else {
            $(displaySel).html('<i class="bi bi-check-circle-fill me-1" style="color:var(--bnb-red)"></i><span class="fw-bold">' + jalStr(ci) + '</span><span class="mx-2 text-muted">تا</span><span class="fw-bold">' + jalStr(co) + '</span>');
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
