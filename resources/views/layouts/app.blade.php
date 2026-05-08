@php
    $navLocations = \App\Models\Province::with('cities')->orderBy('name')->get()->flatMap(function($p) {
        $items = [['type'=>'province','id'=>$p->id,'name'=>$p->name,'province'=>'','province_id'=>null]];
        foreach ($p->cities as $c) {
            $items[] = ['type'=>'city','id'=>$c->id,'name'=>$c->name,'province'=>$p->name,'province_id'=>$p->id];
        }
        return $items;
    })->values()->toArray();
@endphp
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

    {{-- Search popup + x-cloak --}}
    <style>
    [x-cloak]{display:none!important}

    /* ══ Expanded search bar ══════════════════════════════════ */
    .bnb-search-wrap { position: relative; z-index: 1055; }
    .bnb-search-expanded {
        display: flex; align-items: center;
        background: #fff;
        border: 1px solid var(--bnb-border);
        border-radius: 40px;
        box-shadow: 0 6px 20px rgba(0,0,0,.12);
        height: 56px;
        overflow: visible;
        position: relative; z-index: 1055;
    }
    .bnb-stab {
        flex: 1; display: flex; flex-direction: column; align-items: flex-end;
        padding: 8px 18px; background: transparent; border: none;
        border-radius: 32px; cursor: pointer;
        transition: background .15s; min-width: 0;
    }
    .bnb-stab:hover { background: #f0f0f0; }
    .bnb-stab.bnb-stab-active { background: #fff; box-shadow: 0 2px 16px rgba(0,0,0,.16); }
    .bnb-stab-label { font-size: 11px; font-weight: 700; color: var(--bnb-dark); white-space: nowrap; }
    .bnb-stab-val   { font-size: 12px; color: var(--bnb-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; }
    .bnb-stab-div   { width: 1px; height: 24px; background: var(--bnb-border); flex-shrink: 0; }
    .bnb-search-go  {
        background: var(--bnb-red); color: #fff; border: none; border-radius: 24px;
        padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer;
        display: flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0;
        font-family: var(--bnb-font); transition: background .15s; margin: 4px 4px 4px 0;
    }
    .bnb-search-go:hover { background: #E31C5F; }

    /* ══ Dropdown panel ═══════════════════════════════════════ */
    .bnb-search-drop {
        position: fixed; top: 76px; left: 50%; transform: translateX(-50%);
        background: #fff; border-radius: 24px;
        box-shadow: 0 16px 56px rgba(0,0,0,.18);
        padding: 28px 32px;
        min-width: 480px; max-width: 640px; width: 90vw;
        z-index: 1060;
    }
    .bnb-drop-title {
        font-size: 11px; font-weight: 700; color: var(--bnb-dark);
        letter-spacing: .5px; text-transform: uppercase; margin-bottom: 14px;
    }

    /* Location input */
    .bnb-loc-wrap {
        display: flex; align-items: center; gap: 8px;
        border: 1.5px solid var(--bnb-border); border-radius: 12px;
        padding: 10px 14px; margin-bottom: 14px; background: var(--bnb-bg-light);
        transition: border-color .15s;
    }
    .bnb-loc-wrap:focus-within { border-color: var(--bnb-dark); background: #fff; }
    .bnb-loc-input {
        flex: 1; border: none; background: transparent; font-size: 15px;
        color: var(--bnb-dark); font-family: var(--bnb-font); outline: none; text-align: right;
    }
    .bnb-loc-clear { background: none; border: none; color: var(--bnb-gray); cursor: pointer; font-size: 20px; line-height: 1; padding: 0; }

    /* Suggestions list */
    .bnb-suggestions { max-height: 280px; overflow-y: auto; }
    .bnb-sug-btn {
        display: flex; align-items: center; gap: 14px; width: 100%;
        padding: 11px 10px; border: none; background: transparent; border-radius: 12px;
        cursor: pointer; transition: background .1s; text-align: right; font-family: var(--bnb-font);
    }
    .bnb-sug-btn:hover { background: var(--bnb-bg-light); }
    .bnb-sug-icon {
        width: 38px; height: 38px; background: #ebebeb; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0;
    }
    .bnb-sug-name { font-size: 14px; font-weight: 600; color: var(--bnb-dark); }
    .bnb-sug-sub  { font-size: 12px; color: var(--bnb-gray); }

    /* Guest counter */
    .bnb-guest-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 18px 0; border-bottom: 1px solid var(--bnb-border);
    }
    .bnb-guest-row:last-child { border-bottom: none; }
    .bnb-counter { display: flex; align-items: center; gap: 18px; }
    .bnb-cnt-btn {
        width: 32px; height: 32px; border-radius: 50%; border: 1.5px solid #b0b0b0;
        background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: #717171; transition: border-color .15s, color .15s; line-height: 1;
    }
    .bnb-cnt-btn:hover:not([disabled]) { border-color: var(--bnb-dark); color: var(--bnb-dark); }
    .bnb-cnt-btn[disabled] { opacity: .3; cursor: not-allowed; }

    /* Backdrop */
    .bnb-search-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1050;
    }

    /* Alpine transitions */
    .sf-enter { transition: opacity .2s ease, transform .2s ease; }
    .sf-enter-from { opacity: 0; }
    .sf-enter-to { opacity: 1; }
    .sf-leave { transition: opacity .15s ease; }
    .sf-leave-from { opacity: 1; }
    .sf-leave-to { opacity: 0; }
    .sd-enter { transition: opacity .2s ease, margin-top .2s ease; }
    .sd-enter-from { opacity: 0; margin-top: -10px; }
    .sd-enter-to { opacity: 1; margin-top: 0; }
    .sd-leave { transition: opacity .15s ease; }
    .sd-leave-from { opacity: 1; }
    .sd-leave-to { opacity: 0; }

    /* Calendar inside panel */
    .bnb-search-drop .datepicker-plot-area { font-size: 13px; }
    </style>

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

            {{-- ── Airbnb-style Interactive Search (Alpine.js) ───────────── --}}
            <div class="bnb-search-wrap d-none d-md-flex flex-grow-1 mx-auto" style="max-width:620px;"
                 x-data="bnbNavSearch()"
                 @keydown.escape.window="close()">

                {{-- Compact pill (default state) --}}
                <div class="bnb-search-pill w-100"
                     x-show="!open"
                     x-transition:enter="sf-enter" x-transition:enter-start="sf-enter-from" x-transition:enter-end="sf-enter-to"
                     x-transition:leave="sf-leave" x-transition:leave-start="sf-leave-from" x-transition:leave-end="sf-leave-to"
                     style="display:flex;">
                    <button type="button" class="pill-item" style="border-left:none;border-right:1px solid #DDDDDD;" @click.stop="activate('where')">
                        <span class="pill-label">کجا</span>
                        <span class="pill-value" x-text="locationLabel"></span>
                    </button>
                    <button type="button" class="pill-item" style="border-left:none;border-right:1px solid #DDDDDD;" @click.stop="activate('when')">
                        <span class="pill-label">چه زمانی</span>
                        <span class="pill-value" x-text="dateLabel"></span>
                    </button>
                    <button type="button" class="pill-item" @click.stop="activate('who')">
                        <span class="pill-label">چند نفر</span>
                        <span class="pill-value" x-text="guests + ' نفر'"></span>
                    </button>
                    <button type="button" class="bnb-search-btn" @click.stop="submit()">
                        <i class="bi bi-search" style="color:#fff;font-size:14px;"></i>
                    </button>
                </div>

                {{-- Expanded bar (open state) --}}
                <div class="bnb-search-expanded w-100" x-show="open" x-cloak
                     x-transition:enter="sf-enter" x-transition:enter-start="sf-enter-from" x-transition:enter-end="sf-enter-to"
                     x-transition:leave="sf-leave" x-transition:leave-start="sf-leave-from" x-transition:leave-end="sf-leave-to">
                    <button type="button" class="bnb-stab" :class="{'bnb-stab-active': step==='where'}" @click.stop="activate('where')">
                        <div class="bnb-stab-label">مقصد</div>
                        <div class="bnb-stab-val" x-text="locationLabel"></div>
                    </button>
                    <div class="bnb-stab-div"></div>
                    <button type="button" class="bnb-stab" :class="{'bnb-stab-active': step==='when'}" @click.stop="activate('when')">
                        <div class="bnb-stab-label">ورود</div>
                        <div class="bnb-stab-val" x-text="checkIn ? jalaliStr(checkIn) : 'افزودن تاریخ'"></div>
                    </button>
                    <div class="bnb-stab-div"></div>
                    <button type="button" class="bnb-stab" :class="{'bnb-stab-active': step==='when' && checkIn}" @click.stop="activate('when')">
                        <div class="bnb-stab-label">خروج</div>
                        <div class="bnb-stab-val" x-text="checkOut ? jalaliStr(checkOut) : 'افزودن تاریخ'"></div>
                    </button>
                    <div class="bnb-stab-div"></div>
                    <button type="button" class="bnb-stab bnb-stab-last" :class="{'bnb-stab-active': step==='who'}" @click.stop="activate('who')">
                        <div class="bnb-stab-label">مهمان</div>
                        <div class="bnb-stab-val" x-text="guests + ' نفر'"></div>
                    </button>
                    <button type="button" class="bnb-search-go" @click.stop="submit()">
                        <i class="bi bi-search"></i>جستجو
                    </button>
                </div>

                {{-- Dropdown panel --}}
                <div class="bnb-search-drop" x-show="open" x-cloak
                     x-transition:enter="sd-enter" x-transition:enter-start="sd-enter-from" x-transition:enter-end="sd-enter-to"
                     x-transition:leave="sd-leave" x-transition:leave-start="sd-leave-from" x-transition:leave-end="sd-leave-to">

                    {{-- WHERE --}}
                    <div x-show="step==='where'">
                        <p class="bnb-drop-title">مقصد کجاست؟</p>
                        <div class="bnb-loc-wrap">
                            <i class="bi bi-search" style="color:var(--bnb-gray);font-size:15px;flex-shrink:0;"></i>
                            <input type="text" class="bnb-loc-input" placeholder="جستجوی استان یا شهر..."
                                   x-model="locationQuery" @input="filterLocations()" x-ref="locInput" autocomplete="off">
                            <button type="button" class="bnb-loc-clear" x-show="locationQuery" @click="locationQuery='';suggestions=allLocations.slice(0,8);">&times;</button>
                        </div>
                        <div class="bnb-suggestions">
                            <template x-for="loc in suggestions" :key="loc.type+'-'+loc.id">
                                <button type="button" class="bnb-sug-btn" @click="selectLocation(loc)">
                                    <div class="bnb-sug-icon">
                                        <i :class="loc.type==='province'?'bi bi-map':'bi bi-geo-alt-fill'"></i>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="bnb-sug-name" x-text="loc.name"></div>
                                        <div class="bnb-sug-sub" x-show="loc.type==='city'" x-text="loc.province"></div>
                                        <div class="bnb-sug-sub" x-show="loc.type==='province'">استان</div>
                                    </div>
                                </button>
                            </template>
                            <div x-show="suggestions.length===0" style="text-align:center;padding:20px;color:var(--bnb-gray);font-size:14px;">نتیجه‌ای یافت نشد</div>
                        </div>
                    </div>

                    {{-- WHEN --}}
                    <div x-show="step==='when'">
                        <p class="bnb-drop-title">چه زمانی سفر می‌کنید؟</p>
                        <div style="display:flex;gap:24px;font-size:13px;color:var(--bnb-gray);margin-bottom:14px;">
                            <span x-text="checkIn ? '✓ ورود: '+jalaliStr(checkIn) : 'تاریخ ورود را انتخاب کنید'" :style="checkIn?'color:var(--bnb-red);font-weight:600':''"></span>
                            <span x-text="checkOut ? '✓ خروج: '+jalaliStr(checkOut) : 'سپس تاریخ خروج'" :style="checkOut?'color:var(--bnb-red);font-weight:600':''"></span>
                        </div>
                        <div id="navCalEl" style="direction:rtl;"></div>
                        <div x-show="checkIn && checkOut" style="margin-top:14px;text-align:left;">
                            <button type="button" class="bnb-filter-pill" @click="activate('who')">
                                <i class="bi bi-arrow-left me-1"></i>مرحله بعد: مهمانان
                            </button>
                        </div>
                    </div>

                    {{-- WHO --}}
                    <div x-show="step==='who'">
                        <p class="bnb-drop-title">چند نفر سفر می‌کنند؟</p>
                        <div class="bnb-guest-row">
                            <div>
                                <div style="font-weight:600;font-size:15px;color:var(--bnb-dark);">مهمانان</div>
                                <div style="font-size:13px;color:var(--bnb-gray);">بزرگسال و کودک</div>
                            </div>
                            <div class="bnb-counter">
                                <button type="button" class="bnb-cnt-btn" @click="guests>1 && guests--" :disabled="guests<=1">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <span x-text="guests" style="min-width:24px;text-align:center;font-size:16px;font-weight:600;"></span>
                                <button type="button" class="bnb-cnt-btn" @click="guests<16 && guests++">
                                    <i class="bi bi-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:20px;">
                            <button type="button" style="background:none;border:none;color:var(--bnb-gray);font-size:13px;text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);" @click="guests=1">پاک کردن</button>
                            <button type="button" class="btn-bnb" @click="submit()">
                                <i class="bi bi-search me-1"></i>جستجو
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Backdrop --}}
                <div class="bnb-search-backdrop" x-show="open" x-cloak @click="close()"
                     x-transition:enter="sf-enter" x-transition:enter-start="sf-enter-from" x-transition:enter-end="sf-enter-to"
                     x-transition:leave="sf-leave" x-transition:leave-start="sf-leave-from" x-transition:leave-end="sf-leave-to">
                </div>
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

// ─── Airbnb Nav Search (Alpine.js) ───────────────────────────────────────────
window.bnbLocations = @json($navLocations);

function bnbNavSearch() {
    return {
        open: false,
        step: 'where',          // 'where' | 'when' | 'who'
        // Location
        locationQuery: '',
        provinceId: null,
        cityId: null,
        locationLabel: 'جستجوی مقصد',
        suggestions: [],
        allLocations: [],
        // Dates
        checkIn: '',
        checkOut: '',
        calInitialized: false,
        // Guests
        guests: 1,

        get dateLabel() {
            if (this.checkIn && this.checkOut)
                return this.jalaliStr(this.checkIn) + ' — ' + this.jalaliStr(this.checkOut);
            if (this.checkIn) return this.jalaliStr(this.checkIn);
            return 'افزودن تاریخ';
        },

        jalaliStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        init() {
            this.allLocations = window.bnbLocations || [];
            this.suggestions  = this.allLocations.slice(0, 9);
            // Pre-fill from current URL query params
            const p = new URLSearchParams(window.location.search);
            if (p.get('check_in'))  this.checkIn  = p.get('check_in');
            if (p.get('check_out')) this.checkOut = p.get('check_out');
            if (p.get('guests'))    this.guests   = parseInt(p.get('guests')) || 1;
            const cid = parseInt(p.get('city_id'));
            const pid = parseInt(p.get('province_id'));
            if (cid) {
                const c = this.allLocations.find(l => l.type === 'city' && l.id === cid);
                if (c) { this.cityId = c.id; this.provinceId = c.province_id; this.locationLabel = c.name; }
            } else if (pid) {
                const pv = this.allLocations.find(l => l.type === 'province' && l.id === pid);
                if (pv) { this.provinceId = pv.id; this.locationLabel = pv.name; }
            }
        },

        activate(s) {
            this.open = true;
            this.step = s;
            if (s === 'where') {
                this.$nextTick(() => { if (this.$refs.locInput) this.$refs.locInput.focus(); });
            }
            if (s === 'when') {
                this.$nextTick(() => { if (!this.calInitialized) this.initCal(); });
            }
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        filterLocations() {
            const q = this.locationQuery.trim();
            if (!q) { this.suggestions = this.allLocations.slice(0, 9); return; }
            this.suggestions = this.allLocations
                .filter(l => l.name.includes(q) || (l.province && l.province.includes(q)))
                .slice(0, 10);
        },

        selectLocation(loc) {
            if (loc.type === 'province') {
                this.provinceId = loc.id; this.cityId = null;
                this.locationLabel = loc.name; this.locationQuery = loc.name;
            } else {
                this.cityId = loc.id; this.provinceId = loc.province_id;
                this.locationLabel = loc.name;
                this.locationQuery = loc.name + '، ' + loc.province;
            }
            this.activate('when');
        },

        initCal() {
            const self = this;
            let phase = 0, startUnix = null;
            function toGreg(unix) {
                const d = new Date(unix);
                return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
            }
            if (typeof $ === 'undefined' || typeof $.fn.persianDatepicker === 'undefined') return;
            $('#navCalEl').persianDatepicker({
                inline: true,
                calendar: { persian: { locale: 'fa' } },
                format: 'YYYY/MM/DD',
                minDate: new persianDate().valueOf(),
                onSelect(unix) {
                    if (phase === 0) {
                        startUnix = unix;
                        self.checkIn  = toGreg(unix);
                        self.checkOut = '';
                        phase = 1;
                    } else {
                        if (unix > startUnix) {
                            self.checkOut = toGreg(unix);
                            phase = 0; startUnix = null;
                            self.$nextTick(() => self.activate('who'));
                        } else {
                            startUnix = unix;
                            self.checkIn  = toGreg(unix);
                            self.checkOut = '';
                        }
                    }
                }
            });
            this.calInitialized = true;
        },

        submit() {
            const p = new URLSearchParams();
            if (this.cityId)      p.set('city_id',     this.cityId);
            else if (this.provinceId) p.set('province_id', this.provinceId);
            if (this.checkIn)     p.set('check_in',    this.checkIn);
            if (this.checkOut)    p.set('check_out',   this.checkOut);
            if (this.guests > 1)  p.set('guests',      this.guests);
            this.close();
            window.location.href = '/accommodations' + (p.toString() ? '?' + p.toString() : '');
        }
    };
}
</script>

@stack('scripts')
</body>
</html>
