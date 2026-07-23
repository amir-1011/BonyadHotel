<div>

<div class="d-flex align-items-center justify-content-end mb-3 flex-wrap gap-2">
    <a href="{{ route('admin.cancellation-settings') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-gear me-1"></i>تنظیمات بازگشت وجه و دلایل</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold">وضعیت</label>
                <select wire:model.live="status" class="form-select form-select-sm">
                    <option value="">همه</option>
                    <option value="pending">در انتظار بررسی</option>
                    <option value="approved">تایید شده</option>
                    <option value="rejected">رد شده</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">اقامتگاه</label>
                <select wire:model.live="accommodationId" class="form-select form-select-sm">
                    <option value="">همه اقامتگاه‌ها</option>
                    @foreach($accommodations as $acc)
                    <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">جستجو (کد رهگیری، نام یا موبایل مهمان)</label>
                <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm" placeholder="جستجو...">
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>رزرو</th>
                    <th>اقامتگاه</th>
                    <th>مهمان</th>
                    <th>دلیل</th>
                    <th>روز تا ورود</th>
                    <th>درصد / مبلغ بازگشت</th>
                    <th>شماره حساب</th>
                    <th>وضعیت</th>
                    <th style="min-width:220px;">عملیات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                <tr wire:key="req-{{ $request->id }}">
                    <td>
                        <a wire:navigate href="{{ route('admin.bookings.show', $request->booking) }}" class="fw-semibold text-decoration-none">{{ $request->booking->tracking_code }}</a>
                        <div class="text-muted small">{{ \Morilog\Jalali\Jalalian::fromCarbon($request->created_at)->format('Y/m/d H:i') }}</div>
                    </td>
                    <td>{{ $request->booking->accommodation->name ?? '—' }}</td>
                    <td>{{ $request->booking->bookerName() }}<div class="text-muted small" style="direction:ltr;">{{ $request->booking->bookerMobile() }}</div></td>
                    <td class="small">{{ $request->reasonDisplay() }}</td>
                    <td>
                        @if($request->isMidStay())
                        <span class="badge bg-secondary-subtle text-dark" title="در حین اقامت ثبت شده">{{ $request->nightsRemaining() }} شب مانده از {{ $request->nightsTotal() }}</span>
                        @else
                        {{ $request->days_before_checkin }}
                        @endif
                    </td>
                    <td>{{ $request->refund_percentage }}٪ &middot; {{ number_format($request->refund_amount) }} ت</td>
                    <td class="small" style="direction:ltr;">{{ $request->refund_account_number }}</td>
                    <td>
                        <span class="badge bg-{{ $request->statusColor() }}">{{ $request->statusLabel() }}</span>
                        @if($request->isRejected() && $request->rejection_reason)
                        <div class="text-muted small mt-1">{{ $request->rejection_reason }}</div>
                        @endif
                    </td>
                    <td>
                        @if($request->isPending())
                        <div class="d-flex gap-1">
                            <button type="button" wire:click="approve({{ $request->id }})" data-swal-confirm="با تایید این درخواست، رزرو لغو شده و مبلغ {{ number_format($request->refund_amount) }} تومان قابل استرداد ثبت می‌شود. ادامه می‌دهید؟" class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                            <button type="button"
                                data-swal-prompt
                                data-swal-prompt-method="submitReject"
                                data-swal-prompt-request-id="{{ $request->id }}"
                                data-swal-prompt-title="رد درخواست کنسلی"
                                data-swal-prompt-label="دلیل رد درخواست"
                                class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                        </div>
                        @elseif($request->isApproved() && !$request->isSettled())
                        <button type="button" wire:click="openSettleModal({{ $request->id }})" class="btn btn-sm btn-primary"><i class="bi bi-cash-coin me-1"></i>ثبت تسویه</button>
                        @elseif($request->isSettled())
                        <span class="text-success small"><i class="bi bi-check2-all me-1"></i>تسویه شده</span>
                        @else
                        <span class="text-muted small">—</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-5">درخواستی یافت نشد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white">{{ $requests->links() }}</div>
</div>


@include('components.cancellation.settle-modal')

</div>
