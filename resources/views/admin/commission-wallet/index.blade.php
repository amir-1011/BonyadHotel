@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
</style>
@endpush

<div>

@php
    $metrics = [
        ['label' => 'موجودی کیف پول', 'value' => number_format($stats['balance']), 'icon' => 'wallet2', 'suffix' => 'تومان'],
        ['label' => 'کل واریزی‌ها', 'value' => number_format($stats['total_credits']), 'icon' => 'arrow-down-circle', 'suffix' => 'تومان'],
        ['label' => 'کل برگشت‌ها', 'value' => number_format($stats['total_reversals']), 'icon' => 'arrow-up-circle', 'suffix' => 'تومان'],
        ['label' => 'تعداد تراکنش‌ها', 'value' => number_format($stats['entries_count']), 'icon' => 'list-check', 'suffix' => 'رکورد'],
    ];
@endphp

<div class="ta-page-head">
    {{-- <div>
        <div class="text-muted small">
            مبلغ ثابت {{ number_format(config('platform_commission.fixed_amount')) }} تومان برای هر رزرو (بدون کارمزد خدمات؛ معاف: اردو و رزرو با مبلغ صفر)
        </div>
    </div> --}}
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('admin.commission-wallet.export', $exportQuery) }}" class="btn btn-success btn-sm">
            <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
            @if($hasActiveFilters)
            <span class="badge bg-white text-success ms-1">فیلترشده</span>
            @endif
        </a>
        @if($hasActiveFilters)
        <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg me-1"></i>پاک کردن فیلترها
        </button>
        @endif
    </div>
</div>

<div class="row g-4 mb-4">
    @foreach($metrics as $m)
    <div class="col-6 col-xl-3">
        <div class="ta-metric">
            <div class="ta-metric__icon"><i class="bi bi-{{ $m['icon'] }}"></i></div>
            <div class="ta-metric__label">{{ $m['label'] }}</div>
            <div class="ta-metric__value">{{ $m['value'] }} <span class="fs-6 fw-normal text-muted">{{ $m['suffix'] }}</span></div>
        </div>
    </div>
    @endforeach
</div>

@if($hasActiveFilters)
<div class="alert alert-info py-2 px-3 small mb-3 d-flex flex-wrap align-items-center gap-2">
    <i class="bi bi-funnel-fill"></i>
    <span>
        نتیجه فیلتر:
        <strong>{{ number_format($filteredStats['count']) }}</strong> رکورد ·
        جمع کارمزد: <strong class="{{ $filteredStats['sum_commission'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($filteredStats['sum_commission']) }}</strong> تومان ·
        واریز: {{ number_format($filteredStats['sum_credits']) }} ·
        برگشت/کسر: {{ number_format($filteredStats['sum_debits']) }}
    </span>
