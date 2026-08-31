@props(['accommodationId', 'defaultDiscountPct' => 0, 'includeCalMixin' => true, 'childAllocateBed' => true, 'childDiscountPct' => 50])
<script>
@if($includeCalMixin)
// ─── Shared stay-night date picker helpers ───────────────────────────────────
window.bnbStayPicker = {
    addDays(greg, days) {
        const d = new Date(greg + 'T12:00:00');
        d.setDate(d.getDate() + days);
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    },

    nights(checkIn, checkOut) {
        if (!checkIn || !checkOut) return 0;
        return Math.round((new Date(checkOut + 'T12:00:00') - new Date(checkIn + 'T12:00:00')) / 86400000);
    },

    maxStayNights: 365,

    checkOutFromNights(checkIn, nights) {
        const n = parseInt(nights, 10);
        if (!checkIn || !Number.isFinite(n) || n < 1) return '';
        return this.addDays(checkIn, n);
    },

    validateNightsInput(nights) {
        const n = parseInt(nights, 10);
        if (!Number.isFinite(n) || n < 1) {
            return { valid: false, message: 'لطفاً تعداد شب اقامت را وارد کنید (حداقل ۱ شب).' };
        }
        if (n > this.maxStayNights) {
            return { valid: false, message: 'حداکثر مدت اقامت ' + this.maxStayNights + ' شب است.' };
        }
        return { valid: true, message: '' };
    },

    lastStayNight(checkIn, checkOut) {
        if (!checkIn || !checkOut || checkOut <= checkIn) return '';
        return this.addDays(checkOut, -1);
    },

    isStayNight(greg, checkIn, checkOut) {
        return !!(checkIn && checkOut && greg >= checkIn && greg < checkOut);
    },

    isCheckInDay(greg, checkIn) {
        return !!(checkIn && greg === checkIn);
    },

    isCheckOutDay(greg, checkOut) {
        return !!(checkOut && greg === checkOut);
    },

    isLastStayNight(greg, checkIn, checkOut) {
        const last = this.lastStayNight(checkIn, checkOut);
        return !!(last && greg === last && greg !== checkIn);
    },

    calInRange(greg, checkIn, checkOut) {
        const last = this.lastStayNight(checkIn, checkOut);
        if (!last || greg <= checkIn || greg >= last) return false;
        return greg > checkIn && greg < last;
    },

    calHoverRange(greg, checkIn, hoverGreg, calPhase) {
        if (!greg || calPhase !== 1 || !checkIn || !hoverGreg || hoverGreg <= checkIn) return false;
        return greg >= checkIn && greg < hoverGreg;
    },

    selectDay(cell, state) {
        if (!cell || cell.past) return null;
        const g = cell.greg;
        if (state.calPhase === 0) {
            return { checkIn: g, checkOut: '', calPhase: 1 };
        }
        if (g < state.checkIn) {
            return { checkIn: g, checkOut: '', calPhase: 1 };
        }
        if (g === state.checkIn) {
            return { checkIn: g, checkOut: this.addDays(g, 1), calPhase: 0 };
        }
        return { checkIn: state.checkIn, checkOut: g, calPhase: 0 };
    },

    todayGregorian() {
        if (window.BonyadJalaliDate && typeof window.BonyadJalaliDate.todayGregorian === 'function') {
            return window.BonyadJalaliDate.todayGregorian();
        }
        const d = new Date();
        return d.getFullYear() + '-'
            + String(d.getMonth() + 1).padStart(2, '0') + '-'
            + String(d.getDate()).padStart(2, '0');
    },

    isTodayGregorian(greg) {
        return !!greg && greg === this.todayGregorian();
    },
};

