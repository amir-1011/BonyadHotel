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
                const resp = await fetch('/api/accommodations/{{ $accommodation->id }}/rooms-availability?' + params, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (resp.ok) this.availData = await resp.json();
            } catch(e) {}
            this.loading = false;
        }
    };
}
</script>
<script>
function loadTour360(placeholder) {
    var wrapper = placeholder.parentElement;
    var iframe = document.createElement('iframe');
    iframe.src = 'https://360nama.com/virtualtour/realestate-tour/pasdaran-2/';
    var scale = 1.35;
    var pct = (100 / scale).toFixed(4) + '%';
    var hpx = Math.round(500 / scale) + 'px';
    iframe.style.cssText = 'display:block;width:' + pct + ';height:' + hpx + ';border:none;transform:scale(' + scale + ');transform-origin:top center;margin-left:auto;margin-right:auto;';
    iframe.allowFullscreen = true;
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('allow', 'fullscreen; gyroscope; accelerometer; xr-spatial-tracking');
    iframe.title = 'نمای ۳۶۰ درجه اقامتگاه';
    wrapper.style.height = '500px';
    wrapper.style.overflow = 'hidden';
    placeholder.replaceWith(iframe);
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

    var drawerEl = document.querySelector('[x-data="mbbDrawer()"]');
    if (drawerEl && typeof Alpine !== 'undefined') {
        var drawer = Alpine.$data(drawerEl);
        drawer.openForRoom(form, price, orig, rtId, rtName, rtCap, extraCap, extraPrice);
    }
}

// Price breakdown is handled reactively by Alpine.js in the booking widget.

function bnbBookWidget(initCheckIn, initCheckOut, initGuests) {
    return {
        ...bnbCalMixin(),
        checkIn:  initCheckIn  || '',
        checkOut: initCheckOut || '',
        guests:   parseInt(initGuests) || 1,
        dateLabel: '',
        // Calendar state
        calOpen:  false,
        calYear:  null,
        calMonth: null,
        calPhase: 0,
        calHover: null,

        get calNights() {
            return window.bnbStayPicker.nights(this.checkIn, this.checkOut);
        },

        get calMonthLabel() {
            if (!this.calYear) return '';
            const n = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            return n[this.calMonth] + ' ' + this.calYear;
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
                cells.push({ d, greg, past });
            }
            return cells;
        },

        init() {
            if (typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.updateLabel();
            // Sync widget changes → Navbar
            this.$watch('checkIn',  () => this._syncToNav());
            this.$watch('checkOut', () => this._syncToNav());
            this.$watch('guests',   () => this._syncToNav());
        },

        _syncToNav() {
            const navEl = document.querySelector('[x-data="bnbNavSearch()"]');
            if (!navEl || typeof Alpine === 'undefined') return;
            const nav = Alpine.$data(navEl);
            if (nav.checkIn  !== this.checkIn)  nav.checkIn  = this.checkIn;
            if (nav.checkOut !== this.checkOut) nav.checkOut = this.checkOut;
            if (nav.guests   !== this.guests)   nav.guests   = this.guests;
        },

        jalaliStr(g) {
            if (!g) return '';
            try { return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD'); } catch(e) { return g; }
        },

        updateLabel() {
            if (this.checkIn && this.checkOut) {
                this.dateLabel = this.jalaliStr(this.checkIn) + ' — ' + this.jalaliStr(this.checkOut);
            } else if (this.checkIn) {
                this.dateLabel = 'ورود: ' + this.jalaliStr(this.checkIn);
            } else {
                this.dateLabel = '';
            }
        },

        openCal() {
            this.calOpen = !this.calOpen;
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
            if (!this.applyStaySelection(cell)) return;
            this.calHover = null;
            this.updateLabel();
            if (this.calPhase === 0) this.calOpen = false;
        },

        syncFromNav(detail) {
            const ci = detail.checkIn  || '';
            const co = detail.checkOut || '';
            const g  = detail.guests   || 1;
            // Guard: only update if actually changed (prevents infinite loop with _syncToNav)
            if (ci === this.checkIn && co === this.checkOut && g === this.guests) return;
            this.checkIn  = ci;
            this.checkOut = co;
            this.guests   = g;
            this.updateLabel();
        },

        submitBooking() {
            if (!this.checkIn || !this.checkOut) {
                this.calOpen = true;
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('bookings.store', $accommodation) }}';
            const addInput = (name, val) => { const i = document.createElement('input'); i.type='hidden'; i.name=name; i.value=val; form.appendChild(i); };
            addInput('_token', '{{ csrf_token() }}');
            addInput('check_in',  this.checkIn);
            addInput('check_out', this.checkOut);
            addInput('guests',    this.guests);
            document.body.appendChild(form);
            form.submit();
        }
    };
}