</div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between" style="cursor:pointer" data-bs-toggle="collapse" data-bs-target="#commissionFilterBody">
        <span class="fw-semibold small"><i class="bi bi-funnel me-1"></i>جستجو و فیلتر</span>
        @if($hasActiveFilters)
            <span class="badge bg-primary">فعال</span>
        @else
            <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
        @endif
    </div>
    <div class="collapse {{ $hasActiveFilters ? 'show' : 'show' }}" id="commissionFilterBody">
        <div class="card-body pb-2 pt-3">
            <form wire:submit="applyFilters" id="commission-filter-form">
            <div class="row g-2">
                <div class="col-12 col-md-4">
                    <label class="form-label form-label-sm mb-1 text-muted">جستجو</label>
                    <input type="text" wire:model="draftSearch" class="form-control form-control-sm"
                           placeholder="شناسه / کد رزرو / مهمان / موبایل / اقامتگاه / خدمت">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">دسته</label>
                    <select wire:model="draftCategory" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="accommodation">اقامت / رزرو</option>
                        <option value="service">خدمات</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">نوع رکورد</label>
                    <select wire:model="draftEntryType" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="credit">واریز</option>
                        <option value="adjustment">تعدیل</option>
                        <option value="reversal">برگشت</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">دلیل</label>
                    <select wire:model="draftReason" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="booking_confirmed">ثبت رزرو</option>
                        <option value="amount_adjusted">تغییر مبلغ</option>
                        <option value="booking_cancelled">لغو رزرو</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">جهت مبلغ</label>
                    <select wire:model="draftSign" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="positive">واریز / افزایش (+)</option>
                        <option value="negative">برگشت / کاهش (−)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted">اقامتگاه</label>
                    <select wire:model="draftAccommodationId" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($accommodations as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted">شهر</label>
                    <select wire:model="draftCityId" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->id }}">{{ $city->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted">نوع خدمت</label>
                    <select wire:model="draftServiceCatalogId" class="form-select form-select-sm">
                        <option value="">همه</option>
                        @foreach($serviceCatalogs as $svc)
                            <option value="{{ $svc->id }}">{{ $svc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label form-label-sm mb-1 text-muted">منبع رزرو</label>
                    <select wire:model="draftBookingSource" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="manual">دستی (پنل)</option>
                        <option value="online">آنلاین</option>
                    </select>
                </div>

                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">وضعیت رزرو</label>
                    <select wire:model="draftBookingStatus" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="confirmed">تأیید شده</option>
                        <option value="pending">در انتظار</option>
                        <option value="cancelled">لغو شده</option>
                    </select>
                </div>
                <div class="col-6 col-md-2" wire:ignore>
                    <label class="form-label form-label-sm mb-1 text-muted">تاریخ ثبت از</label>
                    <div class="input-group input-group-sm">
                        <input type="text"
                               id="commission-draft-date-from"
                               class="form-control form-control-sm jalali-picker-commission"
                               data-wire-prop="draftDateFrom"
                               value="{{ $draftDateFrom }}"
                               autocomplete="off"
                               placeholder="۱۴۰۳/۰۱/۰۱">
                        <button type="button" class="btn btn-outline-secondary commission-clear-date" data-target="commission-draft-date-from" data-wire-prop="draftDateFrom" tabindex="-1"><i class="bi bi-x"></i></button>
                    </div>
                </div>
                <div class="col-6 col-md-2" wire:ignore>
                    <label class="form-label form-label-sm mb-1 text-muted">تاریخ ثبت تا</label>
                    <div class="input-group input-group-sm">
                        <input type="text"
                               id="commission-draft-date-to"
                               class="form-control form-control-sm jalali-picker-commission"
                               data-wire-prop="draftDateTo"
                               value="{{ $draftDateTo }}"
                               autocomplete="off"
                               placeholder="۱۴۰۳/۱۲/۲۹">
                        <button type="button" class="btn btn-outline-secondary commission-clear-date" data-target="commission-draft-date-to" data-wire-prop="draftDateTo" tabindex="-1"><i class="bi bi-x"></i></button>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">کارمزد از</label>
                    <input type="text" wire:model="draftCommissionMin" class="form-control form-control-sm" placeholder="مثلاً ۵۰۰۰">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">کارمزد تا</label>
                    <input type="text" wire:model="draftCommissionMax" class="form-control form-control-sm" placeholder="مثلاً ۵۰۰۰۰">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">مبلغ تراکنش از</label>
                    <input type="text" wire:model="draftTransactionMin" class="form-control form-control-sm" placeholder="تومان">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">مبلغ تراکنش تا</label>
                    <input type="text" wire:model="draftTransactionMax" class="form-control form-control-sm" placeholder="تومان">
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top">
                <button type="submit" class="btn btn-primary btn-sm" onclick="window.syncCommissionFilterDates && window.syncCommissionFilterDates()">
                    <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                </button>
                @if($hasActiveFilters || $hasDraftChanges)
                <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg me-1"></i>پاک کردن
                </button>
                @endif
                @if($hasDraftChanges)
                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>تغییرات اعمال نشده — دکمه «اعمال فیلتر» را بزنید</span>
                @endif
            </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex align-items-center justify-content-between">
        <span class="fw-semibold small">تراکنش‌ها ({{ $entries->total() }})</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="col-index">#</th>
                    <th>تاریخ</th>
                    <th>نوع</th>
                    <th>دسته</th>
                    <th>مبلغ تراکنش</th>
                    <th>کارمزد</th>
                    <th>رزرو</th>
                    <th>اقامتگاه</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries as $entry)
                <tr class="cursor-pointer" onclick="window.location='{{ route('admin.commission-wallet.show', $entry) }}'" style="cursor:pointer">
                    <td class="text-muted small">{{ $entry->id }}</td>
                    <td class="small">@jalali($entry->created_at)</td>
                    <td>
                        <span class="badge bg-{{ $entry->isCredit() ? 'success' : 'danger' }}-subtle text-{{ $entry->isCredit() ? 'success' : 'danger' }}">
                            {{ $entry->entryTypeLabel() }}
                        </span>
                        <div class="text-muted" style="font-size:.7rem">{{ $entry->reasonLabel() }}</div>
                    </td>
                    <td>{{ $entry->categoryLabel() }}</td>
                    <td>{{ number_format($entry->transaction_amount) }}</td>
                    <td class="fw-semibold {{ $entry->commission_amount >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $entry->commission_amount >= 0 ? '+' : '' }}{{ number_format($entry->commission_amount) }}
                    </td>
                    <td>
                        @if($entry->booking)
                            <a href="{{ route('admin.bookings.show', $entry->booking) }}" wire:navigate class="text-decoration-none" onclick="event.stopPropagation()">
                                {{ $entry->booking->tracking_code }}
                            </a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="small">{{ $entry->accommodation?->name ?? '—' }}</td>
                    <td>
                        <a wire:navigate href="{{ route('admin.commission-wallet.show', $entry) }}"
                           class="btn btn-sm btn-outline-primary py-0 px-2"
                           onclick="event.stopPropagation()">
                            <i class="bi bi-eye"></i> جزئیات
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        @if($hasActiveFilters)
                            رکوردی با این فیلترها یافت نشد.
                        @else
                            هنوز تراکنش کارمزدی ثبت نشده است.
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($entries->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        @if($hasActiveFilters)
        <div class="small text-muted">
            جمع کارمزد فیلتر: <strong>{{ number_format($filteredStats['sum_commission']) }}</strong> تومان
        </div>
        @else
        <div></div>
        @endif
        {{ $entries->links() }}
    </div>
    @endif
</div>

</div>

@push('scripts')
<script>
(function () {
    var commissionDatepickersReady = false;

    function commissionWire() {
        var root = document.getElementById('commission-filter-form');
        if (!root) return null;
        var host = root.closest('[wire\\:id]');
        if (!host) return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function syncCommissionDateToWire(input) {
        var wire = commissionWire();
        var prop = input.getAttribute('data-wire-prop');
        if (wire && prop) {
            wire.set(prop, input.value || '');
        }
    }

    function syncAllCommissionDates() {
        document.querySelectorAll('.jalali-picker-commission').forEach(syncCommissionDateToWire);
    }

    function destroyCommissionDatepickers() {
        $('.jalali-picker-commission').each(function () {
            if ($(this).data('pDatepicker')) {
                try { $(this).pDatepicker('destroy'); } catch (e) { /* ignore */ }
                $(this).removeData('pDatepicker');
            }
        });
        commissionDatepickersReady = false;
    }

    function initCommissionDatepickers() {
        if (commissionDatepickersReady) return;

        $('.jalali-picker-commission').each(function () {
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
                    syncCommissionDateToWire(el);
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
        });

        commissionDatepickersReady = true;
    }

    window.syncCommissionFilterDates = syncAllCommissionDates;

    $(function () {
        initCommissionDatepickers();

        $('#commission-filter-form').on('submit', function () {
            syncAllCommissionDates();
        });

        $(document).on('blur', '.jalali-picker-commission', function () {
            syncCommissionDateToWire(this);
        });

        $(document).on('click', '.commission-clear-date', function () {
            var targetId = $(this).data('target');
            var input = document.getElementById(targetId);
            if (!input) return;
            input.value = '';
            syncCommissionDateToWire(input);
            if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(input);
        });
    });

    document.addEventListener('livewire:navigated', function () {
        if (!document.querySelector('.jalali-picker-commission')) return;
        destroyCommissionDatepickers();
        initCommissionDatepickers();
    });

    document.addEventListener('livewire:init', function () {
        Livewire.on('commission-wallet-dates-sync', function (data) {
            var from = (data && data.from) ? data.from : '';
            var to = (data && data.to) ? data.to : '';
            var fromEl = document.getElementById('commission-draft-date-from');
            var toEl = document.getElementById('commission-draft-date-to');
            if (fromEl) fromEl.value = from;
            if (toEl) toEl.value = to;
        });
    });
})();
</script>
@endpush
