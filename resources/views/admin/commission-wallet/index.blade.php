@push('styles')
<link rel="stylesheet" href="{{ vasset('vendor/persian-datepicker/persian-datepicker.min.css') }}">
<style>
.datepicker-plot-area { font-family: 'Vazirmatn', sans-serif !important; }
</style>
@endpush

<div>

@php
    $metrics = [
        ['label' => 'موجودی کیف پول', 'value' => \App\Support\PdfPersian::toPersianDigits(number_format($stats['balance'])), 'icon' => 'wallet2', 'suffix' => 'ریال'],
        ['label' => 'کل واریزی‌ها', 'value' => \App\Support\PdfPersian::toPersianDigits(number_format($stats['total_credits'])), 'icon' => 'arrow-down-circle', 'suffix' => 'ریال'],
        ['label' => 'کل برگشت‌ها', 'value' => \App\Support\PdfPersian::toPersianDigits(number_format($stats['total_reversals'])), 'icon' => 'arrow-up-circle', 'suffix' => 'ریال'],
        ['label' => 'جمع تسویه‌شده', 'value' => \App\Support\PdfPersian::toPersianDigits(number_format($stats['total_settled'])), 'icon' => 'cash-coin', 'suffix' => 'ریال'],
        ['label' => 'آخرین تسویه تا', 'value' => $stats['last_settlement_end'] ? \App\Support\PdfPersian::toPersianDigits($stats['last_settlement_end']) : '—', 'icon' => 'calendar-check', 'suffix' => ''],
        ['label' => 'تعداد تراکنش‌ها', 'value' => \App\Support\PdfPersian::toPersianDigits(number_format($stats['entries_count'])), 'icon' => 'list-check', 'suffix' => 'رکورد'],
    ];
@endphp

<div class="row g-3 mb-3">
    @foreach($metrics as $m)
    <div class="col-6 col-md-4 col-xl-2">
        <div class="ta-metric">
            <div class="ta-metric__icon"><i class="bi bi-{{ $m['icon'] }}"></i></div>
            <div class="ta-metric__label">{{ $m['label'] }}</div>
            <div class="ta-metric__value">{{ $m['value'] }} <span class="fs-6 fw-normal text-muted">{{ $m['suffix'] }}</span></div>
        </div>
    </div>
    @endforeach
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center justify-content-between gap-2">
        <span class="fw-semibold small" role="button" data-bs-toggle="collapse" data-bs-target="#commissionFilterBody">
            <i class="bi bi-funnel me-1"></i>جستجو و فیلتر
            @if($hasActiveFilters)
                <span class="badge bg-primary">فعال</span>
            @else
                <i class="bi bi-chevron-down text-muted" style="font-size:.8rem"></i>
            @endif
        </span>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" wire:click="openSettlementModal" class="btn btn-primary btn-sm">
                <i class="bi bi-bank me-1"></i>تسویه دوره
            </button>
            <a href="{{ route('admin.commission-wallet.export', $exportQuery) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>خروجی اکسل
                @if($hasActiveFilters)
                <span class="badge bg-white text-success ms-1">فیلترشده</span>
                @endif
            </a>
        </div>
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
                        <option value="period_settlement">تسویه دوره</option>
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
                    <input type="text" wire:model="draftTransactionMin" class="form-control form-control-sm" placeholder="ریال">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label form-label-sm mb-1 text-muted">مبلغ تراکنش تا</label>
                    <input type="text" wire:model="draftTransactionMax" class="form-control form-control-sm" placeholder="ریال">
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top flex-wrap">
                <button type="submit" class="btn btn-primary btn-sm" onclick="window.syncCommissionFilterDates && window.syncCommissionFilterDates()">
                    <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                </button>
                @if($hasActiveFilters || $hasDraftChanges)
                <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-lg me-1"></i>پاک کردن
                </button>
                @endif
                @if($hasActiveFilters)
                <span class="ta-filter-stats ms-auto">
                    <span class="ta-filter-stat">
                        <i class="bi bi-receipt"></i>
                        <span class="ta-filter-stat-value">{{ \App\Support\PdfPersian::toPersianDigits(number_format($filteredStats['count'])) }}</span>
                        <span class="ta-filter-stat-label">رکورد</span>
                    </span>
                    <span class="ta-filter-stat">
                        <i class="bi bi-cash-coin"></i>
                        <span class="ta-filter-stat-value {{ $filteredStats['sum_commission'] >= 0 ? 'ta-filter-stat-value--ok' : '' }}">{{ \App\Support\PdfPersian::toPersianDigits(number_format($filteredStats['sum_commission'])) }}</span>
                        <span class="ta-filter-stat-label">کارمزد</span>
                    </span>
                </span>
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
                    <td>{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->transaction_amount)) }}</td>
                    <td class="fw-semibold {{ $entry->commission_amount >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $entry->commission_amount >= 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($entry->commission_amount)) }}
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
            جمع کارمزد فیلتر: <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($filteredStats['sum_commission'])) }}</strong> ریال
        </div>
        @else
        <div></div>
        @endif
        {{ $entries->links() }}
    </div>
    @endif
