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
    .bnb-search-wrap {
        position: relative; z-index: 1055;
        height: 56px;   /* fixed height so absolute children don't collapse the row */
    }
    .bnb-search-pill {
        position: absolute !important;
        top: 0; right: 0; left: 0;
        height: 56px;
    }
    .bnb-search-expanded {
        display: flex; align-items: center;
        background: #fff;
        border: 1px solid var(--bnb-border);
        border-radius: 40px;
        box-shadow: 0 6px 20px rgba(0,0,0,.12);
        height: 56px;
        overflow: visible;
        position: absolute; top: 0; right: 0; left: 0; z-index: 1055;
    }
    .bnb-stab {
        flex: 1; display: flex; flex-direction: column; align-items: flex-end;
        padding: 8px 18px; background: transparent; border: none;
        border-radius: 32px; cursor: pointer;
        transition: background .15s; min-width: 0; overflow: hidden;
    }
    .bnb-stab:hover { background: #f0f0f0; }
    .bnb-stab.bnb-stab-active {
        background: var(--bnb-bg-light);
        border: 1.5px solid var(--bnb-dark);
        box-shadow: none;
    }
    .bnb-stab-label { font-size: 11px; font-weight: 700; color: var(--bnb-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; text-align: right; }
    .bnb-stab-val   { font-size: 12px; color: var(--bnb-gray); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; text-align: right; }
    .bnb-stab-div   { width: 1px; height: 24px; background: var(--bnb-border); flex-shrink: 0; }
    .bnb-search-go  {
        background: var(--bnb-red); color: #fff; border: none;
        border-radius: 40px 0 0 40px;
        width: 52px; align-self: stretch;
        font-size: 15px; cursor: pointer;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        font-family: var(--bnb-font); transition: background .15s; margin: 0;
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

    /* Alpine transitions — opacity only to avoid layout shift */
    .sf-enter        { transition: opacity .22s ease; }
    .sf-enter-from   { opacity: 0; }
    .sf-enter-to     { opacity: 1; }
    .sf-leave        { transition: opacity .18s ease; }
    .sf-leave-from   { opacity: 1; }
    .sf-leave-to     { opacity: 0; }
    .sd-enter        { transition: opacity .26s cubic-bezier(.22,1,.36,1), transform .26s cubic-bezier(.22,1,.36,1); }
    .sd-enter-from   { opacity: 0; transform: translateX(-50%) translateY(-14px); }
    .sd-enter-to     { opacity: 1; transform: translateX(-50%) translateY(0); }
    .sd-leave        { transition: opacity .18s ease, transform .18s ease; }
    .sd-leave-from   { opacity: 1; transform: translateX(-50%) translateY(0); }
    .sd-leave-to     { opacity: 0; transform: translateX(-50%) translateY(-14px); }

    /* ══ Mini Navbar Styles ═══════════════════════════════════ */
    .bnb-navbar {
        height: 80px; 
        transition: height 0.3s cubic-bezier(0.2, 1, 0.3, 1), box-shadow 0.3s ease;
        display: flex;
        align-items: center;
        background: #fff;
        border-bottom: none;
        position: sticky;
        top: 0;
        z-index: 1055;
        box-sizing: border-box;
    }
    .bnb-navbar.is-mini {
        height: 64px;
        box-shadow: 0 1px 12px rgba(0,0,0,0.08);
    }
    
    /* Global classes to sync sticky elements with navbar state */
    body.nav-is-mini .is-nav-sticky {
        top: 63px !important; /* 64px - 1px border */
    }
    .is-nav-sticky {
        top: 79px !important; /* 80px - 1px border */
        transition: top 0.3s cubic-bezier(0.2, 1, 0.3, 1) !important;
    }
    .bnb-navbar.is-mini .bnb-logo {
        transform: scale(0.9);
        transform-origin: right center;
    }
    .bnb-navbar.is-mini .bnb-search-wrap {
        transform: scale(0.85);
    }
    /* Fix black corners and z-index issues when filters are open in mini mode */
    .bnb-navbar.is-mini .bnb-search-expanded {
        border-radius: 40px !important;
        overflow: hidden;
        border: 1px solid var(--bnb-border) !important;
        background: #fff !important;
        box-shadow: 0 6px 20px rgba(0,0,0,.12) !important;
    }
    .bnb-navbar.is-mini .bnb-search-wrap,
    .bnb-navbar.is-mini .bnb-search-pill {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    .bnb-navbar.is-mini .bnb-search-btn {
        background: transparent !important;
    }
    .bnb-navbar.is-mini .bnb-search-backdrop {
        display: none !important;
    }
    .bnb-logo, .bnb-search-wrap, .bnb-become-host, .bnb-user-menu-btn {
        z-index: 1040;
    }
    .bnb-logo, .bnb-search-wrap, .bnb-become-host, .bnb-user-menu-btn {
        transition: transform 0.3s cubic-bezier(0.2, 1, 0.3, 1);
    }

    /* Custom inline Jalali range calendar */
    .bnb-cal-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:16px; }
    .bnb-cal-nav { background:none;border:1px solid var(--bnb-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:16px;color:var(--bnb-dark);transition:background .15s; }
    .bnb-cal-nav:hover { background:var(--bnb-bg-light); }
    .bnb-cal-title { font-weight:700;font-size:15px;color:var(--bnb-dark); }
    .bnb-cal-dow { display:grid;grid-template-columns:repeat(7,1fr);text-align:center;margin-bottom:4px; }
    .bnb-cal-dow span { font-size:11px;color:var(--bnb-gray);padding:4px 0; }
    .bnb-cal-grid { display:grid;grid-template-columns:repeat(7,1fr);gap:2px; }
    .bnb-cal-cell { position:relative;aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:13px;cursor:pointer;border:none;background:none;transition:background .1s,color .1s;color:var(--bnb-dark);font-family:var(--bnb-font); }
    .bnb-cal-cell:disabled { opacity:.3;cursor:default; }
    .bnb-cal-cell.cal-start,.bnb-cal-cell.cal-end { background:var(--bnb-red)!important;color:#fff!important;border-radius:50%!important;z-index:1; }
    .bnb-cal-cell.cal-range { background:#f0f0f0;border-radius:0;color:var(--bnb-dark); }
    .bnb-cal-cell.cal-range-start { background:#f0f0f0;border-radius:0 50% 50% 0; }
    .bnb-cal-cell.cal-range-end   { background:#f0f0f0;border-radius:50% 0 0 50%; }
    .bnb-cal-cell.cal-hover-range { background:#f7f7f7;border-radius:0; }
    .bnb-cal-cell:not(:disabled):not(.cal-start):not(.cal-end):hover { background:#e8e8e8;border-radius:50%; }
    .bnb-cal-cell.cal-empty { cursor:default; }
    .bnb-cal-nights { display:inline-block;background:var(--bnb-dark);color:#fff;font-size:12px;font-weight:600;padding:4px 12px;border-radius:20px;margin-top:10px; }
    </style>

    @stack('styles')
</head>
<body class="bnb-page">

{{-- ═══════════════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════════════ --}}
<nav class="bnb-navbar" id="bnbNavbar">
    <div class="container-xxl px-3 px-lg-4">
        <div class="d-flex align-items-center">

            {{-- Logo (right in RTL) --}}
            <div class="d-none d-md-flex" style="flex:0 0 auto;justify-content:flex-start;">
            <a href="{{ route('home') }}" class="bnb-logo flex-shrink-0">
                <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 1C11.5 1 8 5.5 8 10.5C8 14 9.8 17 12.7 18.8L16 31L19.3 18.8C22.2 17 24 14 24 10.5C24 5.5 20.5 1 16 1Z" fill="#FF385C"/>
                </svg>
                <span class="d-none d-sm-inline">بنیاد</span>
            </a>
            </div>

            {{-- ── Mobile Search Pill (visible only on mobile) ─────────────── --}}
            <button type="button"
                    class="bnb-mobile-search-pill d-flex d-md-none"
                    onclick="window.dispatchEvent(new CustomEvent('open-mobile-search'))">
                <div class="ms-icon"><i class="bi bi-search"></i></div>
                <div class="ms-text">
                    <span class="ms-main">کجا میری؟</span>
                    <span class="ms-sub">هر کجا · هر تاریخ · هر تعداد نفر</span>
                </div>
            </button>

            {{-- ── Airbnb-style Interactive Search (Alpine.js) ───────────── --}}
            <div class="bnb-search-wrap d-none d-md-flex mx-3" style="flex:1 1 auto;width:min(620px,50%);"
                 x-data="bnbNavSearch()"
                 @keydown.escape.window="close()"
                 @open-nav-search.window="activate($event.detail.step)">

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
                        <i class="bi bi-search"></i>
                    </button>
                </div>

                {{-- Dropdown panel --}}
                <div class="bnb-search-drop" x-show="open" x-cloak
                     x-transition:enter="sd-enter" x-transition:enter-start="sd-enter-from" x-transition:enter-end="sd-enter-to"
                     x-transition:leave="sd-leave" x-transition:leave-start="sd-leave-from" x-transition:leave-end="sd-leave-to">

                    {{-- WHERE --}}
                    <div x-show="step==='where'">
                        <p class="bnb-drop-title">مقصد کجاست؟</p>

                        {{-- Search input (hidden when map is open) --}}
                        <div x-show="!mapOpen">
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
                            {{-- Open map button --}}
                            <div style="border-top:1px solid var(--bnb-border);margin-top:10px;padding-top:10px;">
                                <button type="button" class="bnb-sug-btn" @click="openMap()">
                                    <div class="bnb-sug-icon" style="background:#e8f4fd;">
                                        <i class="bi bi-pin-map-fill" style="color:#0078d4;"></i>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="bnb-sug-name">انتخاب روی نقشه</div>
                                        <div class="bnb-sug-sub">موقعیت دقیق را روی نقشه مشخص کنید</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        {{-- Map panel --}}
                        <div x-show="mapOpen" x-transition>
                            <div id="navSearchMap" style="width:100%;height:300px;border-radius:12px;overflow:hidden;border:1px solid var(--bnb-border);"></div>
                            {{-- Radius selector --}}
                            <div style="display:flex;align-items:center;gap:10px;margin-top:10px;padding:8px 12px;background:#f8f8f8;border-radius:10px;">
                                <label style="font-size:12px;color:var(--bnb-gray);flex-shrink:0;">شعاع جستجو:</label>
                                <select x-model.number="mapRadius" @change="updateMapCircle()" style="flex:1;border:1px solid var(--bnb-border);border-radius:8px;padding:4px 8px;font-size:13px;font-family:var(--bnb-font);background:#fff;cursor:pointer;">
                                    <option value="5">۵ کیلومتر</option>
                                    <option value="10">۱۰ کیلومتر</option>
                                    <option value="20">۲۰ کیلومتر</option>
                                    <option value="30" selected>۳۰ کیلومتر</option>
                                    <option value="50">۵۰ کیلومتر</option>
                                    <option value="100">۱۰۰ کیلومتر</option>
                                </select>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;gap:8px;">
                                <span style="font-size:12px;color:var(--bnb-gray);flex:1;" x-text="mapLabel || 'روی نقشه کلیک کنید تا موقعیت انتخاب شود'"></span>
                                <div style="display:flex;gap:8px;flex-shrink:0;">
                                    <button type="button" @click="closeMap()" style="background:none;border:1px solid var(--bnb-border);border-radius:8px;padding:6px 14px;font-size:13px;cursor:pointer;font-family:var(--bnb-font);color:var(--bnb-dark);">انصراف</button>
                                    <button type="button" class="btn-bnb" :disabled="!mapLat" @click="confirmMap()" style="padding:7px 16px;font-size:13px;">تأیید موقعیت</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- WHEN --}}
                    <div x-show="step==='when'">
                        <p class="bnb-drop-title">چه زمانی سفر می‌کنید؟</p>

                        {{-- Status bar --}}
                        <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;font-size:13px;">
                               <div :style="checkIn ? 'background:transparent;color:var(--bnb-red);' : 'background:var(--bnb-bg-light);color:var(--bnb-gray);'"
                                   style="flex:1;border-radius:10px;padding:8px 14px;text-align:center;transition:all .2s;">
                                <div style="font-size:10px;font-weight:600;opacity:.7;margin-bottom:2px;">ورود</div>
                                <div style="font-weight:600;" x-text="checkIn ? jalaliStr(checkIn) : 'انتخاب شود'"></div>
                            </div>
                            <i class="bi bi-arrow-left" style="color:var(--bnb-gray);flex-shrink:0;"></i>
                               <div :style="checkOut ? 'background:transparent;color:var(--bnb-red);' : 'background:var(--bnb-bg-light);color:var(--bnb-gray);'"
                                   style="flex:1;border-radius:10px;padding:8px 14px;text-align:center;transition:all .2s;">
                                <div style="font-size:10px;font-weight:600;opacity:.7;margin-bottom:2px;">خروج</div>
                                <div style="font-weight:600;" x-text="checkOut ? jalaliStr(checkOut) : 'انتخاب شود'"></div>
                            </div>
                        </div>

                        {{-- Calendar --}}
                        <div class="bnb-cal-header">
                            {{-- RTL: next on left, prev on right --}}
                            <button type="button" class="bnb-cal-nav" @click="calNext()">&lsaquo;</button>
                            <span class="bnb-cal-title" x-text="calMonthLabel"></span>
                            <button type="button" class="bnb-cal-nav" @click="calPrev()">&rsaquo;</button>
                        </div>

                        {{-- Day-of-week headers (RTL: Sat→Fri = right→left) --}}
                        <div class="bnb-cal-dow">
                            <template x-for="h in ['ج','پ','چ','س','د','ی','ش']">
                                <span x-text="h"></span>
                            </template>
                        </div>

                        {{-- Day grid --}}
                        <div class="bnb-cal-grid">
                            <template x-for="(cell, idx) in calDays" :key="idx">
                                <button type="button"
                                    :disabled="!cell || cell.past"
                                    @click="cell && !cell.past && selectCalDay(cell)"
                                    @mouseenter="cell && !cell.past && (calHover = cell.greg)"
                                    @mouseleave="calHover = null"
                                    :class="{
                                        'bnb-cal-cell':true,
                                        'cal-start':   cell && cell.greg === checkIn,
                                        'cal-end':     cell && cell.greg === checkOut,
                                        'cal-range':   calInRange(cell),
                                        'cal-range-start': cell && checkIn && checkOut && cell.greg === checkIn,
                                        'cal-range-end':   cell && checkIn && checkOut && cell.greg === checkOut,
                                        'cal-hover-range': calHoverRange(cell),
                                        'cal-empty':   !cell
                                    }">
                                    <span x-text="cell ? cell.d : ''"></span>
                                </button>
                            </template>
                        </div>

                        {{-- Nights count & next step --}}
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:14px;">
                            <div>
                                <span x-show="checkIn && checkOut" class="bnb-cal-nights"
                                      x-text="calNights + ' شب اقامت'"></span>
                                <span x-show="checkIn && !checkOut" style="font-size:12px;color:var(--bnb-gray);">حالا تاریخ خروج را انتخاب کنید</span>
                                <span x-show="!checkIn" style="font-size:12px;color:var(--bnb-gray);">تاریخ ورود را انتخاب کنید</span>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button x-show="checkIn || checkOut" type="button"
                                    @click="checkIn='';checkOut='';calPhase=0;"
                                    style="background:none;border:none;font-size:12px;color:var(--bnb-gray);text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);">پاک کردن</button>
                                <button x-show="checkIn && checkOut" type="button"
                                    class="btn-bnb" style="padding:7px 16px;font-size:13px;"
                                    @click="activate('who')">
                                    مرحله بعد <i class="bi bi-arrow-left ms-1"></i>
                                </button>
                            </div>
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
            <div class="bnb-nav-right d-none d-md-flex" style="flex:0 0 auto;justify-content:flex-end;">
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
<div class="container-xxl px-3 px-lg-4 pt-3">
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
    <div class="container-xxl px-3 px-lg-4">
        <div class="bnb-footer-grid">
            {{-- پشتیبانی --}}
            <div>
                <button class="bnb-footer-acc-toggle" onclick="bnbFooterToggle(this)">
                    <span>پشتیبانی</span>
                    <i class="bi bi-chevron-down acc-arrow"></i>
                </button>
                <h6>پشتیبانی</h6>
                <div class="bnb-footer-acc-body">
                <ul>
                    <li><a href="#">مرکز کمک</a></li>
                    <li><a href="#">اطلاعات ایمنی</a></li>
                    <li><a href="#">گزینه‌های لغو</a></li>
                    <li><a href="#">اقامتگاه‌های معلولین</a></li>
                </ul>
                </div>
            </div>
            {{-- اجتماع --}}
            <div>
                <button class="bnb-footer-acc-toggle" onclick="bnbFooterToggle(this)">
                    <span>اجتماع</span>
                    <i class="bi bi-chevron-down acc-arrow"></i>
                </button>
                <h6>اجتماع</h6>
                <div class="bnb-footer-acc-body">
                <ul>
                    <li><a href="#">بنیاد علیه تبعیض</a></li>
                    <li><a href="#">اقامتگاه‌های مناسب</a></li>
                    <li><a href="#">آپارتمان‌های مهمان‌دوست</a></li>
                    <li><a href="#">تجربیات مسافران</a></li>
                </ul>
                </div>
            </div>
            {{-- میزبانی --}}
            <div>
                <button class="bnb-footer-acc-toggle" onclick="bnbFooterToggle(this)">
                    <span>میزبانی</span>
                    <i class="bi bi-chevron-down acc-arrow"></i>
                </button>
                <h6>میزبانی</h6>
                <div class="bnb-footer-acc-body">
                <ul>
                    <li><a href="#">میزبان شوید</a></li>
                    <li><a href="#">منابع میزبانی</a></li>
                    <li><a href="#">انجمن میزبانان</a></li>
                    <li><a href="#">میزبانی مسئولانه</a></li>
                </ul>
                </div>
            </div>
            {{-- بنیاد --}}
            <div>
                <button class="bnb-footer-acc-toggle" onclick="bnbFooterToggle(this)">
                    <span>بنیاد</span>
                    <i class="bi bi-chevron-down acc-arrow"></i>
                </button>
                <h6>بنیاد</h6>
                <div class="bnb-footer-acc-body">
                <ul>
                    <li><a href="#">اخبار</a></li>
                    <li><a href="#">درباره ما</a></li>
                    <li><a href="#">فرصت‌های شغلی</a></li>
                    <li><a href="#">تماس با ما</a></li>
                </ul>
                </div>
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

{{-- ═══════════════════════════════════════════════════
     MOBILE BOTTOM NAVIGATION  (only on small screens)
═══════════════════════════════════════════════════ --}}
<nav class="bnb-bottom-nav d-flex d-md-none" aria-label="ناوبری پایین">
    <a href="{{ route('home') }}"
       class="bnb-bottom-nav-item {{ request()->routeIs('home') ? 'active' : '' }}">
        <i class="bi bi-search"></i>
        <span>کاوش</span>
    </a>
    <a href="{{ route('favorites.index') }}"
       class="bnb-bottom-nav-item {{ request()->routeIs('favorites.*') ? 'active' : '' }}">
        <i class="bi bi-heart{{ request()->routeIs('favorites.*') ? '-fill' : '' }}"></i>
        <span>علاقه‌مندی</span>
    </a>
    @auth
        <a href="{{ route('bookings.index') }}"
           class="bnb-bottom-nav-item {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
            <i class="bi bi-calendar-check"></i>
            <span>رزروها</span>
        </a>
        <a href="{{ route('profile.index') }}"
           class="bnb-bottom-nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
            <i class="bi bi-person-circle"></i>
            <span>پروفایل</span>
        </a>
    @else
        <a href="{{ route('auth.mobile') }}" class="bnb-bottom-nav-item">
            <i class="bi bi-person-circle"></i>
            <span>ورود</span>
        </a>
    @endauth
</nav>

{{-- ═══════════════════════════════════════════════════
     MOBILE FULL-SCREEN SEARCH MODAL  (only on small screens)
═══════════════════════════════════════════════════ --}}
<div class="d-md-none" id="mobileSearchOverlay" x-data="bnbMobileSearch()"
     @open-mobile-search.window="onOpen()">

    <div class="bnb-mobile-search-overlay" :class="{ open: open }" x-cloak>

        {{-- Header --}}
        <div class="bnb-ms-header">
            <button type="button" class="bnb-ms-close" @click="close()">
                <i class="bi bi-x"></i>
            </button>
            <div class="bnb-ms-header-title">جستجو</div>
            <div style="width:34px;"></div>{{-- spacer --}}
        </div>

        {{-- Body / steps --}}
        <div class="bnb-ms-body">

            {{-- Step 1: مقصد --}}
            <div class="bnb-ms-step" :class="{ active: step === 'where' }" @click="step='where'">
                <div class="bnb-ms-step-head">
                    <span class="bnb-ms-step-label">مقصد</span>
                    <span class="bnb-ms-step-val" x-text="locationLabel"></span>
                </div>
                <div class="bnb-ms-step-body" x-show="step === 'where'" x-transition>
                    {{-- Search list --}}
                    <div x-show="!mapOpen">
                        <input type="text" class="bnb-ms-loc-input" placeholder="جستجوی استان یا شهر..."
                               x-model="locQuery" @input="filterLocs()" autocomplete="off">
                        <div style="max-height:200px;overflow-y:auto;">
                            <template x-for="loc in suggestions" :key="loc.type+'-'+loc.id">
                                <button type="button" class="bnb-ms-loc-item" @click.stop="selectLoc(loc)">
                                    <div class="bnb-ms-loc-icon">
                                        <i :class="loc.type==='province'?'bi bi-map':'bi bi-geo-alt-fill'"></i>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:14px;font-weight:600;color:var(--bnb-dark);" x-text="loc.name"></div>
                                        <div style="font-size:12px;color:var(--bnb-gray);" x-show="loc.type==='city'" x-text="loc.province"></div>
                                        <div style="font-size:12px;color:var(--bnb-gray);" x-show="loc.type==='province'">استان</div>
                                    </div>
                                </button>
                            </template>
                            <div x-show="suggestions.length===0" style="padding:20px;text-align:center;color:var(--bnb-gray);font-size:13px;">نتیجه‌ای یافت نشد</div>
                        </div>
                        {{-- Open map button --}}
                        <div style="border-top:1px solid var(--bnb-border);margin-top:10px;padding-top:10px;">
                            <button type="button" class="bnb-ms-loc-item" @click.stop="openMap()">
                                <div class="bnb-ms-loc-icon" style="background:#e8f4fd;">
                                    <i class="bi bi-pin-map-fill" style="color:#0078d4;"></i>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:14px;font-weight:600;color:var(--bnb-dark);">انتخاب روی نقشه</div>
                                    <div style="font-size:12px;color:var(--bnb-gray);">موقعیت دقیق را روی نقشه مشخص کنید</div>
                                </div>
                            </button>
                        </div>
                    </div>

                    {{-- Map panel (Mobile) --}}
                    <div x-show="mapOpen" x-transition>
                        <div id="mobileSearchMap" style="width:100%;height:250px;border-radius:12px;overflow:hidden;border:1px solid var(--bnb-border);"></div>
                        {{-- Radius selector --}}
                        <div style="display:flex;align-items:center;gap:10px;margin-top:10px;padding:8px 12px;background:#f8f8f8;border-radius:10px;">
                            <label style="font-size:12px;color:var(--bnb-gray);flex-shrink:0;">شعاع جستجو:</label>
                            <select x-model.number="mapRadius" @change="updateMapCircle()" style="flex:1;border:1px solid var(--bnb-border);border-radius:8px;padding:4px 8px;font-size:13px;font-family:var(--bnb-font);background:#fff;cursor:pointer;">
                                <option value="5">۵ کیلومتر</option>
                                <option value="10">۱۰ کیلومتر</option>
                                <option value="20">۲۰ کیلومتر</option>
                                <option value="30" selected>۳۰ کیلومتر</option>
                                <option value="50">۵۰ کیلومتر</option>
                                <option value="100">۱۰۰ کیلومتر</option>
                            </select>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:Leg;gap:8px;padding-top:8px;">
                            <div style="display:flex;gap:8px;width:100%;">
                                <button type="button" @click.stop="closeMap()" style="flex:1;background:none;border:1px solid var(--bnb-border);border-radius:8px;padding:8px;font-size:13px;cursor:pointer;font-family:var(--bnb-font);color:var(--bnb-dark);">انصراف</button>
                                <button type="button" class="btn-bnb" :disabled="!mapLat" @click.stop="confirmMap()" style="flex:1;padding:8px;font-size:13px;">تأیید</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2: تاریخ --}}
            <div class="bnb-ms-step" :class="{ active: step === 'when' }" @click="step='when'">
                <div class="bnb-ms-step-head">
                    <span class="bnb-ms-step-label">تاریخ</span>
                    <span class="bnb-ms-step-val" x-text="dateLabel"></span>
                </div>
                <div class="bnb-ms-step-body" x-show="step === 'when'" x-transition>
                    {{-- Mini calendar status --}}
                    <div style="display:flex;gap:10px;margin-top:14px;margin-bottom:14px;">
                        <div style="flex:1;border:1px solid var(--bnb-border);border-radius:10px;padding:10px;text-align:center;" :style="checkIn ? 'border-color:var(--bnb-red)' : ''">
                            <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);">ورود</div>
                            <div style="font-size:13px;font-weight:600;" x-text="checkIn ? jalStr(checkIn) : 'انتخاب'"></div>
                        </div>
                        <div style="flex:1;border:1px solid var(--bnb-border);border-radius:10px;padding:10px;text-align:center;" :style="checkOut ? 'border-color:var(--bnb-red)' : ''">
                            <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);">خروج</div>
                            <div style="font-size:13px;font-weight:600;" x-text="checkOut ? jalStr(checkOut) : 'انتخاب'"></div>
                        </div>
                    </div>
                    {{-- Inline Jalali calendar --}}
                    <div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <button type="button" @click.stop="calNext()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&lsaquo;</button>
                            <span style="font-size:14px;font-weight:700;" x-text="calMonthLabel"></span>
                            <button type="button" @click.stop="calPrev()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&rsaquo;</button>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(7,1fr);text-align:center;margin-bottom:4px;">
                            <template x-for="h in ['ج','پ','چ','س','د','ی','ش']">
                                <span style="font-size:11px;color:var(--bnb-gray);padding:4px 0;" x-text="h"></span>
                            </template>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;">
                            <template x-for="(cell, idx) in calDays" :key="idx">
                                <button type="button"
                                    :disabled="!cell || cell.past"
                                    @click.stop="cell && !cell.past && selectDay(cell)"
                                    :class="{
                                        'bnb-cal-cell': true,
                                        'cal-start':  cell && cell.greg === checkIn,
                                        'cal-end':    cell && cell.greg === checkOut,
                                        'cal-range':  cell && checkIn && checkOut && cell.greg > checkIn && cell.greg < checkOut,
                                        'cal-empty':  !cell
                                    }">
                                    <span x-text="cell ? cell.d : ''"></span>
                                </button>
                            </template>
                        </div>
                        <div style="margin-top:10px;display:flex;justify-content:flex-end;gap:8px;">
                            <button x-show="checkIn" type="button" @click.stop="checkIn='';checkOut='';calPhase=0;"
                                style="background:none;border:none;font-size:12px;color:var(--bnb-gray);text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);">پاک کردن</button>
                            <button x-show="checkIn && checkOut" type="button" @click.stop="step='who'"
                                class="btn-bnb" style="padding:6px 16px;font-size:13px;">بعدی ›</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 3: مهمانان --}}
            <div class="bnb-ms-step" :class="{ active: step === 'who' }" @click="step='who'">
                <div class="bnb-ms-step-head">
                    <span class="bnb-ms-step-label">مهمانان</span>
                    <span class="bnb-ms-step-val" x-text="guests + ' نفر'"></span>
                </div>
                <div class="bnb-ms-step-body" x-show="step === 'who'" x-transition>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 0;">
                        <div>
                            <div style="font-size:15px;font-weight:600;">تعداد نفرات</div>
                            <div style="font-size:12px;color:var(--bnb-gray);">بزرگسال و کودک</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:18px;">
                            <button type="button" class="bnb-cnt-btn" @click.stop="guests>1 && guests--" :disabled="guests<=1"><i class="bi bi-dash"></i></button>
                            <span x-text="guests" style="min-width:20px;text-align:center;font-size:16px;font-weight:600;"></span>
                            <button type="button" class="bnb-cnt-btn" @click.stop="guests<16 && guests++"><i class="bi bi-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- end ms-body --}}

        {{-- Footer: clear + search --}}
        <div class="bnb-ms-footer">
            <button type="button" class="bnb-ms-clear-btn" @click="clearAll()">پاک کردن همه</button>
            <button type="button" class="btn-bnb" style="padding:12px 28px;font-size:15px;border-radius:12px;" @click="submit()">
                <i class="bi bi-search me-2"></i>جستجو
            </button>
        </div>

    </div>{{-- end overlay --}}
</div>{{-- end mobile search wrapper --}}

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
        // Map
        mapOpen: false,
        mapLat: null,
        mapLng: null,
        mapRadius: 30,
        mapLabel: '',
        _map: null,
        _marker: null,
        _circle: null,
        // Dates
        checkIn: '',
        checkOut: '',
        calYear: null,
        calMonth: null,
        calPhase: 0,   // 0=picking start, 1=picking end
        calHover: null,
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

        _fireSync() {
            window.dispatchEvent(new CustomEvent('nav-search-updated', {
                detail: { checkIn: this.checkIn, checkOut: this.checkOut, guests: this.guests }
            }));
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
            // Watch for date/guest changes and broadcast to other components
            this.$watch('checkIn',  () => this._fireSync());
            this.$watch('checkOut', () => this._fireSync());
            this.$watch('guests',   () => this._fireSync());
        },

        activate(s) {
            this.open = true;
            this.step = s;
            if (s === 'where') {
                this.$nextTick(() => { if (this.$refs.locInput) this.$refs.locInput.focus(); });
            }
            if (s === 'when') {
                if (typeof persianDate !== 'undefined') {
                    // If we have a check-in date, move calendar to its month
                    if (this.checkIn) {
                        try {
                            const pDate = new persianDate(new Date(this.checkIn + 'T12:00:00'));
                            this.calYear = pDate.year();
                            this.calMonth = pDate.month();
                        } catch(e) {
                            const t = new persianDate();
                            this.calYear = t.year();
                            this.calMonth = t.month();
                        }
                    } else if (!this.calYear) {
                        const t = new persianDate();
                        this.calYear  = t.year();
                        this.calMonth = t.month();
                    }
                    this.calPhase = (this.checkIn && !this.checkOut) ? 1 : 0;
                }
            }
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.open = false;
            this.mapOpen = false;
            document.body.style.overflow = '';
        },

        filterLocations() {
            const q = this.locationQuery.trim();
            if (!q) { this.suggestions = this.allLocations.slice(0, 9); return; }
            this.suggestions = this.allLocations
                .filter(l => l.name.includes(q) || (l.province && l.province.includes(q)))
                .slice(0, 10);
        },

        openMap() {
            this.mapOpen = true;
            this.$nextTick(() => {
                if (this._map) { this._map.invalidateSize(); return; }
                const map = L.map('navSearchMap', { zoomControl: true }).setView([32.4, 53.7], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 18
                }).addTo(map);
                map.on('click', (e) => {
                    const { lat, lng } = e.latlng;
                    // Remove old marker + circle
                    if (this._marker) map.removeLayer(this._marker);
                    if (this._circle) map.removeLayer(this._circle);
                    this._marker = L.marker([lat, lng]).addTo(map);
                    this._circle = L.circle([lat, lng], {
                        radius: this.mapRadius * 1000,
                        color: '#c0392b', fillColor: '#e74c3c', fillOpacity: 0.12, weight: 1.5
                    }).addTo(map);
                    this.mapLat = lat.toFixed(6);
                    this.mapLng = lng.toFixed(6);
                    this.mapLabel = `📍 ${lat.toFixed(4)}، ${lng.toFixed(4)} — شعاع ${this.mapRadius} کیلومتر`;
                });
                this._map = map;
            });
        },

        updateMapCircle() {
            if (!this._circle || !this.mapLat) return;
            this._circle.setRadius(this.mapRadius * 1000);
            if (this.mapLat) this.mapLabel = `📍 ${parseFloat(this.mapLat).toFixed(4)}، ${parseFloat(this.mapLng).toFixed(4)} — شعاع ${this.mapRadius} کیلومتر`;
        },

        closeMap() {
            this.mapOpen = false;
        },

        confirmMap() {
            if (!this.mapLat) return;
            this.cityId = null;
            this.provinceId = null;
            const label = `نقشه — شعاع ${this.mapRadius} کیلومتر`;
            this.locationLabel = label;
            this.locationQuery  = label;
            this.mapOpen = false;
            this.activate('when');
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

        get calMonthLabel() {
            if (!this.calYear) return '';
            const n=['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
        },

        get calDays() {
            if (!this.calYear || typeof persianDate === 'undefined') return [];
            const pd   = new persianDate([this.calYear, this.calMonth, 1]);
            const fdow = pd.day();          // 0=Sat … 6=Fri
            const dim  = pd.daysInMonth();
            const now  = new persianDate();
            const ty   = now.year(), tm = now.month(), td = now.date();
            // In RTL grid columns are: ج پ چ س د ی ش (left to right visually)
            // col 0 = جمعه(6), col 6 = شنبه(0)  → offset = (6 - fdow) % 7? No.
            // persianDate.day(): 0=Sat,1=Sun,2=Mon,3=Tue,4=Wed,5=Thu,6=Fri
            // Grid cols L→R: ج(6) پ(5) چ(4) س(3) د(2) ی(1) ش(0)
            // So col index for day = 6 - fdow
            const offset = (6 - fdow + 7) % 7;
            let cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= dim; d++) {
                const dt   = new persianDate([this.calYear, this.calMonth, d]).toDate();
                const greg = dt.getFullYear() + '-'
                           + String(dt.getMonth()+1).padStart(2,'0') + '-'
                           + String(dt.getDate()).padStart(2,'0');
                const past = (this.calYear < ty)
                          || (this.calYear === ty && this.calMonth < tm)
                          || (this.calYear === ty && this.calMonth === tm && d < td);
                cells.push({ d, greg, past });
            }
            return cells;
        },

        get calNights() {
            if (!this.checkIn || !this.checkOut) return 0;
            return Math.round((new Date(this.checkOut) - new Date(this.checkIn)) / 86400000);
        },

        calInRange(cell) {
            if (!cell || !this.checkIn || !this.checkOut) return false;
            return cell.greg > this.checkIn && cell.greg < this.checkOut;
        },

        calHoverRange(cell) {
            if (!cell || this.calPhase !== 1 || !this.checkIn || !this.calHover) return false;
            if (this.calHover > this.checkIn)
                return cell.greg > this.checkIn && cell.greg < this.calHover;
            return false;
        },

        calPrev() {
            if (this.calMonth === 1) { this.calYear--; this.calMonth = 12; }
            else this.calMonth--;
        },

        calNext() {
            if (this.calMonth === 12) { this.calYear++; this.calMonth = 1; }
            else this.calMonth++;
        },

        selectCalDay(cell) {
            if (!cell || cell.past) return;
            if (this.calPhase === 0) {
                this.checkIn  = cell.greg;
                this.checkOut = '';
                this.calPhase = 1;
            } else {
                if (cell.greg > this.checkIn) {
                    this.checkOut = cell.greg;
                    this.calPhase = 0;
                    this.calHover = null;
                } else {
                    this.checkIn  = cell.greg;
                    this.checkOut = '';
                }
            }
        },

        submit() {
            const p = new URLSearchParams();
            if (this.cityId)           p.set('city_id',     this.cityId);
            else if (this.provinceId)  p.set('province_id', this.provinceId);
            else if (this.mapLat) { p.set('lat', this.mapLat); p.set('lng', this.mapLng); p.set('radius', this.mapRadius); }
            if (this.checkIn)          p.set('check_in',    this.checkIn);
            if (this.checkOut)         p.set('check_out',   this.checkOut);
            if (this.guests > 1)       p.set('guests',      this.guests);
            this.close();
            window.location.href = '/accommodations' + (p.toString() ? '?' + p.toString() : '');
        }
    };
}

