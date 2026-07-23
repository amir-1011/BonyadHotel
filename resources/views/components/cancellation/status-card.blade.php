@php
    /** @var \App\Models\Booking $booking */
    $isStaffPanel = in_array($panel ?? 'guest', ['admin', 'host'], true);
    $requests = $booking->cancellationRequests;
    $latest = $requests->first();
    // Once the check-out date has passed with no cancellation history at all, there is
    // nothing left to request or to review — hide the whole panel in that case.
    $hidePanel = $requests->isEmpty() && $booking->cancellationWindowClosed();
@endphp

@unless($hidePanel)
<div class="{{ $isStaffPanel ? 'card shadow-sm mt-3' : '' }}" style="{{ $isStaffPanel ? '' : 'border:1px solid var(--bnb-border);border-radius:12px;padding:20px;margin-bottom:16px;' }}">
    <div class="{{ $isStaffPanel ? 'card-header bg-white fw-semibold small d-flex align-items-center justify-content-between flex-wrap gap-2' : 'd-flex align-items-center justify-content-between flex-wrap gap-2 mb-2' }}" style="{{ $isStaffPanel ? '' : 'font-size:12px;color:var(--bnb-gray);font-weight:600;text-transform:uppercase;letter-spacing:.5px;' }}">
        <span><i class="bi bi-x-circle me-2"></i>کنسلی و استرداد وجه</span>

        @if($booking->canRequestCancellation())
        <x-host.can page="bookings.cancellation-submit" action="write" :panel="$panel ?? 'guest'">
        <button type="button" wire:click="openCancellationRequestModal" class="{{ $isStaffPanel ? 'btn btn-sm btn-outline-danger' : 'bnb-filter-pill' }}" style="{{ $isStaffPanel ? '' : 'border-color:var(--bnb-red);color:var(--bnb-red);cursor:pointer;background:none;font-family:var(--bnb-font);' }}">
            <i class="bi bi-x-circle me-1"></i>{{ $isStaffPanel ? 'ثبت درخواست کنسلی' : 'درخواست کنسلی و استرداد وجه' }}
        </button>
        </x-host.can>
        @endif
    </div>

    <div class="{{ $isStaffPanel ? 'card-body' : '' }}">
        @if($requests->isEmpty())
        <p class="{{ $isStaffPanel ? 'text-muted small mb-0' : '' }}" style="{{ $isStaffPanel ? '' : 'font-size:13px;color:var(--bnb-gray);margin:0;' }}">تا کنون درخواست کنسلی برای این رزرو ثبت نشده است.</p>
        @else
        <div class="d-flex flex-column gap-2">
            @foreach($requests as $request)
            <div class="border rounded p-2 p-md-3" style="{{ $isStaffPanel ? '' : 'border-color:var(--bnb-border) !important;' }}">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <span class="badge bg-{{ $request->statusColor() }}">{{ $request->statusLabel() }}</span>
                    <span class="text-muted small">{{ \Morilog\Jalali\Jalalian::fromCarbon($request->created_at)->format('Y/m/d H:i') }}</span>
                </div>

                <div class="row g-2 small">
                    <div class="col-6 col-md-3"><span class="text-muted d-block">دلیل</span><strong>{{ $request->reasonDisplay() }}</strong></div>
                    @if($request->isMidStay())
                    <div class="col-6 col-md-3"><span class="text-muted d-block">شب‌های باقی‌مانده (از {{ $request->nightsTotal() }} شب)</span><strong>{{ $request->nightsRemaining() }} شب</strong></div>
                    @else
                    <div class="col-6 col-md-3"><span class="text-muted d-block">روزهای باقی‌مانده تا ورود</span><strong>{{ $request->days_before_checkin }} روز</strong></div>
                    @endif
                    <div class="col-6 col-md-3"><span class="text-muted d-block">درصد بازگشت وجه</span><strong>{{ $request->refund_percentage }}٪</strong></div>
                    <div class="col-6 col-md-3"><span class="text-muted d-block">مبلغ قابل بازگشت</span><strong>{{ number_format($request->refund_amount) }} تومان</strong></div>
                    @if($request->isMidStay())
                    <div class="col-12"><span class="text-muted small">این درخواست پس از شروع اقامت ثبت شده؛ فقط مبلغ متناسب با شب‌های باقی‌مانده ({{ number_format($request->nightsBasisAmountDisplay()) }} تومان) مشمول {{ $request->refund_percentage }}٪ بازگشت وجه شده است.</span></div>
                    @endif
                    <div class="col-6 col-md-3"><span class="text-muted d-block">شماره حساب/کارت</span><strong style="direction:ltr;display:inline-block;">{{ $request->refund_account_number }}</strong></div>
                    @if($request->refund_account_holder_name)
                    <div class="col-6 col-md-3"><span class="text-muted d-block">به نام</span><strong>{{ $request->refund_account_holder_name }}</strong></div>
                    @endif
                    @if($request->requestedBy)
                    <div class="col-6 col-md-3"><span class="text-muted d-block">ثبت‌کننده درخواست</span><strong>{{ $request->requestedBy->name ?? $request->requestedBy->mobile }}</strong></div>
                    @endif
                    @if($request->notes)
                    <div class="col-12"><span class="text-muted d-block">توضیحات</span>{{ $request->notes }}</div>
                    @endif
                </div>

                @if($request->isRejected() && $request->rejection_reason)
                <div class="alert alert-danger small mt-2 mb-0 py-2"><i class="bi bi-info-circle me-1"></i>دلیل رد: {{ $request->rejection_reason }}</div>
                @endif

                @if($request->isApproved())
                <div class="small text-muted mt-2">
                    توسط {{ $request->decidedBy?->name ?? $request->decidedBy?->mobile ?? '—' }} در تاریخ {{ $request->decided_at ? \Morilog\Jalali\Jalalian::fromCarbon($request->decided_at)->format('Y/m/d H:i') : '—' }} تایید شد.
                    @if($request->isSettled())
                    <br>تسویه توسط {{ $request->settledBy?->name ?? $request->settledBy?->mobile ?? '—' }} در تاریخ {{ \Morilog\Jalali\Jalalian::fromCarbon($request->settled_at)->format('Y/m/d H:i') }}.
                    @if($request->settled_amount)
                    <br>مبلغ واریز: {{ number_format($request->settled_amount) }} تومان به حساب <span style="direction:ltr;display:inline-block;">{{ $request->settled_account_number }}</span>
                    @endif
                    @if($request->settlement_notes)
                    <br>توضیحات تسویه: {{ $request->settlement_notes }}
                    @endif
                    @endif
                </div>
                @endif

                @if($isStaffPanel)
                <div class="d-flex gap-2 mt-2">
                    @if($request->isPending())
                    <x-host.can page="cancellation-requests.decide" action="edit" :panel="$panel ?? 'admin'">
                    <button type="button" wire:click="approveCancellationRequest({{ $request->id }})" data-swal-confirm="با تایید این درخواست، رزرو لغو شده و مبلغ {{ number_format($request->refund_amount) }} تومان قابل استرداد ثبت می‌شود. ادامه می‌دهید؟" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>تایید کنسلی</button>
                    <button type="button"
                        data-swal-prompt
                        data-swal-prompt-method="submitCancellationReject"
                        data-swal-prompt-request-id="{{ $request->id }}"
                        data-swal-prompt-title="رد درخواست کنسلی"
                        data-swal-prompt-label="دلیل رد درخواست"
                        class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg me-1"></i>رد درخواست</button>
                    </x-host.can>
                    @elseif($request->isApproved() && !$request->isSettled())
                    <x-host.can page="cancellation-requests.settle" action="edit" :panel="$panel ?? 'admin'">
                    <button type="button" wire:click="openSettleModal({{ $request->id }})" class="btn btn-sm btn-primary"><i class="bi bi-cash-coin me-1"></i>ثبت تسویه (پرداخت شد)</button>
                    </x-host.can>
                    @endif
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

@include('components.cancellation.request-modal', ['booking' => $booking, 'panel' => $panel ?? 'guest'])
@if($isStaffPanel)
@include('components.cancellation.settle-modal')
@endif
@endunless
