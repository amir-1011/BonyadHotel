@props(['mode' => 'public', 'defaultDiscountPct' => 0, 'accommodationEditUrl' => null])
@php $isManual = $mode === 'manual'; @endphp
{{-- Quick-Book Drawer --}}
<div data-bnb-drawer
     data-bnb-mode="{{ $mode }}"
     data-user-discount-pct="{{ (int) $defaultDiscountPct }}"
     x-data="mbbDrawer()"
     @keydown.escape.window="mode !== 'manual' && (drawerOpen=false)">

    {{-- Backdrop --}}
    <div x-show="mode !== 'manual' && drawerOpen" x-transition.opacity
         style="position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1060;"
         @click="drawerOpen=false" x-cloak></div>

    {{-- Slide-up sheet / embedded panel --}}
    <div id="bnb-manual-drawer-panel"
         x-show="(mode === 'manual' && roomTypeId) || (mode !== 'manual' && drawerOpen)" x-cloak
         x-transition
         :style="mode === 'manual'
            ? 'position:relative;background:#fff;border:1px solid var(--bnb-border);border-radius:12px;padding:20px 16px;margin-top:16px;max-width:100%;box-shadow:none;'
            : 'position:fixed;bottom:0;right:0;left:0;z-index:1071;background:#fff;border-radius:20px 20px 0 0;padding:20px 16px 32px;max-height:90vh;overflow-y:auto;max-width:500px;margin:0 auto;box-shadow: 0 -8px 30px rgba(0,0,0,0.15);'">

        {{-- Handle + title --}}
        <div x-show="mode !== 'manual'" style="text-align:center;margin-bottom:16px;">
            <div style="width:36px;height:4px;border-radius:2px;background:var(--bnb-border);margin:0 auto 14px;"></div>
            <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);" x-text="roomTypeName || 'تاریخ و تعداد نفرات را انتخاب کنید'"></div>
            <div x-show="roomTypeCapacity" style="font-size:12px;color:var(--bnb-gray);margin-top:4px;">
                <i class="bi bi-people me-1"></i><span x-text="'ظرفیت: ' + roomTypeCapacity + ' نفر'"></span>
            </div>
        </div>
        <div x-show="mode === 'manual'" style="margin-bottom:16px;">
            <div style="font-size:16px;font-weight:700;color:var(--bnb-dark);" x-text="roomTypeName || 'ابتدا اتاق را انتخاب کنید'"></div>
            <div x-show="roomTypeCapacity" style="font-size:12px;color:var(--bnb-gray);margin-top:4px;">
                <i class="bi bi-people me-1"></i><span x-text="'ظرفیت: ' + roomTypeCapacity + ' نفر'"></span>
            </div>
            <div x-show="datesLocked" class="alert alert-info py-2 px-3 mt-2 mb-0" style="font-size:12px;">
                <i class="bi bi-lock-fill me-1"></i>
                تاریخ ورود و خروج برای همه اتاق‌ها ثابت است:
                <span dir="ltr" x-text="jalStr(checkIn) + ' → ' + jalStr(checkOut)"></span>
            </div>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:16px;">
            <div :style="checkIn ? 'border-color:var(--bnb-red);background:rgba(255,56,92,.06);' : ''"
                 style="flex:1;border:1.5px solid var(--bnb-border);border-radius:10px;padding:9px 12px;text-align:center;">
                <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);margin-bottom:3px;">ورود · اولین شب</div>
                <div style="font-size:13px;font-weight:600;" x-text="checkIn ? jalStr(checkIn) : 'انتخاب'"></div>
            </div>
            <div :style="checkOut ? 'border-color:var(--bnb-red);background:rgba(255,56,92,.06);' : ''"
                 style="flex:1;border:1.5px solid var(--bnb-border);border-radius:10px;padding:9px 12px;text-align:center;">
                <div style="font-size:10px;font-weight:700;color:var(--bnb-gray);margin-bottom:3px;">خروج · روز پایان</div>
                <div style="font-size:13px;font-weight:600;" x-text="checkOut ? jalStr(checkOut) : 'انتخاب'"></div>
            </div>
        </div>

        {{-- Inline Jalali Calendar --}}
        <div style="margin-bottom:16px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <button type="button" @click.stop="calPrev()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&lsaquo;</button>
                <span style="font-size:14px;font-weight:700;" x-text="calMonthLabel"></span>
                <button type="button" @click.stop="calNext()" style="background:none;border:1px solid var(--bnb-border);border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;cursor:pointer;">&rsaquo;</button>
            </div>

            {{-- Legend (only for room-type bookings) --}}
            <div x-show="roomTypeId" class="bnb-avail-legend">
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#10b981;"></div>موجود</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#f59e0b;"></div>ظرفیت محدود</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#e5e7eb;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(0,0,0,0.1) 3px,rgba(0,0,0,0.1) 4px);"></div>تکمیل</div>
                <div class="bnb-avail-legend-item"><div class="bnb-legend-dot" style="background:#fff0f0;background-image:repeating-linear-gradient(-45deg,transparent,transparent 3px,rgba(220,38,38,0.15) 3px,rgba(220,38,38,0.15) 4px);border:1px solid #fca5a5;"></div>مسدود</div>
            </div>

            {{-- Loading state --}}
            <div x-show="availabilityLoading" class="bnb-avail-loading">
                <div class="bnb-spinner"></div>
                <span>در حال بررسی ظرفیت...</span>
            </div>

            {{-- Error state --}}
            <div x-show="availabilityError && !availabilityLoading" style="font-size:12px;color:#e53e3e;text-align:center;padding:8px;margin-bottom:8px;">
                <i class="bi bi-exclamation-triangle me-1"></i>خطا در بارگذاری ظرفیت. تقویم به‌صورت عادی نمایش داده می‌شود.
            </div>

            <div class="bnb-cal-square-grid" style="text-align:center;margin-bottom:4px;">
                <template x-for="h in ['ش','ی','د','س','چ','پ','ج']">
                    <span style="font-size:11px;color:var(--bnb-gray);padding:4px 0;" x-text="h"></span>
                </template>
            </div>
            <div class="bnb-cal-square-grid">
                <template x-for="(cell, idx) in calDays" :key="idx">
                    <button type="button"
                        :disabled="!cell || cell.past || cell.isUnavailable || cell.isBlocked || cell.disabledByGap"
                        @click.stop="cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && !cell.disabledByGap && selectDay(cell)"
                        @mouseenter="cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && (calHover = cell.greg)"
                        @mouseleave="calHover = null"
                        :title="cell && cell.availInfo ? cell.availInfo : ''"
                        class="bnb-cal-square-cell"
                        :class="{
                            'cal-start':       cell && isCheckInDay(cell),
                            'cal-end':         cell && isCheckOutDay(cell),
                            'cal-last-night':  cell && isLastStayNight(cell),
                            'cal-range':       cell && calInRange(cell),
                            'cal-selected':    cell && isStayNight(cell),
                            'cal-hover-range': cell && calHoverRange(cell),
                            'cal-empty':       !cell,
                            'cal-unavailable': cell && !cell.past && cell.isUnavailable,
                            'cal-blocked':     cell && !cell.past && cell.isBlocked,
                            'cal-low-avail':   cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && cell.isLowAvail,
                            'cal-avail':       cell && !cell.past && !cell.isUnavailable && !cell.isBlocked && !cell.isLowAvail && cell.hasAvailData
                        }">
                        <i x-show="cell && isStayNight(cell)" class="bi bi-check-lg cal-day-check"></i>
                        <div class="cd" x-text="cell ? cell.d : ''"></div>
                        <template x-if="cell && !cell.past && isCheckInDay(cell)">
                            <div class="cs">ورود</div>
                        </template>
                        <template x-if="cell && !cell.past && isCheckOutDay(cell)">
                            <div class="cs">خروج</div>
                        </template>
                        <template x-if="cell && !cell.past && cell.discountPct">
                            <div style="position:absolute;top:1px;left:2px;font-size:7px;background:#dc2626;color:#fff;border-radius:2px;padding:0 2px;line-height:1.4;font-weight:700;" x-text="cell.discountPct + '%'"></div>
                        </template>
                        <template x-if="cell && !cell.past && cell.priceLabel">
                            <div class="cal-meta" style="font-size:7px;line-height:1;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;" x-text="cell.priceLabel"></div>
                        </template>
                        <template x-if="cell && !cell.past && cell.effectivePrice">
                            <div class="cal-meta" style="font-size:9px;font-weight:700;line-height:1;margin-top:1px;" x-text="cell.effectivePrice.toLocaleString('fa-IR')"></div>
                        </template>
                    </button>
                </template>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div>
                    <span x-show="checkIn && checkOut" style="font-size:12px;color:var(--bnb-gray);" x-text="nights + ' شب اقامت'"></span>
                    <span x-show="checkIn && calPhase === 1" style="font-size:12px;color:var(--bnb-gray);">تاریخ خروج را انتخاب کنید</span>
                    <span x-show="!checkIn" style="font-size:12px;color:var(--bnb-gray);">روز شروع اقامت را انتخاب کنید</span>
                </div>
                <button x-show="checkIn" type="button" @click.stop="checkIn='';checkOut='';calPhase=0;"
                    style="background:none;border:none;font-size:12px;color:var(--bnb-gray);text-decoration:underline;cursor:pointer;font-family:var(--bnb-font);">پاک کردن</button>
            </div>
        </div>

        {{-- Adults counter --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--bnb-border);">
            <div>
                <div style="font-size:14px;font-weight:600;">تعداد نفرات</div>
                <div style="font-size:12px;color:var(--bnb-gray);">بزرگسال</div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <button type="button" class="bnb-cnt-btn" @click.stop="adults>1 && adults--" :disabled="adults<=1"><i class="bi bi-dash"></i></button>
                <span x-text="adults" style="min-width:24px;text-align:center;font-size:16px;font-weight:600;"></span>
                <button type="button" class="bnb-cnt-btn" @click.stop="adults<16 && adults++" :disabled="adults>=16"><i class="bi bi-plus"></i></button>
            </div>
        </div>

        {{-- Children under 6 --}}
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-top:1px solid var(--bnb-border);">
            <div>
                <div style="font-size:14px;font-weight:600;">کودک زیر ۶ سال</div>
                <div style="font-size:12px;color:var(--bnb-gray);line-height:1.6;">
                    <span x-text="childDiscountPct <= 0 ? 'بدون تخفیف نرخ اقامت' : (childDiscountPct >= 100 ? 'رایگان (شامل ۱۰۰ درصد تخفیف)' : ('شامل ' + childDiscountPct + ' درصد تخفیف'))"></span>
                    <span> · </span>
                    <span x-text="childAllocateBed ? 'تخت اختصاص داده می‌شود' : 'تخت اختصاص داده نمی‌شود'"></span>
                    @if($isManual && $accommodationEditUrl)
                    <span> · </span>
                    <span>برای تغییر این سیاست <a href="{{ $accommodationEditUrl }}" wire:navigate style="color:inherit;text-decoration:underline;">اطلاعات اقامتگاه</a> را بزنید.</span>
                    @endif
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <button type="button" class="bnb-cnt-btn" @click.stop="childrenUnder6>0 && childrenUnder6--" :disabled="childrenUnder6<=0"><i class="bi bi-dash"></i></button>
                <span x-text="childrenUnder6" style="min-width:24px;text-align:center;font-size:16px;font-weight:600;"></span>
                <button type="button" class="bnb-cnt-btn" @click.stop="childrenUnder6 < maxChildrenUnder6 && childrenUnder6++" :disabled="childrenUnder6>=maxChildrenUnder6"><i class="bi bi-plus"></i></button>
            </div>
        </div>

        {{-- Dynamic price breakdown (shown when dates selected) --}}
        <div x-show="checkIn && checkOut && hasDynamicPricing" style="margin:-4px 0 14px;border:1px solid var(--bnb-border);border-radius:10px;overflow:hidden;">
            <div style="padding:7px 12px;background:#f9fafb;font-size:11px;font-weight:700;color:var(--bnb-gray);border-bottom:1px solid var(--bnb-border);display:flex;align-items:center;justify-content:space-between;">
                <span>قیمت به تفکیک شب</span>
                @auth
                @if(auth()->user()->discount_percentage > 0)
                <span style="background:#fef9c3;color:#854d0e;border-radius:4px;padding:2px 7px;font-size:10px;font-weight:700;"><i class="bi bi-star-fill me-1" style="font-size:9px;"></i>{{ auth()->user()->veteranLabel() }} · {{ auth()->user()->discount_percentage }}٪ تخفیف</span>
                @endif
                @endauth
            </div>
            <template x-for="(p, i) in dynamicNightPrices" :key="i">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:5px 12px;font-size:12px;border-bottom:1px solid #f3f4f6;">
                    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;">
                        <span style="font-weight:600;" x-text="new persianDate(new Date(p.date + 'T12:00:00')).format('DD MMM')"></span>
                        <template x-if="p.label">
                            <span :style="p.hostDiscountPct > 0 ? 'font-size:10px;background:#fff7ed;color:#c2410c;border-radius:4px;padding:1px 5px;font-weight:700;' : 'font-size:10px;background:#eff6ff;color:#1e40af;border-radius:4px;padding:1px 5px;font-weight:600;'"
                                  x-text="p.label + (p.hostDiscountPct > 0 ? ' · ' + p.hostDiscountPct + '%' : '')"></span>
                        </template>
                        <template x-if="!p.label && p.hostDiscountPct > 0">
                            <span style="font-size:10px;background:#fff7ed;color:#c2410c;border-radius:4px;padding:1px 5px;font-weight:700;" x-text="'تخفیف میزبان ' + p.hostDiscountPct + '%'"></span>
                        </template>
                        @auth
                        @if(auth()->user()->discount_percentage > 0)
                        <span style="font-size:10px;background:#fef9c3;color:#854d0e;border-radius:4px;padding:1px 5px;font-weight:700;"><i class="bi bi-star-fill me-1" style="font-size:9px;"></i>{{ auth()->user()->discount_percentage }}%</span>
                        @endif
                        @endauth
                    </div>
                    <div style="text-align:left;white-space:nowrap;">
                        <template x-if="p.baseRate > p.price">
                            <span style="font-size:10px;text-decoration:line-through;color:var(--bnb-gray);margin-left:4px;" x-text="p.baseRate.toLocaleString('fa-IR')"></span>
                        </template>
                        @auth
                        @if(auth()->user()->discount_percentage > 0)
                        <template x-if="p.hostEffective < p.baseRate && p.price < p.hostEffective">
                            <span style="font-size:10px;text-decoration:line-through;color:#f97316;margin-left:4px;" x-text="p.hostEffective.toLocaleString('fa-IR')"></span>
                        </template>
                        @endif
                        @endauth
                        <span style="font-weight:700;color:var(--bnb-dark);" x-text="p.price.toLocaleString('fa-IR') + ' ت'"></span>
                    </div>
                </div>
            </template>
            <div style="display:flex;justify-content:space-between;padding:7px 12px;font-size:13px;font-weight:700;background:#f9fafb;">
                <span>جمع کل</span>
                <div style="display:flex;align-items:baseline;gap:6px;">
                    <template x-if="dynamicOriginalTotal > dynamicTotal">
                        <span style="font-size:11px;text-decoration:line-through;color:var(--bnb-gray);" x-text="dynamicOriginalTotal.toLocaleString('fa-IR')"></span>
                    </template>
                    @auth
                    @if(auth()->user()->discount_percentage > 0)
                    <template x-if="dynamicAfterHostTotal < dynamicOriginalTotal && dynamicAfterHostTotal > dynamicTotal">
                        <span style="font-size:11px;text-decoration:line-through;color:#f97316;" x-text="dynamicAfterHostTotal.toLocaleString('fa-IR')"></span>
                    </template>
                    @endif
                    @endauth
                    <span style="color:var(--bnb-red);" x-text="dynamicTotal.toLocaleString('fa-IR') + ' تومان'"></span>
                </div>
            </div>
            {{-- Extra guests line --}}
            <template x-if="extraGuests > 0 && extraCapacityPrice > 0">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 12px;font-size:12px;background:#f0fdf4;border-top:1px solid #bbf7d0;">
                    <span style="color:#15803d;font-weight:600;display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                        <i class="bi bi-person-add"></i><span x-text="extraGuests + ' نفر کف‌خواب'"></span>
                        @auth
                        @if(auth()->user()->discount_percentage > 0)
                        <span style="font-size:10px;background:#fef9c3;color:#854d0e;border-radius:4px;padding:1px 5px;font-weight:700;"><i class="bi bi-star-fill me-1" style="font-size:9px;"></i>{{ auth()->user()->discount_percentage }}%</span>
                        @endif
                        @endauth
                    </span>
                    <div style="text-align:left;white-space:nowrap;">
                        <template x-if="userDiscountPct > 0 && extraGuestsOriginalTotal > extraGuestsTotal">
                            <span style="font-size:10px;text-decoration:line-through;color:var(--bnb-gray);margin-left:4px;" x-text="extraGuestsOriginalTotal.toLocaleString('fa-IR')"></span>
                        </template>
                        <span style="color:#15803d;font-weight:700;" x-text="extraGuestsTotal.toLocaleString('fa-IR') + ' تومان'"></span>
                    </div>
                </div>
            </template>
        </div>

        {{-- Confirm dates button --}}
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="button" @click="confirmDates()"
                    :disabled="!checkIn || !checkOut"
                    :style="(!checkIn || !checkOut) ? 'opacity:.5;cursor:not-allowed;' : ''"
                    class="btn-bnb"
                    style="width:auto;min-width:140px;padding:14px 20px;font-size:15px;border-radius:12px;display:inline-flex;align-items:center;">
                <i class="bi bi-check-circle me-1"></i>
                <span x-text="mode === 'manual' ? (datesLocked ? 'افزودن این اتاق' : 'تأیید انتخاب') : 'تأیید'"></span>
            </button>
        </div>
    </div>

    {{-- Sticky bottom pay bar --}}
    <div x-show="mode !== 'manual' && datesConfirmed && checkIn && checkOut && targetForm" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bnb-pay-bar">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;max-width:600px;margin:0 auto;">
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">
                    <template x-if="dynamicOriginalTotal > dynamicTotal">
                        <span style="font-size:12px;text-decoration:line-through;color:var(--bnb-gray);font-weight:400;" x-text="dynamicOriginalTotal.toLocaleString('fa-IR') + ' تومان'"></span>
                    </template>
                    @auth
                    @if(auth()->user()->discount_percentage > 0)
                    <template x-if="dynamicAfterHostTotal < dynamicOriginalTotal && dynamicAfterHostTotal > dynamicTotal">
                        <span style="font-size:12px;text-decoration:line-through;color:#f97316;font-weight:400;" x-text="dynamicAfterHostTotal.toLocaleString('fa-IR') + ' تومان'"></span>
                    </template>
                    @endif
                    @endauth
                    <span style="font-size:16px;font-weight:700;color:var(--bnb-dark);" x-text="dynamicTotal.toLocaleString('fa-IR') + ' تومان'"></span>
                </div>
                <div style="font-size:12px;color:var(--bnb-gray);margin-top:2px;" x-text="nights + ' شب · ' + totalGuests + ' نفر' + (billFullRooms ? ' (رزرو ' + billableGuests + ' تخت)' : '') + (extraGuests > 0 ? ' (' + extraGuests + ' کف‌خواب)' : '')"></div>
            </div>
            <button type="button" data-async-btn @click="pay()"
                    class="btn-bnb"
                    style="padding:12px 28px;border-radius:12px;font-size:14px;white-space:nowrap;flex-shrink:0;">
                <i class="bi bi-credit-card me-1"></i> پرداخت
            </button>
        </div>
    </div>