// ══ Navbar Minimize logic ═════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    const navbar = document.getElementById('bnbNavbar');
    if (!navbar) return;

    let lastScrollY = window.scrollY;
    
    window.addEventListener('scroll', () => {
        const currentScrollY = window.scrollY;
        
        if (currentScrollY > 50) {
            navbar.classList.add('is-mini');
            document.body.classList.add('nav-is-mini');
        } else {
            navbar.classList.remove('is-mini');
            document.body.classList.remove('nav-is-mini');
        }
    }, { passive: true });

    // Close search dropdown when clicking outside in mini mode
    document.addEventListener('click', (e) => {
        const searchWrap = document.querySelector('.bnb-search-wrap');
        if (searchWrap && !searchWrap.contains(e.target)) {
            // Check if Alpine store/data 'open' is true and close it
            const alpineData = Alpine.$data(document.querySelector('[x-data="bnbNavSearch()"]'));
            if (alpineData && alpineData.open) {
                alpineData.close();
            }
        }
    });
});

// ══ Mobile Search Alpine Component ════════════════════════════
function bnbMobileSearch() {
    return {
        open: false,
        step: 'where',
        // Location
        locQuery: '',
        cityId: null,
        provinceId: null,
        locationLabel: 'جستجوی مقصد',
        suggestions: [],
        allLocations: [],
        // Dates
        checkIn: '',
        checkOut: '',
        calYear: null,
        calMonth: null,
        calPhase: 0,
        // Map (Mobile)
        mapOpen: false,
        mapLat: null,
        mapLng: null,
        mapRadius: 30,
        mapLabel: '',
        _map: null,
        _marker: null,
        _circle: null,
        // Guests
        guests: 1,

        get dateLabel() {
            if (this.checkIn && this.checkOut) return this.jalStr(this.checkIn) + ' — ' + this.jalStr(this.checkOut);
            if (this.checkIn) return this.jalStr(this.checkIn);
            return 'افزودن تاریخ';
        },

        jalStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        init() {
            this.allLocations = window.bnbLocations || [];
            this.suggestions  = this.allLocations.slice(0, 8);
            const p = new URLSearchParams(window.location.search);
            if (p.get('check_in'))  this.checkIn  = p.get('check_in');
            if (p.get('check_out')) this.checkOut = p.get('check_out');
            if (p.get('guests'))    this.guests   = parseInt(p.get('guests')) || 1;
        },

        onOpen() {
            // init calendar if not inited
            if (!this.calYear && typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.open = true;
            this.step = 'where';
            document.body.style.overflow = 'hidden';
        },

        close() {
            this.open = false;
            this.mapOpen = false;
            document.body.style.overflow = '';
        },

        openMap() {
            this.mapOpen = true;
            this.$nextTick(() => {
                if (this._map) { this._map.invalidateSize(); return; }
                const map = L.map('mobileSearchMap', { zoomControl: true }).setView([32.4, 53.7], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap', maxZoom: 18
                }).addTo(map);
                map.on('click', (e) => {
                    const { lat, lng } = e.latlng;
                    if (this._marker) map.removeLayer(this._marker);
                    if (this._circle) map.removeLayer(this._circle);
                    this._marker = L.marker([lat, lng]).addTo(map);
                    this._circle = L.circle([lat, lng], {
                        radius: this.mapRadius * 1000,
                        color: '#c0392b', fillColor: '#e74c3c', fillOpacity: 0.12, weight: 1.5
                    }).addTo(map);
                    this.mapLat = lat.toFixed(6);
                    this.mapLng = lng.toFixed(6);
                });
                this._map = map;
            });
        },

        updateMapCircle() {
            if (!this._circle || !this.mapLat) return;
            this._circle.setRadius(this.mapRadius * 1000);
        },

        closeMap() {
            this.mapOpen = false;
        },

        confirmMap() {
            if (!this.mapLat) return;
            this.cityId = null;
            this.provinceId = null;
            const label = `نقشه — شعاع ${this.mapRadius} کیلومتر`;
            this.locationLabel = label;
            this.locQuery = label;
            this.mapOpen = false;
            this.step = 'when';
        },

        clearAll() {
            this.locQuery = ''; this.cityId = null; this.provinceId = null;
            this.locationLabel = 'جستجوی مقصد';
            this.checkIn = ''; this.checkOut = ''; this.calPhase = 0;
            this.guests = 1;
            this.suggestions = this.allLocations.slice(0, 8);
        },

        filterLocs() {
            const q = this.locQuery.trim();
            if (!q) { this.suggestions = this.allLocations.slice(0, 8); return; }
            this.suggestions = this.allLocations
                .filter(l => l.name.includes(q) || (l.province && l.province.includes(q)))
                .slice(0, 10);
        },

        selectLoc(loc) {
            if (loc.type === 'province') {
                this.provinceId = loc.id; this.cityId = null;
                this.locationLabel = loc.name; this.locQuery = loc.name;
            } else {
                this.cityId = loc.id; this.provinceId = loc.province_id;
                this.locationLabel = loc.name; this.locQuery = loc.name + '، ' + loc.province;
            }
            this.step = 'when';
        },

        // Calendar helpers (same logic as desktop)
        get calMonthLabel() {
            if (!this.calYear) return '';
            const n=['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
        },

        get calDays() {
            if (!this.calYear || typeof persianDate === 'undefined') return [];
            const pd   = new persianDate([this.calYear, this.calMonth, 1]);
            const fdow = pd.day();
            const dim  = pd.daysInMonth();
            const now  = new persianDate();
            const ty   = now.year(), tm = now.month(), td = now.date();
            const offset = (6 - fdow + 7) % 7;
            let cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= dim; d++) {
                const dt   = new persianDate([this.calYear, this.calMonth, d]).toDate();
                const greg = dt.getFullYear() + '-'
                           + String(dt.getMonth()+1).padStart(2,'0') + '-'
                           + String(dt.getDate()).padStart(2,'0');
                const past = (this.calYear < ty)
                          || (this.calYear === ty && this.calMonth < tm)
                          || (this.calYear === ty && this.calMonth === tm && d < td);
                cells.push({ d, greg, past });
            }
            return cells;
        },

        calPrev() {
            if (this.calMonth === 1) { this.calYear--; this.calMonth = 12; }
            else this.calMonth--;
        },
        calNext() {
            if (this.calMonth === 12) { this.calYear++; this.calMonth = 1; }
            else this.calMonth++;
        },

        selectDay(cell) {
            if (!cell || cell.past) return;
            if (this.calPhase === 0) {
                this.checkIn  = cell.greg; this.checkOut = ''; this.calPhase = 1;
            } else {
                if (cell.greg > this.checkIn) {
                    this.checkOut = cell.greg; this.calPhase = 0;
                } else {
                    this.checkIn = cell.greg; this.checkOut = '';
                }
            }
        },

        submit() {
            const p = new URLSearchParams();
            if (this.cityId)           p.set('city_id',     this.cityId);
            else if (this.provinceId)  p.set('province_id', this.provinceId);
            else if (this.mapLat) { p.set('lat', this.mapLat); p.set('lng', this.mapLng); p.set('radius', this.mapRadius); }
            if (this.checkIn)          p.set('check_in',    this.checkIn);
            if (this.checkOut)         p.set('check_out',   this.checkOut);
            if (this.guests > 1)       p.set('guests',      this.guests);
            this.close();
            window.location.href = '/accommodations' + (p.toString() ? '?' + p.toString() : '');
        }
    };
}

// ══ Footer Accordion (mobile) ══════════════════════════════════
function bnbFooterToggle(btn) {
    const body = btn.nextElementSibling.nextElementSibling; // skip <h6>
    const isOpen = btn.classList.contains('open');
    btn.classList.toggle('open', !isOpen);
    body.classList.toggle('open', !isOpen);
}
</script>

@stack('scripts')
</body>
</html>
