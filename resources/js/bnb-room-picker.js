/**
 * Alpine component for physical room picker modal (manual booking + program booking).
 */
export function bnbRoomPicker() {
    return {
        open: false,
        loading: false,
        error: '',
        rooms: [],
        roomTypeName: '',
        roomTypeId: null,
        accommodationId: null,
        checkIn: '',
        checkOut: '',
        excludeRoomIds: [],
        roomsToSelect: 1,
        selectedRooms: [],
        preselectedRoomIds: [],
        explicitConfirm: false,

        get selectionLabel() {
            return this.selectedRooms.length + ' از ' + this.roomsToSelect + ' اتاق انتخاب شده';
        },

        get canConfirm() {
            return this.selectedRooms.length === this.roomsToSelect;
        },

        get confirmLabel() {
            if (this.roomsToSelect <= 1) {
                return 'تأیید انتخاب';
            }
            return 'تأیید ' + this.roomsToSelect + ' اتاق';
        },

        get needLabel() {
            return this.roomsToSelect > 1 ? ' · نیاز به ' + this.roomsToSelect + ' اتاق' : '';
        },

        jalaliStr(g) {
            if (!g) return '';
            try {
                return new persianDate(new Date(g + 'T12:00:00')).format('YYYY/MM/DD');
            } catch (e) {
                return g;
            }
        },

        openPicker(detail) {
            this.roomTypeId = detail.roomTypeId || null;
            this.accommodationId = detail.accommodationId || null;
            this.checkIn = detail.checkIn;
            this.checkOut = detail.checkOut;
            this.excludeRoomIds = detail.excludeRoomIds || [];
            this.roomsToSelect = Math.max(1, parseInt(detail.roomsToSelect, 10) || 1);
            this.selectedRooms = [];
            this.preselectedRoomIds = (detail.preselectedRoomIds || []).map(Number).filter(Boolean);
            this.explicitConfirm = !!detail.explicitConfirm || this.preselectedRoomIds.length > 0;
            this.roomTypeName = detail.roomTypeName || '';
            this.open = true;
            this.fetchRooms();
        },

        close() {
            this.open = false;
            this.rooms = [];
            this.error = '';
            this.selectedRooms = [];
            this.roomsToSelect = 1;
            this.roomTypeName = '';
            this.roomTypeId = null;
            this.accommodationId = null;
            this.preselectedRoomIds = [];
            this.explicitConfirm = false;
        },

        isSelected(roomId) {
            return this.selectedRooms.some(r => r.id === roomId);
        },

        roomPayload(room) {
            return {
                id: room.id,
                name: room.name,
                room_type_id: room.room_type_id || null,
                room_type_name: room.room_type_name || '',
            };
        },

        async fetchRooms() {
            const hasType = !!this.roomTypeId;
            const hasAccommodation = !!this.accommodationId;
            if ((!hasType && !hasAccommodation) || !this.checkIn || !this.checkOut) return;

            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams({
                    check_in: this.checkIn,
                    check_out: this.checkOut,
                    exclude_room_ids: this.excludeRoomIds.join(','),
                });
                const url = hasAccommodation
                    ? '/api/accommodations/' + this.accommodationId + '/physical-rooms?' + params
                    : '/api/room-types/' + this.roomTypeId + '/physical-rooms?' + params;

                const resp = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!resp.ok) throw new Error('خطا در بارگذاری');
                const data = await resp.json();
                this.rooms = data.rooms || [];
                this._applyPreselectedRooms();
            } catch (e) {
                this.error = 'خطا در بارگذاری لیست اتاق‌ها. دوباره تلاش کنید.';
            } finally {
                this.loading = false;
            }
        },

        _applyPreselectedRooms() {
            if (!this.preselectedRoomIds.length) return;
            for (const roomId of this.preselectedRoomIds) {
                const room = this.rooms.find(r => r.id === roomId);
                if (!room) continue;
                if (!room.selectable && !this.isSelected(room.id)) continue;
                if (!this.isSelected(room.id)) {
                    this.selectedRooms.push(this.roomPayload(room));
                }
            }
        },

        toggleRoom(room) {
            if (this.isSelected(room.id)) {
                this.selectedRooms = this.selectedRooms.filter(r => r.id !== room.id);
                return;
            }
            if (!room.selectable) return;

            if (this.roomsToSelect <= 1 && !this.explicitConfirm) {
                this.selectedRooms = [this.roomPayload(room)];
                this.confirmSelection();
                return;
            }

            if (this.selectedRooms.length >= this.roomsToSelect) return;
            this.selectedRooms.push(this.roomPayload(room));
        },

        confirmSelection() {
            if (!this.canConfirm) return;
            window.dispatchEvent(new CustomEvent('manual-booking-rooms-selected', {
                detail: {
                    rooms: this.selectedRooms.map(r => ({
                        roomId: r.id,
                        roomName: r.name,
                        roomTypeId: r.room_type_id,
                        roomTypeName: r.room_type_name,
                    })),
                },
            }));
            this.close();
        },
    };
}

export function registerBnbRoomPicker() {
    window.bnbRoomPicker = bnbRoomPicker;
}

if (!window.__bonyadRoomPickerReady) {
    window.__bonyadRoomPickerReady = true;
    registerBnbRoomPicker();
}
