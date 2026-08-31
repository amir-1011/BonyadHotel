<div class="ta-page">
    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <form wire:submit="applyFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted">جستجو</label>
                        <input type="search" wire:model="draftSearch" class="form-control form-control-sm" placeholder="کد رزرو، پیگیری، کارت">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted">استان</label>
                        <select wire:model="draftProvinceFilter" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach($provinces as $province)
                            <option value="{{ $province->id }}">{{ $province->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted">ترمینال</label>
                        <select wire:model="draftTerminalFilter" class="form-select form-select-sm">
                            <option value="">همه</option>
                            @foreach($terminals as $terminal)
                            <option value="{{ $terminal->id }}">{{ $terminal->displayLabel() }} — {{ $terminal->province?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted">از تاریخ</label>
                        <input type="text" wire:model="draftDateFrom" class="form-control form-control-sm jalali-date-input" placeholder="1404/01/01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm mb-1 text-muted">تا تاریخ</label>
                        <input type="text" wire:model="draftDateTo" class="form-control form-control-sm jalali-date-input" placeholder="1404/12/29">
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 mt-3 pt-2 border-top flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>اعمال فیلتر
                    </button>
                    @if($hasActiveFilters)
                    <button type="button" wire:click="resetFilters" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>پاک کردن
                    </button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>زمان</th>
                        <th>رزرو</th>
                        <th>اقامتگاه</th>
                        <th>مبلغ</th>
                        <th>تغییر</th>
                        <th>کارت / پیگیری</th>
                        <th>ترمینال</th>
                        <th>توضیح</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                    <tr>
                        <td class="small" dir="ltr">{{ \Morilog\Jalali\Jalalian::fromDateTime($record->payment_at)->format('Y/m/d H:i') }}</td>
                        <td>
                            <a wire:navigate href="{{ route($panel . '.bookings.show', $record->booking) }}" class="text-decoration-none">
                                <code dir="ltr">{{ $record->booking?->tracking_code }}</code>
                            </a>
                            <div class="text-muted small">{{ $record->contextLabel() }}</div>
                        </td>
                        <td class="small">{{ $record->booking?->accommodation?->name }}</td>
                        <td class="fw-semibold" dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($record->amount)) }}</td>
                        <td dir="ltr" class="small {{ $record->amount_delta > 0 ? 'text-success' : ($record->amount_delta < 0 ? 'text-danger' : 'text-muted') }}">
                            @if($record->amount_delta !== 0)
                            {{ $record->amount_delta > 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($record->amount_delta)) }}
                            @else
                            —
                            @endif
                        </td>
                        <td class="small">
                            @if($record->card_last_four)<div dir="ltr">کارت: {{ $record->card_last_four }}</div>@endif
                            @if($record->transaction_tracking)<div dir="ltr">پیگیری: {{ $record->transaction_tracking }}</div>@endif
                        </td>
                        <td class="small">{{ $record->posTerminal?->displayLabel() ?? '—' }}</td>
                        <td class="small text-muted">{{ \Illuminate\Support\Str::limit($record->price_adjustment_reason ?? '—', 40) }}</td>
                        <td class="text-end">
                            @if($record->hasDocuments())
                            <div class="d-flex flex-wrap gap-1 justify-content-end">
                                @foreach($record->documentPaths() as $docIndex => $path)
                                <a href="{{ route($panel . '.bookings.payment-document', ['booking' => $record->booking, 'record' => $record, 'index' => $docIndex]) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-paperclip"></i>{{ $docIndex + 1 }}
                                </a>
                                @endforeach
                            </div>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">تراکنشی ثبت نشده است.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($records->hasPages())
        <div class="card-footer">{{ $records->links() }}</div>
        @endif
    </div>
</div>