// ─── Jalali calendar grid helpers (headers: ش ی د س چ پ ج = Sat-first) ───────
window.bnbJalaliCal = window.bnbJalaliCal || {
    /** JS Date.getDay() / Carbon: 0=Sun … 6=Sat → column 0=Sat … 6=Fri (matches PHP JalaliCalendarGrid) */
    satFirstColumnForJsDow(jsGetDay) {
        return (jsGetDay + 1) % 7;
    },

    /** Grid offset for jalali month — same algorithm as App\Support\JalaliCalendarGrid */
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

function bnbCalMixin() {
    return {
        _effectiveCheckOut() {
            if (this.checkOut) return this.checkOut;
            if (typeof this.previewCheckOut === 'string' && this.previewCheckOut) return this.previewCheckOut;
            return '';
        },
        isStayNight(cell) {
            const co = this._effectiveCheckOut();
            return cell && window.bnbStayPicker.isStayNight(cell.greg, this.checkIn, co);
        },
        isCheckInDay(cell) {
            return cell && window.bnbStayPicker.isCheckInDay(cell.greg, this.checkIn);
        },
        isCheckOutDay(cell) {
            const co = this._effectiveCheckOut();
            return cell && window.bnbStayPicker.isCheckOutDay(cell.greg, co);
        },
        isLastStayNight(cell) {
            const co = this._effectiveCheckOut();
            return cell && window.bnbStayPicker.isLastStayNight(cell.greg, this.checkIn, co);
        },
        calInRange(cell) {
            const co = this._effectiveCheckOut();
            return cell && window.bnbStayPicker.calInRange(cell.greg, this.checkIn, co);
        },
        calHoverRange(cell) {
            return cell && window.bnbStayPicker.calHoverRange(cell.greg, this.checkIn, this.calHover, this.calPhase);
        },
        isTodayDay(cell) {
            return cell && window.bnbStayPicker.isTodayGregorian(cell.greg);
        },
        isHoverCheckoutDay(cell) {
            if (!cell || this.calPhase !== 1 || !this.checkIn || !this.calHover || this.calHover <= this.checkIn) return false;
            return cell.greg === this.calHover;
        },
        applyStaySelection(cell, validateRange) {
            const next = window.bnbStayPicker.selectDay(cell, {
                checkIn: this.checkIn,
                checkOut: this.checkOut,
                calPhase: this.calPhase
            });
            if (!next) return false;
            if (next.checkOut) {
                const lastNight = window.bnbStayPicker.addDays(next.checkOut, -1);
                if (typeof validateRange === 'function' && !validateRange(next.checkIn, lastNight)) return false;
            }
            this.checkIn = next.checkIn;
            this.checkOut = next.checkOut;
            this.calPhase = next.calPhase;
            return true;
        }
    };
}
@endif

// ── Rooms section Alpine.js component ──────────────────────────────────────
function roomsSection() {
    return {
        guestCount: 1,
        availData:  {}, // roomTypeId → { min_available, is_available, room_count, capacity }
        loading:    false,

        init() {
            // Sync guest count from sidebar booking widget on next tick
            this.$nextTick(() => {
                try {
                    const w = document.querySelector('[x-data^="bnbBookWidget"]');
                    if (w && typeof Alpine !== 'undefined') {
                        const wd = Alpine.$data(w);
                        if (wd && wd.guests) this.guestCount = parseInt(wd.guests) || 1;
                        if (wd && wd.checkIn && wd.checkOut) {
                            this.fetchAllAvail(wd.checkIn, wd.checkOut);
                        }
                    }
                } catch(e) {}
            });
        },

        /**
         * اتاق‌های مورد نیاز = ceil(تعداد نفرات / ظرفیت هر اتاق)
         */
        roomsNeeded(cap) {
            return Math.ceil(this.guestCount / Math.max(1, parseInt(cap)));
        },

        /**
         * حداقل اتاق موجود برای این نوع اتاق در بازه تاریخی انتخابی
         * اگر تاریخ انتخاب نشده → null
         */
        minAvail(rtId) {
            const d = this.availData[rtId];
            return d !== undefined ? d.min_available : null;
        },

        /**
         * آیا باید اتاق هاشور بخورد؟
         * اگر تاریخ انتخاب شده: rooms_needed > min_available
         * اگر تاریخ انتخاب نشده: rooms_needed > room_count (ظرفیت کل)
         */
        needsHatch(cap, roomCount, rtId) {
            const needed = this.roomsNeeded(cap);
            const avail  = this.minAvail(rtId);
            if (avail !== null) {
                return needed > avail;
            }
            return needed > parseInt(roomCount);
        },

        async fetchAllAvail(checkIn, checkOut) {
            if (!checkIn || !checkOut) return;
            this.loading = true;
            try {
                const params = new URLSearchParams({ check_in: checkIn, check_out: checkOut });
                const resp = await fetch('/api/accommodations/{{ $accommodationId }}/rooms-availability?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (resp.ok) this.availData = await resp.json();
            } catch(e) {}
            this.loading = false;
        }
    };
}

function reserveRoom(btn, pricePerNight, origPrice, roomTypeId, roomCapacity, extraCapacity, extraCapacityPrice) {
    var form  = btn.closest('form');
    var price = pricePerNight || 0;
    var orig  = origPrice || price;
    var rtId  = roomTypeId || null;
    var rtCap = roomCapacity || 1;
    var extraCap   = parseInt(extraCapacity)   || 0;
    var extraPrice = parseInt(extraCapacityPrice) || 0;

    // Read room-type meta from the card
    var card      = btn.closest('.bnb-room-card');
    var rtName    = card ? (card.querySelector('[data-rt-name]')?.dataset.rtName || '') : '';

    var drawerEl = document.querySelector('[data-bnb-drawer]');
    if (drawerEl && typeof Alpine !== 'undefined') {
        var drawer = Alpine.$data(drawerEl);
        drawer.openForRoom(form, price, orig, rtId, rtName, rtCap, extraCap, extraPrice);
    }
}

function mbbDrawer() {
    return {
        ...bnbCalMixin(),
        mode: 'public',
        drawerOpen: false,
        datesConfirmed: false,
        datesLocked: false,
        checkIn: '',
        checkOut: '',
        adults: 1,
        childrenUnder6: 0,
        calYear: null,
        calMonth: null,
        calPhase: 0,
        calHover: null,
        stayNightsInput: '',
        stayCheckOutInput: '',
        stayDurationMode: 'nights',
        targetForm: null,
        pricePerNight: 0,
        originalPrice: 0,
        userDiscountPct: {{ (int) $defaultDiscountPct }},
        platformCommissionFixed: {{ (int) config('platform_commission.fixed_amount', 50_000) }},
        childAllocateBed: {{ $childAllocateBed ? 'true' : 'false' }},
        childDiscountPct: {{ (int) $childDiscountPct }},
        // Room capacity (guests per room) — used to compute rooms_needed for warning
        roomTypeCapacityNum: 1,
        // Extra capacity (floor sleeping / کف‌خوابی)
        extraCapacity: 0,         // max extra guests allowed for this room type
        extraCapacityPrice: 0,    // price per extra guest per night (undiscounted)
        extraGuests: 0,           // chosen extra guests for this booking
        billFullRooms: false,     // charge for all beds in reserved rooms
        // Availability
        roomTypeId: null,
        roomRateId: null,
        roomTypeName: '',
        roomTypeCapacity: '',
        availabilityData: {},
        availabilityLoading: false,
        availabilityError: false,
        loadedMonths: [],
        selectedRoomId: null,
        selectedRoomName: '',
        selectedRooms: [],
        pendingCommitExtraG: 0,
        pendingCommitFullRoom: false,
        pendingPreselectedRoomId: null,
        pendingPreselectedRoomName: '',

        get nights() {
            if (this.checkOut) {
                return window.bnbStayPicker.nights(this.checkIn, this.checkOut);
            }
            const preview = this.previewCheckOut;
            return preview ? window.bnbStayPicker.nights(this.checkIn, preview) : 0;
        },

        get awaitingStayDuration() {
            return this.mode === 'manual' && !!this.checkIn && !this.checkOut && this.calPhase === 1;
        },

        get previewCheckOut() {
            if (this.mode !== 'manual' || !this.checkIn || this.checkOut) return '';
            if (this.stayDurationMode === 'checkout') {
                const g = this._parseJalaliCheckoutInput();
                return g && g > this.checkIn ? g : '';
            }
            const validation = window.bnbStayPicker.validateNightsInput(this.stayNightsInput);
            if (!validation.valid) return '';
            return window.bnbStayPicker.checkOutFromNights(this.checkIn, this.stayNightsInput);
        },

        get roomsNeeded() {
            return this.effectiveRoomsNeeded;
        },

        get totalGuests() {
            return Math.max(1, this.adults + this.childrenUnder6);
        },

        get guestsForBeds() {
            if (this.childAllocateBed) {
                return this.totalGuests;
            }
            return Math.max(1, this.totalGuests - this.childrenUnder6);
        },

        get childPayMultiplier() {
            return (100 - Math.max(0, Math.min(100, this.childDiscountPct))) / 100;
        },

        get effectiveRoomsNeeded() {
            const cap = Math.max(1, this.roomTypeCapacityNum);
            const total = this.guestsForBeds;
            if (this.extraGuests > 0) {
                return Math.max(1, Math.ceil((total - this.extraGuests) / cap));
            }
            return Math.ceil(total / cap);
        },

        get billableGuests() {
            if (this.billFullRooms) {
                return this.effectiveRoomsNeeded * Math.max(1, this.roomTypeCapacityNum);
            }
            return Math.max(1, this.totalGuests - this.extraGuests);
        },

        get maxChildrenUnder6() {
            return Math.max(0, 20 - this.adults);
        },

        get effectiveChildGuests() {
            return Math.min(Math.max(0, this.childrenUnder6), this.billableGuests);
        },

        _nightAccommodationTotal(perPerson, billingGuests, childGuests) {
            const childMult = this.childPayMultiplier;
            const fullRate = billingGuests - childGuests;
            const raw = perPerson * fullRate + perPerson * childMult * childGuests;
            return this.userDiscountPct > 0
                ? Math.round(raw * (1 - this.userDiscountPct / 100))
                : raw;
        },

        get dynamicNightPrices() {
            if (!this.checkIn || !this.checkOut) return [];
            const prices = [];
            const g = this.billableGuests;
            const childG = this.effectiveChildGuests;
            let d = new Date(this.checkIn + 'T12:00:00');
            const end = new Date(this.checkOut + 'T12:00:00');
            while (d < end) {
                const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                const avail = this.availabilityData[key];
                const baseRatePerPerson      = this.originalPrice || this.pricePerNight;
                const hostEffectivePerPerson = (avail && avail.effective_price != null) ? avail.effective_price : baseRatePerPerson;
                const hostDiscountPct        = (avail && avail.discount_percentage) ? avail.discount_percentage : 0;
                const baseRate      = baseRatePerPerson * (g - childG) + baseRatePerPerson * this.childPayMultiplier * childG;
                const hostEffective = hostEffectivePerPerson * (g - childG) + hostEffectivePerPerson * this.childPayMultiplier * childG;
                const finalPrice    = this._nightAccommodationTotal(hostEffectivePerPerson, g, childG);
                prices.push({
                    date: key,
                    baseRate,
                    hostEffective,
                    hostDiscountPct,
                    price: finalPrice,
                    label: (avail && avail.price_label) ? avail.price_label : '',
                    discount: hostDiscountPct,
                    origPrice: baseRate,
                });
                d.setDate(d.getDate() + 1);
            }
            return prices;
        },

        // Total cost of extra guests (fixed price, no per-night override)
        get extraGuestsOriginalTotal() {
            if (!this.extraGuests || !this.extraCapacityPrice || !this.nights) return 0;
            return this.extraGuests * this.extraCapacityPrice * this.nights;
        },

        get extraGuestsTotal() {
            const raw = this.extraGuestsOriginalTotal;
            return this.userDiscountPct > 0 ? Math.round(raw * (1 - this.userDiscountPct / 100)) : raw;
        },

        get dynamicTotal() {
            const prices = this.dynamicNightPrices;
            const g = this.billableGuests;
            const childG = this.effectiveChildGuests;
            if (!prices.length) {
                const perPerson = this.originalPrice || this.pricePerNight;
                return this.nights * this._nightAccommodationTotal(perPerson, g, childG) + this.extraGuestsTotal;
            }
            return prices.reduce((s, p) => s + p.price, 0) + this.extraGuestsTotal;
        },

        get platformCommissionAmount() {
            if (this.mode === 'manual') return 0;
            const subtotal = this.dynamicTotal;
            return subtotal > 0 ? (this.platformCommissionFixed || 0) : 0;
        },

        get payableTotal() {
            return this.dynamicTotal + this.platformCommissionAmount;
        },

        get dynamicAfterHostTotal() {
            const prices = this.dynamicNightPrices;
            const g = this.billableGuests;
            const childG = this.effectiveChildGuests;
            if (!prices.length) {
                const perPerson = this.originalPrice || this.pricePerNight;
                return this.nights * (perPerson * (g - childG) + perPerson * this.childPayMultiplier * childG) + this.extraGuestsOriginalTotal;
            }
            return prices.reduce((s, p) => s + p.hostEffective, 0) + this.extraGuestsOriginalTotal;
        },

        get dynamicOriginalTotal() {
            const prices = this.dynamicNightPrices;
            const g = this.billableGuests;
            const childG = this.effectiveChildGuests;
            if (!prices.length) {
                const perPerson = this.originalPrice || this.pricePerNight;
                return this.nights * (perPerson * (g - childG) + perPerson * this.childPayMultiplier * childG) + this.extraGuestsOriginalTotal;
            }
            return prices.reduce((s, p) => s + p.baseRate, 0) + this.extraGuestsOriginalTotal;
        },

        get hasDynamicPricing() {
            return !!(this.checkIn && this.checkOut && this.dynamicNightPrices.length);
        },

        // Minimum available rooms across the selected date range
        get minAvailInRange() {
            if (!this.checkIn || !this.checkOut) return null;
            let min = Infinity, count = 0;
            for (const [d, a] of Object.entries(this.availabilityData)) {
                if (d >= this.checkIn && d < this.checkOut) {
                    min = Math.min(min, a.available_rooms);
                    count++;
                }
            }
            return count > 0 && min < Infinity ? min : null;
        },

        get calMonthLabel() {
            if (!this.calYear) return '';
            const n = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
        },

        _isInvalidStayNight(greg) {
            const avail = this.availabilityData[greg];
            if (!avail) return false;
            return avail.is_blocked || avail.available_rooms <= 0;
        },

        _hasInvalidNightInRange(fromNight, toNight) {
            if (!fromNight || !toNight || toNight < fromNight) return true;
            let d = fromNight;
            while (d <= toNight) {
                if (this._isInvalidStayNight(d)) return true;
                d = window.bnbStayPicker.addDays(d, 1);
            }
            return false;
        },

        get calDays() {
            if (!this.calYear || typeof persianDate === 'undefined') return [];
            const pd   = new persianDate([this.calYear, this.calMonth, 1]);
            const dim  = pd.daysInMonth();
            const now  = new persianDate();
            const ty = now.year(), tm = now.month(), td = now.date();
            const offset = window.bnbJalaliCal.monthStartOffset(this.calYear, this.calMonth);

            let cells = [];
            for (let i = 0; i < offset; i++) cells.push(null);
            for (let d = 1; d <= dim; d++) {
                const greg = window.bnbJalaliCal.toGregorian(this.calYear, this.calMonth, d);
                const past = (this.calYear < ty) || (this.calYear === ty && this.calMonth < tm) || (this.calYear === ty && this.calMonth === tm && d < td);

                const avail        = this.availabilityData[greg];
                const hasAvailData = !!avail;
                const isBlocked    = avail ? avail.is_blocked : false;
                const isUnavailable= avail ? (!avail.is_blocked && avail.available_rooms <= 0) : false;
                const isLowAvail   = avail ? (!avail.is_blocked && avail.available_rooms > 0 && avail.available_rooms < avail.total) : false;

                const disabledByGap = this.mode !== 'manual' && this.calPhase === 1 && this.checkIn && (
                    greg < this.checkIn
                    || (greg > this.checkIn && this._hasInvalidNightInRange(
                        this.checkIn,
                        window.bnbStayPicker.addDays(greg, -1)
                    ))
                );

                let availInfo = '';
                let roomCountDisplay = '';
                if (avail && !past) {
                    if (avail.is_blocked) {
                        availInfo = 'مسدود شده توسط میزبان';
                        roomCountDisplay = 'مسدود';
                    } else if (avail.available_rooms <= 0) {
                        availInfo = 'تمام شد';
                        roomCountDisplay = 'تمام';
                    } else {
                        availInfo = avail.available_rooms + ' اتاق موجود';
                        roomCountDisplay = avail.available_rooms + ' اتاق';
                    }
                }

                const rawEffective   = (avail && avail.effective_price != null) ? avail.effective_price : null;
                const effectivePrice = rawEffective !== null && this.userDiscountPct > 0
                    ? Math.round(rawEffective * (1 - this.userDiscountPct / 100))
                    : rawEffective;
                const discountPct    = (avail && avail.discount_percentage) ? avail.discount_percentage : 0;
                const priceLabel     = (avail && avail.price_label) ? avail.price_label : '';

                cells.push({ d, greg, past, isBlocked, isUnavailable, isLowAvail, hasAvailData, disabledByGap, availInfo, roomCountDisplay, effectivePrice, discountPct, priceLabel });
            }
            return cells;
        },

        init() {
            this.mode = this.$el.dataset.bnbMode || 'public';
            const pct = this.$el.dataset.userDiscountPct;
            if (pct !== undefined && pct !== '') {
                this.userDiscountPct = parseInt(pct, 10) || 0;
            }
            if (typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.$watch('drawerOpen', val => {
                if (this.mode === 'manual') return;
                const nav = document.querySelector('.bnb-bottom-nav');
                if (nav) nav.style.display = val ? 'none' : '';

                if (val && this.checkIn) {
                    try {
                        const pDate = new persianDate(new Date(this.checkIn + 'T12:00:00'));
                        this.calYear = pDate.year();
                        this.calMonth = pDate.month();
                    } catch(e) {}
                }
            });
            // Dispatch guest count changes to rooms section
            this.$watch('adults', val => {
                window.dispatchEvent(new CustomEvent('bnb-guests-changed', { detail: { guests: this.guestsForBeds, totalGuests: this.totalGuests } }));
            });
            this.$watch('childrenUnder6', () => {
                window.dispatchEvent(new CustomEvent('bnb-guests-changed', { detail: { guests: this.guestsForBeds, totalGuests: this.totalGuests } }));
            });
            this.$watch('checkIn',  () => { if (this.drawerOpen || this.mode === 'manual') this.datesConfirmed = false; });
            this.$watch('checkOut', () => { if (this.drawerOpen || this.mode === 'manual') this.datesConfirmed = false; });
            // Pre-fill from booking widget if available
            const widgetEl = document.querySelector('[x-data^="bnbBookWidget"]');
            if (widgetEl && typeof Alpine !== 'undefined') {
                this.$nextTick(() => {
                    try {
                        const w = Alpine.$data(widgetEl);
                        if (w.checkIn)  this.checkIn  = w.checkIn;
                        if (w.checkOut) this.checkOut = w.checkOut;
                        if (w.guests) {
                            this.adults = parseInt(w.guests, 10) || 1;
                            this.childrenUnder6 = 0;
                        }
                    } catch(e) {}
                });
            }
            window.addEventListener('nav-search-updated', (e) => {
                if (e.detail.checkIn)  this.checkIn  = e.detail.checkIn;
                if (e.detail.checkOut) this.checkOut = e.detail.checkOut;
                if (e.detail.guests) {
                    this.adults = parseInt(e.detail.guests, 10) || 1;
                    this.childrenUnder6 = 0;
                }
            });
            window.addEventListener('manual-booking-set-discount', (e) => {
                this.userDiscountPct = parseInt(e.detail?.pct || 0, 10) || 0;
            });
            window.addEventListener('manual-booking-room-committed', (e) => {
                this.datesLocked = true;
                this.datesConfirmed = true;
                this.roomTypeId = null;
                this.roomTypeName = '';
                this.roomTypeCapacity = '';
                this.roomTypeCapacityNum = 1;
                this.adults = 1;
                this.childrenUnder6 = 0;
                this.extraGuests = 0;
                this.billFullRooms = false;
                this.targetForm = null;
                if (e.detail?.checkIn) this.checkIn = e.detail.checkIn;
                if (e.detail?.checkOut) this.checkOut = e.detail.checkOut;
            });
            window.addEventListener('manual-booking-dates-unlocked', () => {
                this.datesLocked = false;
                this.datesConfirmed = false;
                this.clearDatesSelection();
            });
            window.addEventListener('manual-booking-room-selected', (e) => {
                if (this.mode !== 'manual') return;
                const rooms = e.detail?.roomId
                    ? [{ roomId: e.detail.roomId, roomName: e.detail.roomName || '' }]
                    : [];
                this._finalizeManualRoomCommit(rooms);
            });
            window.addEventListener('manual-booking-rooms-selected', (e) => {
                if (this.mode !== 'manual') return;
                this._finalizeManualRoomCommit(e.detail?.rooms || []);
            });
            window.addEventListener('manual-booking-excluded-rooms', (e) => {
                if (this.mode !== 'manual') return;
                this._showRoomPicker(e.detail?.roomIds || []);
            });

            const prefillRt = parseInt(this.$el.dataset.bnbPrefillRoomTypeId, 10) || null;
            const prefillRate = parseInt(this.$el.dataset.bnbPrefillRoomRateId, 10) || null;
            if (this.mode === 'manual' && prefillRt && prefillRate) {
                this.pendingPreselectedRoomId = parseInt(this.$el.dataset.bnbPrefillRoomId, 10) || null;
                this.pendingPreselectedRoomName = this.$el.dataset.bnbPrefillRoomName || '';
                this.$nextTick(() => this._applyManualPrefill(prefillRt, prefillRate));
            }

            if (this.mode === 'manual' && this.$el.dataset.bnbPrefillFocus === '1') {
                document.addEventListener('livewire:navigated', () => {
                    if (!document.getElementById('manual-booking-prefill-summary')) return;
                    setTimeout(() => this._scrollToTodayInCalendar(), 350);
                }, { once: true });
            }
        },

        _applyManualPrefill(roomTypeId, roomRateId) {
            const el = this.$el;
            const rtName = el.dataset.bnbPrefillRoomTypeName || '';
            const rtCap = parseInt(el.dataset.bnbPrefillRoomCapacity, 10) || 1;
            const price = parseInt(el.dataset.bnbPrefillPrice, 10) || parseInt(el.dataset.bnbPrefillOrigPrice, 10) || 0;
            const origPrice = parseInt(el.dataset.bnbPrefillOrigPrice, 10) || price;
            const extraCap = parseInt(el.dataset.bnbPrefillExtraCap, 10) || 0;
            const extraPrice = parseInt(el.dataset.bnbPrefillExtraPrice, 10) || 0;

            let form = document.getElementById('bnb-prefill-reserve-form');
            if (!form) {
                form = document.createElement('form');
                form.id = 'bnb-prefill-reserve-form';
                form.className = 'd-none';
                form.setAttribute('aria-hidden', 'true');
                form.innerHTML = '<input type="hidden" name="room_type_id"><input type="hidden" name="room_rate_id">';
                document.body.appendChild(form);
            }
            form.querySelector('[name="room_type_id"]').value = roomTypeId;
            form.querySelector('[name="room_rate_id"]').value = roomRateId;

            this.openForRoom(form, price, origPrice, roomTypeId, rtName, rtCap, extraCap, extraPrice);
        },

        _scrollToTodayInCalendar() {
            const scroll = () => {
                const panel = document.getElementById('bnb-manual-drawer-panel');
                if (!panel) return false;
                const todayCell = panel.querySelector('.bnb-cal-square-cell.cal-today:not(.cal-empty)');
                if (!todayCell) return false;
                todayCell.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                return true;
            };
            this.$nextTick(() => {
                if (scroll()) return;
                setTimeout(() => { if (!scroll()) setTimeout(scroll, 300); }, 180);
            });
        },

        _finalizeManualRoomCommit(rooms) {
            this.selectedRooms = Array.isArray(rooms) ? rooms : [];
            this.extraGuests = this.pendingCommitExtraG || 0;
            this.billFullRooms = this.pendingCommitFullRoom || false;
            this._emitManualCommit();
            this.selectedRooms = [];
            this.selectedRoomId = null;
            this.selectedRoomName = '';
            this.pendingPreselectedRoomId = null;
            this.pendingPreselectedRoomName = '';
        },

        _emitManualSync() {
            const payload = this._manualSyncPayload();
            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('manual-booking-sync', payload);
            }
        },

        _emitManualCommit() {
            const payload = this._manualSyncPayload();
            if (typeof Livewire !== 'undefined') {
                if (this.selectedRooms.length > 0) {
                    Livewire.dispatch('manual-booking-commit-rooms', {
                        ...payload,
                        rooms: this.selectedRooms,
                    });
                } else {
                    Livewire.dispatch('manual-booking-commit-room', payload);
                }
            }
            this.datesConfirmed = true;
        },

        _manualSyncPayload() {
            const form = this.targetForm;
            let roomRateId = null;
            let roomTypeId = this.roomTypeId;
            if (form) {
                const ri = form.querySelector('[name="room_rate_id"]');
                const rti = form.querySelector('[name="room_type_id"]');
                if (ri) roomRateId = parseInt(ri.value, 10) || null;
                if (rti) roomTypeId = parseInt(rti.value, 10) || roomTypeId;
            }
            return {
                checkIn: this.checkIn,
                checkOut: this.checkOut,
                guests: this.totalGuests,
                adults: this.adults,
                roomTypeId: roomTypeId,
                roomRateId: roomRateId,
                extraGuests: this.extraGuests,
                billFullRooms: this.billFullRooms,
                childrenUnder6: this.childrenUnder6,
                roomId: this.selectedRoomId,
                roomName: this.selectedRoomName,
            };
        },

        // Convert Jalali year+month (with optional offset) to Gregorian YYYY-MM for API calls
        _gregYmForJalali(jYear, jMonth) {
            while (jMonth > 12) { jMonth -= 12; jYear++; }
            while (jMonth < 1)  { jMonth += 12; jYear--; }
            return window.bnbJalaliCal.toGregorianYm(jYear, jMonth);
        },

        async fetchAvailability(months) {
            if (!this.roomTypeId) return;
            const toFetch = months.filter(m => !this.loadedMonths.includes(m));
            if (!toFetch.length) return;

            this.availabilityLoading = true;
            this.availabilityError   = false;
            try {
                const params = new URLSearchParams({ months: toFetch.join(',') });
                if (this.roomRateId) {
                    params.set('room_rate_id', String(this.roomRateId));
                }
                const resp = await fetch('/api/room-types/' + this.roomTypeId + '/availability?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                Object.assign(this.availabilityData, data.dates || {});
                this.loadedMonths.push(...toFetch);
            } catch(e) {
                this.availabilityError = true;
            } finally {
                this.availabilityLoading = false;
            }
        },

        _ensureMonthLoaded() {
            if (!this.roomTypeId) return;
            const curr = this._gregYmForJalali(this.calYear, this.calMonth);
            const next = this._gregYmForJalali(this.calYear, this.calMonth + 1);
            this.fetchAvailability([curr, next]);
        },

        jalStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        jalStrLatin(g) {
            return this._normalizeDigits(this.jalStr(g));
        },

        calPrev() {
            if (this.calMonth === 1) { this.calYear--; this.calMonth = 12; }
            else this.calMonth--;
            this._ensureMonthLoaded();
        },

        calNext() {
            if (this.calMonth === 12) { this.calYear++; this.calMonth = 1; }
            else this.calMonth++;
            this._ensureMonthLoaded();
        },

        selectDay(cell) {
            if (this.mode === 'manual' && this.datesLocked) return;
            if (!cell || cell.past || cell.isUnavailable || cell.isBlocked || cell.disabledByGap) return;

            if (this.mode === 'manual') {
                if (this._isInvalidStayNight(cell.greg)) {
                    this._toastStayError('این تاریخ برای اقامت در دسترس نیست (مسدود یا تکمیل ظرفیت).');
                    return;
                }
                if (this.awaitingStayDuration && this.stayDurationMode === 'checkout' && cell.greg > this.checkIn) {
                    this._applyStayRange(this.checkIn, cell.greg);
                    return;
                }
                if (this.awaitingStayDuration && cell.greg === this.checkIn) {
                    this._focusStayDurationInput();
                    return;
                }
                this.checkIn = cell.greg;
                this.checkOut = '';
                this.calPhase = 1;
                this.stayNightsInput = '';
                this.stayCheckOutInput = '';
                this.stayDurationMode = 'nights';
                this.calHover = null;
                this.$nextTick(() => this._focusStayDurationInput());
                return;
            }

            if (!this.applyStaySelection(cell, (from, to) => !this._hasInvalidNightInRange(from, to))) return;
            this.calHover = null;
        },

        clearDatesSelection() {
            this.checkIn = '';
            this.checkOut = '';
            this.calPhase = 0;
            this.stayNightsInput = '';
            this.stayCheckOutInput = '';
            this.stayDurationMode = 'nights';
            this.calHover = null;
        },

        _focusStayDurationInput() {
            this.$nextTick(() => {
                const sel = this.stayDurationMode === 'checkout'
                    ? '[data-bnb-stay-checkout-input]'
                    : '[data-bnb-stay-nights-input]';
                const input = this.$el.querySelector(sel);
                if (!input) return;
                input.focus();
                if (this.stayDurationMode === 'checkout' && input.value) {
                    input.select();
                }
            });
        },

        _normalizeDigits(str) {
            const persian = '۰۱۲۳۴۵۶۷۸۹';
            return String(str).replace(/[۰-۹]/g, (d) => String(persian.indexOf(d)));
        },

        _parseJalaliCheckoutInput() {
            const raw = this._normalizeDigits(this.stayCheckOutInput).trim();
            if (!raw) return '';
            const parts = raw.replace(/-/g, '/').split('/').map((p) => parseInt(p.trim(), 10));
            if (parts.length !== 3 || parts.some((n) => !Number.isFinite(n) || n <= 0)) return '';
            try {
                return window.bnbJalaliCal.toGregorian(parts[0], parts[1], parts[2]);
            } catch (e) {
                return '';
            }
        },

        _toastStayError(message) {
            const msg = message || 'در بازه انتخابی یک یا چند شب در دسترس نیست. تاریخ شروع را دوباره انتخاب کنید.';
            if (typeof window.bnbToast === 'function') {
                window.bnbToast('error', msg);
            } else {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { type: 'error', message: msg }
                }));
            }
        },

        async _ensureRangeMonthsLoaded(checkIn, checkOut) {
            if (!this.roomTypeId || !checkIn || !checkOut) return;
            const months = new Set();
            let d = new Date(checkIn + 'T12:00:00');
            const end = new Date(checkOut + 'T12:00:00');
            while (d < end) {
                try {
                    const pDate = new persianDate(d);
                    months.add(this._gregYmForJalali(pDate.year(), pDate.month()));
                } catch (e) {}
                d.setDate(d.getDate() + 1);
            }
            if (months.size) {
                await this.fetchAvailability([...months]);
            }
        },

        async _applyStayRange(checkIn, checkOut) {
            if (!checkIn || !checkOut || checkOut <= checkIn) {
                this._toastStayError('تاریخ خروج باید بعد از تاریخ ورود باشد.');
                return false;
            }

            const nights = window.bnbStayPicker.nights(checkIn, checkOut);
            const maxValidation = window.bnbStayPicker.validateNightsInput(nights);
            if (!maxValidation.valid) {
                this._toastStayError(maxValidation.message);
                return false;
            }

            const lastNight = window.bnbStayPicker.lastStayNight(checkIn, checkOut);
            await this._ensureRangeMonthsLoaded(checkIn, checkOut);

            if (this._hasInvalidNightInRange(checkIn, lastNight)) {
                this._toastStayError('در این بازه یک یا چند شب مسدود یا پر است. با «پاک کردن» تاریخ شروع را دوباره انتخاب کنید.');
                this.clearDatesSelection();
                return false;
            }

            this.checkIn = checkIn;
            this.checkOut = checkOut;
            this.calPhase = 0;
            this.stayNightsInput = '';
            this.stayCheckOutInput = '';
            this.stayDurationMode = 'nights';
            this.calHover = null;
            return true;
        },

        async confirmStayDuration() {
            if (this.mode !== 'manual' || this.datesLocked) return;

            if (this.stayDurationMode === 'checkout') {
                const checkOut = this._parseJalaliCheckoutInput();
                if (!checkOut) {
                    this._toastStayError('تاریخ خروج را به شکل ۱۴۰۴/۰۴/۱۵ وارد کنید یا روز خروج را در تقویم بزنید.');
                    return;
                }
                await this._applyStayRange(this.checkIn, checkOut);
                return;
            }

            const validation = window.bnbStayPicker.validateNightsInput(this.stayNightsInput);
            if (!validation.valid) {
                this._toastStayError(validation.message);
                return;
            }

            const checkOut = window.bnbStayPicker.checkOutFromNights(
                this.checkIn,
                parseInt(String(this.stayNightsInput).trim(), 10),
            );
            await this._applyStayRange(this.checkIn, checkOut);
        },

        onBookClick() {
            if (this.checkIn && this.checkOut) {
                this._scrollToRooms();
            } else {
                this.drawerOpen = true;
            }
        },

        _calcNightTotal(g, childG = 0) {
            if (!this.checkIn || !this.checkOut || !g) return 0;
            let total = 0;
            let d = new Date(this.checkIn + 'T12:00:00');
            const end = new Date(this.checkOut + 'T12:00:00');
            const children = Math.min(Math.max(0, childG), g);
            while (d < end) {
                const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                const avail = this.availabilityData[key];
                const perPerson = (avail && avail.effective_price != null)
                    ? avail.effective_price
                    : (this.originalPrice || this.pricePerNight);
                total += this._nightAccommodationTotal(perPerson, g, children);
                d.setDate(d.getDate() + 1);
            }
            return total;
        },

        _swalEmptyBedsHtml(guests, rn, capacity, totalBeds, emptyBeds, fullRoomTotal, partialTotal) {
            const savingsHint = guests % capacity === 0 ? capacity : guests % capacity;
            return `<div style="font-family:var(--bnb-font);line-height:1.8;color:#374151;text-align:right;">
                <p style="margin:0 0 12px;">
                    برای <strong>${guests} نفر</strong> نیاز به <strong>${rn} اتاق</strong> (هر اتاق ${capacity} نفر) دارید.
                    <br>مجموع تخت‌های رزرو شده: <strong>${totalBeds} تخت</strong> — که <strong style="color:#dc2626;">${emptyBeds} تخت خالی</strong> دارد.
                </p>
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:12px 14px;font-size:13px;margin-bottom:12px;">
                    <div style="font-weight:700;color:#15803d;margin-bottom:6px;"><i class="bi bi-door-closed-fill"></i> رزرو کامل اتاق‌ها</div>
                    <p style="margin:0 0 8px;color:#374151;">با پذیرش این گزینه، هزینه برای <strong>${totalBeds} تخت</strong> (${rn} اتاق کامل) محاسبه می‌شود.</p>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:14px;">
                        <span style="color:#6b7280;">مبلغ نهایی:</span>
                        <div>
                            ${partialTotal < fullRoomTotal ? `<span style="font-size:12px;text-decoration:line-through;color:#9ca3af;margin-left:6px;">${partialTotal.toLocaleString('fa-IR')}</span>` : ''}
                            <strong style="color:#15803d;font-size:16px;">${fullRoomTotal.toLocaleString('fa-IR')} ریال</strong>
                        </div>
                    </div>
                </div>
                <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:12px 14px;font-size:13px;">
                    <div style="font-weight:700;color:#92400e;margin-bottom:6px;"><i class="bi bi-lightbulb-fill" style="color:#f59e0b;"></i> پیشنهاد برای صرفه‌جویی:</div>
                    <ul style="margin:0;padding-right:18px;color:#78350f;">
                        <li style="margin-bottom:4px;">اتاق با ظرفیت <strong>${savingsHint} نفر</strong> (برای آخرین گروه) انتخاب کنید.</li>
                        <li>یا برای هر گروه به‌صورت جداگانه رزرو انجام دهید.</li>
                    </ul>
                </div>
            </div>`;
        },

        _swalEmptyBedsManualHtml(guests, rn, capacity, totalBeds, emptyBeds, fullRoomTotal, partialTotal) {
            return `<div style="font-family:var(--bnb-font);line-height:1.8;color:#374151;text-align:right;">
                <p style="margin:0 0 14px;">
                    برای <strong>${guests} نفر</strong> نیاز به <strong>${rn} اتاق</strong> (هر اتاق ${capacity} نفر) دارید.
                    <br>در این ترکیب، <strong style="color:#dc2626;">${emptyBeds} تخت خالی</strong> باقی می‌ماند (${totalBeds} تخت رزرو شده).
                </p>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    <label style="display:flex;align-items:flex-start;gap:12px;background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:14px;cursor:pointer;" id="swal-opt-partial">
                        <input type="radio" name="swal-bed-choice" value="partial" checked style="margin-top:4px;accent-color:#16a34a;">
                        <div style="flex:1;">
                            <div style="font-weight:700;color:#15803d;margin-bottom:4px;"><i class="bi bi-person-check"></i> رزرو فقط برای ${guests} نفر (بدون هزینه تخت خالی)</div>
                            <div style="font-size:13px;color:#374151;">هزینه اقامت فقط برای مهمانان حاضر محاسبه می‌شود؛ ${emptyBeds} تخت خالی بدون هزینه است.</div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:14px;margin-top:8px;">
                                <span style="color:#6b7280;">مبلغ اقامت:</span>
                                <strong style="color:#15803d;font-size:16px;">${partialTotal.toLocaleString('fa-IR')} ریال</strong>
                            </div>
                        </div>
                    </label>
                    <label style="display:flex;align-items:flex-start;gap:12px;background:#fff7ed;border:2px solid #fdba74;border-radius:12px;padding:14px;cursor:pointer;" id="swal-opt-full">
                        <input type="radio" name="swal-bed-choice" value="full" style="margin-top:4px;accent-color:#ea580c;">
                        <div style="flex:1;">
                            <div style="font-weight:700;color:#c2410c;margin-bottom:4px;"><i class="bi bi-door-closed-fill"></i> رزرو کامل اتاق‌ها (هزینه همه تخت‌ها)</div>
                            <div style="font-size:13px;color:#374151;">هزینه برای <strong>${totalBeds} تخت</strong> (${rn} اتاق کامل) محاسبه می‌شود.</div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline;font-size:14px;margin-top:8px;">
                                <span style="color:#6b7280;">مبلغ اقامت:</span>
                                <div>
                                    ${partialTotal < fullRoomTotal ? `<span style="font-size:12px;text-decoration:line-through;color:#9ca3af;margin-left:6px;">${partialTotal.toLocaleString('fa-IR')}</span>` : ''}
                                    <strong style="color:#c2410c;font-size:16px;">${fullRoomTotal.toLocaleString('fa-IR')} ریال</strong>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
            </div>`;
        },

        _showEmptyBedsPopup(guests, rn, capacity, totalBeds, emptyBeds) {
            const fullRoomTotal = this._calcNightTotal(totalBeds, 0);
            const partialTotal  = this._calcNightTotal(guests, this.effectiveChildGuests);
            const isManual = this.mode === 'manual';

            return _loadSwal().then(() => Swal.fire({
                title: '<span style="font-family:var(--bnb-font);font-size:17px;">⚠️ تخت‌های خالی در رزرو شما</span>',
                html: isManual
                    ? this._swalEmptyBedsManualHtml(guests, rn, capacity, totalBeds, emptyBeds, fullRoomTotal, partialTotal)
                    : this._swalEmptyBedsHtml(guests, rn, capacity, totalBeds, emptyBeds, fullRoomTotal, partialTotal),
                icon: 'warning',
                showConfirmButton: true,
                confirmButtonText: isManual
                    ? '<i class="bi bi-check-circle me-1"></i> تأیید و ادامه'
                    : '<i class="bi bi-check-circle me-1"></i> رزرو کامل اتاق‌ها و پرداخت',
                confirmButtonColor: '#16a34a',
                showCancelButton: true,
                cancelButtonText: '<i class="bi bi-pencil-square me-1"></i> اصلاح فرم رزرو',
                cancelButtonColor: isManual ? '#6b7280' : '#ff385c',
                reverseButtons: true,
                customClass: { popup: 'swal-bnb-popup' },
                didOpen: () => {
                    const popup = Swal.getPopup();
                    if (popup) { popup.style.fontFamily = 'var(--bnb-font)'; popup.style.borderRadius = '18px'; popup.style.direction = 'rtl'; }
                    const container = document.querySelector('.swal2-container');
                    if (container) container.style.zIndex = '9999';
                }
            })).then((result) => {
                if (!result.isConfirmed) return;
                if (isManual) {
                    const choice = document.querySelector('input[name="swal-bed-choice"]:checked')?.value;
                    this._proceedBooking(0, choice === 'full');
                } else {
                    this._proceedBooking(0, true);
                }
            });
        },

        _proceedBooking(extraG, fullRoom) {
            this.extraGuests    = extraG || 0;
            this.billFullRooms  = !!fullRoom;
            if (this.mode !== 'manual') {
                this.datesConfirmed = true;
                this.drawerOpen = false;
            }
            window.dispatchEvent(new CustomEvent('bnb-dates-set', {
                detail: { checkIn: this.checkIn, checkOut: this.checkOut }
            }));
            if (this.mode === 'manual') {
                this._openPhysicalRoomPicker(extraG, fullRoom);
            }
        },

        async _openPhysicalRoomPicker(extraG, fullRoom) {
            this.pendingCommitExtraG = extraG || 0;
            this.pendingCommitFullRoom = !!fullRoom;

            if (!this.roomTypeId || !this.checkIn || !this.checkOut) {
                this._emitManualCommit();
                return;
            }

            try {
                const params = new URLSearchParams({
                    check_in: this.checkIn,
                    check_out: this.checkOut,
                });
                const resp = await fetch('/api/room-types/' + this.roomTypeId + '/physical-rooms?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (resp.ok) {
                    const data = await resp.json();
                    if (!data.rooms || data.rooms.length === 0) {
                        this.extraGuests = this.pendingCommitExtraG;
                        this.billFullRooms = this.pendingCommitFullRoom;
                        this._emitManualCommit();
                        return;
                    }
                }
            } catch (e) {}

            if (typeof Livewire !== 'undefined') {
                Livewire.dispatch('manual-booking-get-excluded-rooms');
            } else {
                this._showRoomPicker([]);
            }
        },

        _showRoomPicker(excludeRoomIds) {
            const preIds = this.pendingPreselectedRoomId ? [this.pendingPreselectedRoomId] : [];
            window.dispatchEvent(new CustomEvent('manual-booking-open-room-picker', {
                detail: {
                    roomTypeId: this.roomTypeId,
                    roomTypeName: this.roomTypeName,
                    checkIn: this.checkIn,
                    checkOut: this.checkOut,
                    excludeRoomIds: excludeRoomIds || [],
                    roomsToSelect: this.effectiveRoomsNeeded,
                    preselectedRoomIds: preIds,
                    explicitConfirm: preIds.length > 0,
                }
            }));
        },

        confirmDates() {
            if (!this.checkIn || !this.checkOut) return;

            const capacity     = this.roomTypeCapacityNum || 1;
            const guests       = this.totalGuests;
            const bedGuests    = this.guestsForBeds;
            const extraCap     = this.extraCapacity || 0;
            const extraPrice   = this.extraCapacityPrice || 0;
            const nights       = this.nights;
            // Standard rooms needed (ceil) without extra capacity — based on bed allocation policy
            const rn           = Math.ceil(bedGuests / capacity);
            const totalBeds    = rn * capacity;
            const emptyBeds    = totalBeds - bedGuests;
            // Remainder that would go on floor (guests that don't fill the last room)
            const remainder    = bedGuests % capacity;
            // Can extra capacity solve the mismatch? (remainder fits within extra_capacity)
            const canUseExtra  = this.roomTypeId && capacity > 1 && bedGuests > capacity && remainder > 0 && extraCap >= remainder;

            const proceed = (extraG, fullRoom) => this._proceedBooking(extraG, fullRoom);

            if (canUseExtra) {
                // Extra capacity CAN bridge the gap — offer the user the choice
                const extraCost = remainder * extraPrice * nights;
                const extraCostDisc = this.userDiscountPct > 0
                    ? Math.round(extraCost * (1 - this.userDiscountPct / 100))
                    : extraCost;
                const roomsWithExtra = Math.floor(bedGuests / capacity); // one fewer room needed
                const htmlContent = `
                    <div style="font-family:var(--bnb-font);line-height:1.8;color:#374151;text-align:right;">
                        <p style="margin:0 0 14px;">برای <strong>${guests} نفر</strong> با ظرفیت هر اتاق ${capacity} نفر:</p>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <label style="display:flex;align-items:flex-start;gap:12px;background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:14px;cursor:pointer;" id="swal-opt-extra">
                                <input type="radio" name="swal-cap-choice" value="extra" checked style="margin-top:4px;accent-color:#16a34a;">
                                <div>
                                    <div style="font-weight:700;color:#15803d;margin-bottom:4px;"><i class="bi bi-person-add"></i> کف‌خوابی (پیشنهادی)</div>
                                    <div style="font-size:13px;color:#374151;"><strong>${roomsWithExtra > 0 ? roomsWithExtra : 1} اتاق</strong> + <strong>${remainder} نفر کف‌خواب</strong></div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">هزینه کف‌خوابی: ${this.userDiscountPct > 0 ? `<s style="opacity:.65;">${extraCost.toLocaleString('fa-IR')}</s> ` : ''}<strong style="color:#15803d;">${extraCostDisc.toLocaleString('fa-IR')} ریال</strong>${this.userDiscountPct > 0 ? ` <span style="font-size:10px;background:#fef9c3;color:#854d0e;border-radius:4px;padding:1px 5px;font-weight:700;">${this.userDiscountPct}٪ تخفیف</span>` : ''} (${remainder} نفر × ${extraPrice.toLocaleString('fa-IR')} × ${nights} شب)</div>
                                </div>
                            </label>
                            <label style="display:flex;align-items:flex-start;gap:12px;background:#fff7ed;border:2px solid #fdba74;border-radius:12px;padding:14px;cursor:pointer;" id="swal-opt-multi">
                                <input type="radio" name="swal-cap-choice" value="multi" style="margin-top:4px;accent-color:#ea580c;">
                                <div>
                                    <div style="font-weight:700;color:#c2410c;margin-bottom:4px;"><i class="bi bi-layers"></i> رزرو چندتایی (بدون کف‌خوابی)</div>
                                    <div style="font-size:13px;color:#374151;"><strong>${rn} اتاق</strong> — ${emptyBeds} تخت خالی محاسبه می‌شود</div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">هر رزرو را به‌صورت جداگانه ثبت کنید تا از تخت خالی جلوگیری شود.</div>
                                </div>
                            </label>
                        </div>
                    </div>`;
                _loadSwal().then(() => Swal.fire({
                    title: '<span style="font-family:var(--bnb-font);font-size:17px;"><i class="bi bi-people-fill text-success"></i> نحوه رزرو برای ' + guests + ' نفر</span>',
                    html: htmlContent,
                    showConfirmButton: true,
                    confirmButtonText: '<i class="bi bi-check-circle me-1"></i> تأیید و ادامه',
                    confirmButtonColor: '#16a34a',
                    showCancelButton: true,
                    cancelButtonText: '<i class="bi bi-x me-1"></i> انصراف',
                    cancelButtonColor: '#6b7280',
                    customClass: { popup: 'swal-bnb-popup' },
                    didOpen: () => {
                        const popup = Swal.getPopup();
                        if (popup) { popup.style.fontFamily = 'var(--bnb-font)'; popup.style.borderRadius = '18px'; popup.style.direction = 'rtl'; }
                        const container = document.querySelector('.swal2-container');
                        if (container) container.style.zIndex = '9999';
                    }
                })).then((result) => {
                    if (result.isConfirmed) {
                        const choice = document.querySelector('input[name="swal-cap-choice"]:checked')?.value;
                        if (choice === 'extra') {
                            proceed(remainder, false);
                        } else {
                            this._showEmptyBedsPopup(guests, rn, capacity, totalBeds, emptyBeds);
                        }
                    }
                });
            } else if (this.roomTypeId && capacity > 1 && emptyBeds > 0) {
                this._showEmptyBedsPopup(guests, rn, capacity, totalBeds, emptyBeds);
            } else {
                proceed(0, false);
            }
        },

        pay() {
            if (this.mode === 'manual') return;
            if (!this.checkIn || !this.checkOut || !this.targetForm) return;
            const form = this.targetForm;
            const ci  = form.querySelector('.rt-check-in');
            const co  = form.querySelector('.rt-check-out');
            const g   = form.querySelector('.rt-guests') || form.querySelector('input[name="guests"]');
            const eg  = form.querySelector('.rt-extra-guests') || form.querySelector('input[name="extra_guests"]');
            const bfr = form.querySelector('.rt-bill-full-rooms') || form.querySelector('input[name="bill_full_rooms"]');
            const cu6 = form.querySelector('.rt-children-under-6') || form.querySelector('input[name="children_under_6"]');
            if (ci) ci.value = this.checkIn;
            if (co) co.value = this.checkOut;
            if (g)  g.value  = this.totalGuests;
            if (cu6) cu6.value = this.childrenUnder6 || 0;
            if (eg) eg.value = this.extraGuests || 0;
            if (bfr) bfr.value = this.billFullRooms ? '1' : '0';
            form.submit();
        },

        openForRoom(form, price, origPrice, roomTypeId, rtName, rtCap, extraCap, extraPrice) {
            this.targetForm          = form;
            this.pricePerNight       = price;
            this.originalPrice       = origPrice || price;
            this.roomTypeName        = rtName || '';
            this.roomTypeCapacity    = String(rtCap) || '';
            this.roomTypeCapacityNum = parseInt(rtCap) || 1;
            this.extraCapacity       = parseInt(extraCap)   || 0;
            this.extraCapacityPrice  = parseInt(extraPrice) || 0;
            this.extraGuests         = 0;
            this.adults              = 1;
            this.childrenUnder6      = 0;
            this.billFullRooms       = false;
            this.datesConfirmed      = this.datesLocked;

            const rateInput = form ? form.querySelector('[name="room_rate_id"]') : null;
            const newRateId = rateInput ? (parseInt(rateInput.value, 10) || null) : null;

            // Always clear cached availability when room type or tariff changes
            const roomChanged     = this.roomTypeId !== (roomTypeId || null);
            const rateChanged     = this.roomRateId !== newRateId;
            this.roomTypeId       = roomTypeId || null;
            this.roomRateId       = newRateId;
            if (roomChanged || rateChanged) {
                this.availabilityData = {};
                this.loadedMonths     = [];
            }

            // Reset calendar to current month when switching rooms so user picks fresh dates
            if (roomChanged && typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
                this.calPhase = 0;
            }

            this.drawerOpen = true;
            if (this.mode === 'manual') {
                if (typeof persianDate !== 'undefined') {
                    const t = new persianDate();
                    this.calYear  = t.year();
                    this.calMonth = t.month();
                }
                this._scrollToTodayInCalendar();
            }

            // Sync guest count to rooms section
            window.dispatchEvent(new CustomEvent('bnb-guests-changed', { detail: { guests: this.guestsForBeds, totalGuests: this.totalGuests } }));

            if (roomTypeId) {
                // Load current + next 2 Gregorian months (converted from Jalali calendar state)
                const months = [0, 1, 2].map(i => this._gregYmForJalali(this.calYear, this.calMonth + i));
                this.fetchAvailability(months);
            }
        },

        _scrollToRooms() {
            const el = document.getElementById('sec-rooms');
            if (!el) return;
            const nav = document.getElementById('bnbNavbar');
            const navH = (nav && nav.classList.contains('is-mini')) ? 64 : 80;
            const top = el.getBoundingClientRect().top + window.pageYOffset - navH - 50;
            window.scrollTo({ top, behavior: 'smooth' });
        }
    };
}

// Lazy-load SweetAlert2 on demand
var _swalLoaded = false;
function _loadSwal() {
    if (_swalLoaded || window.Swal) { _swalLoaded = true; return Promise.resolve(); }
    return new Promise(function(resolve, reject) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '{{ vasset("vendor/sweetalert2/sweetalert2.min.css") }}';
        document.head.appendChild(link);
        var script = document.createElement('script');
        script.src = '{{ vasset("vendor/sweetalert2/sweetalert2.min.js") }}';
        script.onload = function() { _swalLoaded = true; resolve(); };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}
</script>
