@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
</style>
@endpush

<div class="col-md-6">
    <label class="form-label">تاریخ شروع <span class="text-danger">*</span></label>
    <div wire:ignore>
        <input type="text"
               id="program-start-date"
               class="form-control jalali-picker-program @error('startDate') is-invalid @enderror"
               data-wire-prop="startDate"
               value="{{ $startDate }}"
               autocomplete="off"
               placeholder="۱۴۰۴/۰۱/۰۱"
               required>
    </div>
    @error('startDate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
<div class="col-md-6">
    <label class="form-label">تاریخ پایان <span class="text-danger">*</span></label>
    <div wire:ignore>
        <input type="text"
               id="program-end-date"
               class="form-control jalali-picker-program @error('endDate') is-invalid @enderror"
               data-wire-prop="endDate"
               value="{{ $endDate }}"
               autocomplete="off"
               placeholder="۱۴۰۴/۰۱/۱۵"
               required>
    </div>
    @error('endDate')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
(function () {
    var programDatepickersReady = false;

    function programWire() {
        var root = document.getElementById('program-form-root');
        if (!root) return null;
        var host = root.closest('[wire\\:id]');
        if (!host) return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function syncProgramDateToWire(input) {
        var wire = programWire();
        var prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            wire.set(prop, input.value || '');
        }
    }

    function syncAllProgramDates() {
        document.querySelectorAll('.jalali-picker-program').forEach(syncProgramDateToWire);
    }

    function destroyProgramDatepickers() {
        $('.jalali-picker-program').each(function () {
            if ($(this).data('pDatepicker')) {
                try { $(this).pDatepicker('destroy'); } catch (e) { /* ignore */ }
                $(this).removeData('pDatepicker');
            }
        });
        programDatepickersReady = false;
    }

    function initProgramDatepickers() {
        if (programDatepickersReady) return;

        $('.jalali-picker-program').each(function () {
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
                    syncProgramDateToWire(el);
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
        });

        programDatepickersReady = true;
    }

    window.syncProgramDates = syncAllProgramDates;

    $(function () {
        initProgramDatepickers();

        $(document).on('blur', '.jalali-picker-program', function () {
            syncProgramDateToWire(this);
        });
    });

    document.addEventListener('livewire:navigated', function () {
        if (!document.querySelector('.jalali-picker-program')) return;
        destroyProgramDatepickers();
        initProgramDatepickers();
    });
})();
</script>
@endpush