</div>

@if($showSettlementModal)
<div class="modal-backdrop fade show" style="z-index:1040;"></div>
<div class="modal fade show d-block" style="z-index:1045;" tabindex="-1" wire:keydown.escape="closeSettlementModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-bank me-2"></i>تسویه دوره کارمزد</h5>
                <button type="button" class="btn-close" wire:click="closeSettlementModal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    تراکنش‌های کارمزد (واریز، برگشت و تعدیل رزرو) از پایان آخرین تسویه تا تاریخ انتخابی جمع می‌شوند و با تأیید، یک رکورد تسویه از موجودی کیف پول کسر می‌گردد.
                </p>
                @if($stats['last_settlement_end'])
                <div class="alert alert-light border small py-2 mb-3">
                    آخرین تسویه تا: <strong>{{ \App\Support\PdfPersian::toPersianDigits($stats['last_settlement_end']) }}</strong>
                    · مبلغ: {{ \App\Support\PdfPersian::toPersianDigits(number_format($stats['last_settlement_amount'])) }} ریال
                </div>
                @endif
                <label class="form-label small fw-semibold">پایان دوره (شمسی)</label>
                <div class="input-group input-group-sm mb-2" wire:ignore>
                    <input type="text"
                           id="commission-settlement-period-end"
                           class="form-control jalali-picker-commission-settlement"
                           data-wire-prop="settlementPeriodEndJalali"
                           value="{{ $settlementPeriodEndJalali }}"
                           autocomplete="off"
                           placeholder="۱۴۰۴/۰۶/۱۹">
                    <button type="button" class="btn btn-outline-secondary commission-clear-date"
                            data-target="commission-settlement-period-end"
                            data-wire-prop="settlementPeriodEndJalali"
                            tabindex="-1"><i class="bi bi-x"></i></button>
                </div>
                @error('settlementPeriodEndJalali')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                <button type="button"
                        class="btn btn-sm btn-outline-secondary mb-3"
                        id="commission-settlement-calc-btn">
                    <i class="bi bi-calculator me-1"></i>محاسبه مبلغ دوره
                </button>
                @if($settlementPreview)
                <div class="border rounded p-3 bg-light">
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">تعداد رکورد در دوره</span>
                        <strong>{{ \App\Support\PdfPersian::toPersianDigits(number_format($settlementPreview['entries_count'])) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">از تاریخ</span>
                        <span>{{ $settlementPreview['period_start'] ? \App\Support\PdfPersian::toPersianDigits(str_replace('-', '/', $settlementPreview['period_start'])) : 'ابتدای دوره' }}</span>
                    </div>
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">تا تاریخ</span>
                        <span>{{ \App\Support\PdfPersian::toPersianDigits($settlementPreview['period_end_jalali'] ?? '') }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="fw-semibold">مبلغ قابل تسویه</span>
                        <span class="fs-5 fw-bold text-primary">
                            {{ \App\Support\PdfPersian::toPersianDigits(number_format($settlementPreview['net_amount'])) }} <span class="fs-6 fw-normal">ریال</span>
                        </span>
                    </div>
                    <div class="text-muted small mt-2">
                        موجودی پس از تسویه: {{ \App\Support\PdfPersian::toPersianDigits(number_format($stats['balance'] - $settlementPreview['net_amount'])) }} ریال
                    </div>
                </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" wire:click="closeSettlementModal">انصراف</button>
                <button type="button"
                        class="btn btn-primary"
                        wire:click="confirmPeriodSettlement"
                        wire:loading.attr="disabled"
                        @if(!$settlementPreview || ($settlementPreview['net_amount'] ?? 0) <= 0) disabled @endif
                        data-swal-confirm="تسویه این دوره ثبت شود و مبلغ از موجودی کیف پول کسر گردد؟"
                        data-swal-confirm-title="تأیید تسویه دوره"
                        data-swal-confirm-variant="warning">
                    <span wire:loading.remove wire:target="confirmPeriodSettlement"><i class="bi bi-check2-circle me-1"></i>تأیید تسویه</span>
                    <span wire:loading wire:target="confirmPeriodSettlement">در حال ثبت...</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif

</div>

@push('scripts')
<script>
(function () {
    var commissionDatepickersReady = false;

    function commissionWire(fromEl) {
        var host = fromEl && fromEl.closest ? fromEl.closest('[wire\\:id]') : null;
        if (!host) {
            var root = document.getElementById('commission-filter-form');
            host = root ? root.closest('[wire\\:id]') : document.querySelector('[wire\\:id]');
        }
        if (!host) return null;
        return Livewire.find(host.getAttribute('wire:id'));
    }

    function syncCommissionDateToWire(input, options) {
        options = options || {};
        var wire = commissionWire(input);
        var prop = input.getAttribute('data-wire-prop');
        if (!wire || !prop) {
            return Promise.resolve();
        }

        return wire.set(prop, input.value || '').then(function () {
            if (options.refreshPreview && prop === 'settlementPeriodEndJalali' && typeof wire.call === 'function') {
                return wire.call('refreshSettlementPreview');
            }
        });
    }

    function syncSettlementPeriodDate(refreshPreview) {
        var input = document.getElementById('commission-settlement-period-end');
        if (!input) {
            return Promise.resolve();
        }

        return syncCommissionDateToWire(input, { refreshPreview: !!refreshPreview });
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

    function destroySettlementDatepicker() {
        var $input = $('#commission-settlement-period-end');
        if ($input.length && $input.data('pDatepicker')) {
            try { $input.pDatepicker('destroy'); } catch (e) { /* ignore */ }
            $input.removeData('pDatepicker');
        }
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
                persianDigit: true,
                toolbox: {
                    enabled: true,
                    todayButton: { enabled: true },
                    submitButton: { enabled: false },
                },
                onSelect: function () {
                    var el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                    syncCommissionDateToWire(el, { refreshPreview: false });
                    if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
                },
            });
        });

        commissionDatepickersReady = true;
    }

    function initSettlementDatepicker() {
        var $input = $('#commission-settlement-period-end');
        if (!$input.length || $input.data('pDatepicker')) return;

        $input.pDatepicker({
            format: 'YYYY/MM/DD',
            viewMode: 'day',
            autoClose: true,
            initialValue: false,
            initialValueType: 'persian',
            persianDigit: true,
            toolbox: {
                enabled: true,
                todayButton: { enabled: true },
                submitButton: { enabled: false },
            },
            onSelect: function () {
                var el = this.model && this.model.inputElement ? this.model.inputElement : $input[0];
                syncCommissionDateToWire(el, { refreshPreview: false });
                if (window.BonyadJalaliDate) window.BonyadJalaliDate.syncInputTodayClass(el);
            },
        });
    }

    window.syncCommissionFilterDates = syncAllCommissionDates;

    $(function () {
        initCommissionDatepickers();

        $('#commission-filter-form').on('submit', function () {
            syncAllCommissionDates();
        });

        $(document).on('blur', '.jalali-picker-commission', function () {
            syncCommissionDateToWire(this, { refreshPreview: false });
        });

        $(document).on('blur', '.jalali-picker-commission-settlement', function () {
            syncCommissionDateToWire(this, { refreshPreview: false });
        });

        $(document).on('click', '#commission-settlement-calc-btn', function () {
            syncSettlementPeriodDate(true);
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
        Livewire.on('init-commission-settlement-datepicker', function () {
            requestAnimationFrame(function () {
                destroySettlementDatepicker();
                initSettlementDatepicker();
            });
        });

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