function mbbDrawer() {
    return {
        ...bnbCalMixin(),
        drawerOpen: false,
        datesConfirmed: false,
        checkIn: '',
        checkOut: '',
        guests: 1,
        calYear: null,
        calMonth: null,
        calPhase: 0,
        calHover: null,
        targetForm: null,
        pricePerNight: 0,
        originalPrice: 0,
        // User's veteran/special-group discount injected from PHP
        userDiscountPct: {{ auth()->check() ? (int) auth()->user()->discount_percentage : 0 }},
        // Room capacity (guests per room) — used to compute rooms_needed for warning
        roomTypeCapacityNum: 1,
        // Extra capacity (floor sleeping / کف‌خوابی)
        extraCapacity: 0,         // max extra guests allowed for this room type
        extraCapacityPrice: 0,    // price per extra guest per night (undiscounted)
        extraGuests: 0,           // chosen extra guests for this booking
        billFullRooms: false,     // charge for all beds in reserved rooms
        // Availability
        roomTypeId: null,
        roomTypeName: '',
        roomTypeCapacity: '',
        availabilityData: {},
        availabilityLoading: false,
        availabilityError: false,
        loadedMonths: [],

        get nights() {
            return window.bnbStayPicker.nights(this.checkIn, this.checkOut);
        },

        get roomsNeeded() {
            return this.effectiveRoomsNeeded;
        },

        get effectiveRoomsNeeded() {
            const cap = Math.max(1, this.roomTypeCapacityNum);
            if (this.extraGuests > 0) {
                return Math.max(1, Math.ceil((this.guests - this.extraGuests) / cap));
            }
            return Math.ceil(this.guests / cap);
        },

        get billableGuests() {
            if (this.billFullRooms) {
                return this.effectiveRoomsNeeded * Math.max(1, this.roomTypeCapacityNum);
            }
            return Math.max(1, this.guests - this.extraGuests);
        },

        get dynamicNightPrices() {
            if (!this.checkIn || !this.checkOut) return [];
            const prices = [];
            const g = this.billableGuests;
            let d = new Date(this.checkIn + 'T12:00:00');
            const end = new Date(this.checkOut + 'T12:00:00');
            while (d < end) {
                const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                const avail = this.availabilityData[key];
                const baseRatePerPerson      = this.originalPrice || this.pricePerNight;
                const hostEffectivePerPerson = (avail && avail.effective_price != null) ? avail.effective_price : baseRatePerPerson;
                const hostDiscountPct        = (avail && avail.discount_percentage) ? avail.discount_percentage : 0;
                const baseRate      = baseRatePerPerson * g;
                const hostEffective = hostEffectivePerPerson * g;
                const finalPrice    = this.userDiscountPct > 0
                    ? Math.round(hostEffective * (1 - this.userDiscountPct / 100))
                    : hostEffective;
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
            if (!prices.length) {
                const base = this.userDiscountPct > 0
                    ? Math.round((this.originalPrice || this.pricePerNight) * (1 - this.userDiscountPct / 100))
                    : (this.originalPrice || this.pricePerNight);
                return this.nights * base * g + this.extraGuestsTotal;
            }
            return prices.reduce((s, p) => s + p.price, 0) + this.extraGuestsTotal;
        },

        get dynamicAfterHostTotal() {
            const prices = this.dynamicNightPrices;
            const g = this.billableGuests;
            if (!prices.length) return this.nights * (this.originalPrice || this.pricePerNight) * g + this.extraGuestsOriginalTotal;
            return prices.reduce((s, p) => s + p.hostEffective, 0) + this.extraGuestsOriginalTotal;
        },

        get dynamicOriginalTotal() {
            const prices = this.dynamicNightPrices;
            const g = this.billableGuests;
            if (!prices.length) return this.nights * (this.originalPrice || this.pricePerNight) * g + this.extraGuestsOriginalTotal;
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

                const disabledByGap = this.calPhase === 1 && this.checkIn && (
                    greg < this.checkIn || this._hasInvalidNightInRange(this.checkIn, greg)
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
            if (typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
            }
            this.$watch('drawerOpen', val => {
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
            this.$watch('guests', val => {
                window.dispatchEvent(new CustomEvent('bnb-guests-changed', { detail: { guests: val } }));
            });
            this.$watch('checkIn',  () => { if (this.drawerOpen) this.datesConfirmed = false; });
            this.$watch('checkOut', () => { if (this.drawerOpen) this.datesConfirmed = false; });
            // Pre-fill from booking widget if available
            const widgetEl = document.querySelector('[x-data^="bnbBookWidget"]');
            if (widgetEl && typeof Alpine !== 'undefined') {
                this.$nextTick(() => {
                    try {
                        const w = Alpine.$data(widgetEl);
                        if (w.checkIn)  this.checkIn  = w.checkIn;
                        if (w.checkOut) this.checkOut = w.checkOut;
                        if (w.guests)   this.guests   = w.guests;
                    } catch(e) {}
                });
            }
            window.addEventListener('nav-search-updated', (e) => {
                if (e.detail.checkIn)  this.checkIn  = e.detail.checkIn;
                if (e.detail.checkOut) this.checkOut = e.detail.checkOut;
                if (e.detail.guests)   this.guests   = e.detail.guests;
            });
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
            if (!cell || cell.past || cell.isUnavailable || cell.isBlocked || cell.disabledByGap) return;
            if (!this.applyStaySelection(cell, (from, to) => !this._hasInvalidNightInRange(from, to))) return;
            this.calHover = null;
        },

        onBookClick() {
            if (this.checkIn && this.checkOut) {
                this._scrollToRooms();
            } else {
                this.drawerOpen = true;
            }
        },

        _calcNightTotal(g) {
            if (!this.checkIn || !this.checkOut || !g) return 0;
            let total = 0;
            let d = new Date(this.checkIn + 'T12:00:00');
            const end = new Date(this.checkOut + 'T12:00:00');
            while (d < end) {
                const key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
                const avail = this.availabilityData[key];
                const perPerson = (avail && avail.effective_price != null)
                    ? avail.effective_price
                    : (this.originalPrice || this.pricePerNight);
                const hostEffective = perPerson * g;
                total += this.userDiscountPct > 0
                    ? Math.round(hostEffective * (1 - this.userDiscountPct / 100))
                    : hostEffective;
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
                            <strong style="color:#15803d;font-size:16px;">${fullRoomTotal.toLocaleString('fa-IR')} تومان</strong>
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

        _showEmptyBedsPopup(guests, rn, capacity, totalBeds, emptyBeds) {
            const fullRoomTotal = this._calcNightTotal(totalBeds);
            const partialTotal  = this._calcNightTotal(guests);
            return _loadSwal().then(() => Swal.fire({
                title: '<span style="font-family:var(--bnb-font);font-size:17px;">⚠️ تخت‌های خالی در رزرو شما</span>',
                html: this._swalEmptyBedsHtml(guests, rn, capacity, totalBeds, emptyBeds, fullRoomTotal, partialTotal),
                icon: 'warning',
                showConfirmButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> رزرو کامل اتاق‌ها و پرداخت',
                confirmButtonColor: '#16a34a',
                showCancelButton: true,
                cancelButtonText: '<i class="bi bi-pencil-square me-1"></i> اصلاح فرم رزرو',
                cancelButtonColor: '#ff385c',
                reverseButtons: true,
                customClass: { popup: 'swal-bnb-popup' },
                didOpen: () => {
                    const popup = Swal.getPopup();
                    if (popup) { popup.style.fontFamily = 'var(--bnb-font)'; popup.style.borderRadius = '18px'; popup.style.direction = 'rtl'; }
                    const container = document.querySelector('.swal2-container');
                    if (container) container.style.zIndex = '9999';
                }
            })).then((result) => {
                if (result.isConfirmed) {
                    this._proceedBooking(0, true);
                }
            });
        },

        _proceedBooking(extraG, fullRoom) {
            this.extraGuests    = extraG || 0;
            this.billFullRooms  = !!fullRoom;
            this.datesConfirmed = true;
            this.drawerOpen     = false;
            window.dispatchEvent(new CustomEvent('bnb-dates-set', {
                detail: { checkIn: this.checkIn, checkOut: this.checkOut }
            }));
        },

        confirmDates() {
            if (!this.checkIn || !this.checkOut) return;

            const capacity     = this.roomTypeCapacityNum || 1;
            const guests       = this.guests;
            const extraCap     = this.extraCapacity || 0;
            const extraPrice   = this.extraCapacityPrice || 0;
            const nights       = this.nights;
            // Standard rooms needed (ceil) without extra capacity
            const rn           = Math.ceil(guests / capacity);
            const totalBeds    = rn * capacity;
            const emptyBeds    = totalBeds - guests;
            // Remainder that would go on floor (guests that don't fill the last room)
            const remainder    = guests % capacity;
            // Can extra capacity solve the mismatch? (remainder fits within extra_capacity)
            const canUseExtra  = this.roomTypeId && capacity > 1 && guests > capacity && remainder > 0 && extraCap >= remainder;

            const proceed = (extraG, fullRoom) => this._proceedBooking(extraG, fullRoom);

            if (canUseExtra) {
                // Extra capacity CAN bridge the gap — offer the user the choice
                const extraCost = remainder * extraPrice * nights;
                const extraCostDisc = this.userDiscountPct > 0
                    ? Math.round(extraCost * (1 - this.userDiscountPct / 100))
                    : extraCost;
                const roomsWithExtra = Math.floor(guests / capacity); // one fewer room needed
                const htmlContent = `
                    <div style="font-family:var(--bnb-font);line-height:1.8;color:#374151;text-align:right;">
                        <p style="margin:0 0 14px;">برای <strong>${guests} نفر</strong> با ظرفیت هر اتاق ${capacity} نفر:</p>
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            <label style="display:flex;align-items:flex-start;gap:12px;background:#f0fdf4;border:2px solid #86efac;border-radius:12px;padding:14px;cursor:pointer;" id="swal-opt-extra">
                                <input type="radio" name="swal-cap-choice" value="extra" checked style="margin-top:4px;accent-color:#16a34a;">
                                <div>
                                    <div style="font-weight:700;color:#15803d;margin-bottom:4px;"><i class="bi bi-person-add"></i> کف‌خوابی (پیشنهادی)</div>
                                    <div style="font-size:13px;color:#374151;"><strong>${roomsWithExtra > 0 ? roomsWithExtra : 1} اتاق</strong> + <strong>${remainder} نفر کف‌خواب</strong></div>
                                    <div style="font-size:12px;color:#6b7280;margin-top:2px;">هزینه کف‌خوابی: ${this.userDiscountPct > 0 ? `<s style="opacity:.65;">${extraCost.toLocaleString('fa-IR')}</s> ` : ''}<strong style="color:#15803d;">${extraCostDisc.toLocaleString('fa-IR')} تومان</strong>${this.userDiscountPct > 0 ? ` <span style="font-size:10px;background:#fef9c3;color:#854d0e;border-radius:4px;padding:1px 5px;font-weight:700;">${this.userDiscountPct}٪ تخفیف</span>` : ''} (${remainder} نفر × ${extraPrice.toLocaleString('fa-IR')} × ${nights} شب)</div>
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
            if (!this.checkIn || !this.checkOut || !this.targetForm) return;
            const form = this.targetForm;
            const ci  = form.querySelector('.rt-check-in');
            const co  = form.querySelector('.rt-check-out');
            const g   = form.querySelector('.rt-guests') || form.querySelector('input[name="guests"]');
            const eg  = form.querySelector('.rt-extra-guests') || form.querySelector('input[name="extra_guests"]');
            const bfr = form.querySelector('.rt-bill-full-rooms') || form.querySelector('input[name="bill_full_rooms"]');
            if (ci) ci.value = this.checkIn;
            if (co) co.value = this.checkOut;
            if (g)  g.value  = this.guests;
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
            this.extraGuests         = 0;  // reset when switching rooms
            this.billFullRooms       = false;
            this.datesConfirmed      = false;

            // Always clear cached availability so new room type gets fresh data
            const roomChanged     = this.roomTypeId !== (roomTypeId || null);
            this.roomTypeId       = roomTypeId || null;
            this.availabilityData = {};
            this.loadedMonths     = [];

            // Reset calendar to current month when switching rooms so user picks fresh dates
            if (roomChanged && typeof persianDate !== 'undefined') {
                const t = new persianDate();
                this.calYear  = t.year();
                this.calMonth = t.month();
                this.calPhase = 0;
            }

            this.drawerOpen = true;

            // Sync guest count to rooms section
            window.dispatchEvent(new CustomEvent('bnb-guests-changed', { detail: { guests: this.guests } }));

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
