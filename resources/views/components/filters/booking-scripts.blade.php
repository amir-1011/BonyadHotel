@push('styles')
<link rel="stylesheet" href="{{ vasset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var bookingDatepickersReady = false;

    function bookingWire() {
        var root = document.getElementById('booking-filter-form');
        if (!root) return null;
        var host = root.closest('[wire\\:id]');
        if (!host) return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function syncBookingDateToWire(input) {
        var wire = bookingWire();
        var prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            wire.set(prop, input.value || '');
        }
    }

    function syncAllBookingDates() {
        document.querySelectorAll('.jalali-picker-booking').forEach(syncBookingDateToWire);
    }

    function destroyBookingDatepickers() {
        $('.jalali-picker-booking').each(function () {
            if ($(this).data('pDatepicker')) {
                try { $(this).pDatepicker('destroy'); } catch (e) { /* ignore */ }
                $(this).removeData('pDatepicker');
            }
        });
        bookingDatepickersReady = false;
    }

    function initBookingDatepickers() {
        if (bookingDatepickersReady) return;

        $('.jalali-picker-booking').each(function () {
            var $input = $(this);
            if ($input.data('pDatepicker')) return;

            $input.pDatepicker({
                format: 'YYYY/MM/DD',
                viewMode: 'day',
                autoClose: true,
                initialValue: false,
                initialValueType: 'persian',
                persianDigit: false,
                toolbox: {
                    enabled: true,
                    todayButton: { enabled: true },
                    submitButton: { enabled: false },
                },
                onSelect: function () {
                    var el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                    syncBookingDateToWire(el);
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
        });

        bookingDatepickersReady = true;
    }

    window.syncBookingFilterDates = syncAllBookingDates;

    $(function () {
        initBookingDatepickers();

        $('#booking-filter-form').on('submit', function () {
            syncAllBookingDates();
        });

        $(document).on('blur', '.jalali-picker-booking', function () {
            syncBookingDateToWire(this);
        });

        $(document).on('click', '.booking-clear-date', function () {
            var targetId = $(this).data('target');
            var input = document.getElementById(targetId);
            if (!input) return;
            input.value = '';
            syncBookingDateToWire(input);
            if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(input);
        });
    });

    document.addEventListener('livewire:navigated', function () {
        if (!document.querySelector('.jalali-picker-booking')) return;
        destroyBookingDatepickers();
        initBookingDatepickers();
    });

    document.addEventListener('livewire:init', function () {
        Livewire.on('booking-dates-sync', function (data) {
            var dates = (data && data.dates) ? data.dates : {};
            var map = {
                'booking-draft-check-in-from': dates.check_in_from || '',
                'booking-draft-check-in-to': dates.check_in_to || '',
                'booking-draft-check-out-from': dates.check_out_from || '',
                'booking-draft-check-out-to': dates.check_out_to || '',
            };
            Object.keys(map).forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.value = map[id];
            });
        });
    });
})();
</script>
@endpush
