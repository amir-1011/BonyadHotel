@props(['booking', 'panel' => 'admin'])

@php
    $records = $booking->relationLoaded('paymentRecords')
        ? $booking->paymentRecords
        : $booking->paymentRecords()->with(['posTerminal', 'recordedBy'])->get();
@endphp

@if($records->isNotEmpty())
<section class="bnb-fin-section mt-3">
    <header class="bnb-fin-section__head">
        <div class="bnb-fin-section__icon bnb-fin-section__icon--stay"><i class="bi bi-credit-card-2-front"></i></div>
        <div>
            <h6 class="bnb-fin-section__title">سوابق مالی و پرداخت</h6>
            <p class="bnb-fin-section__meta">{{ $records->count() }} ثبت</p>
        </div>
    </header>
    <div class="bnb-fin-section__body">
        @foreach($records as $record)
        <div class="border rounded-3 p-3 mb-2 bg-light-subtle">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                <div>
                    <div class="fw-semibold small">{{ $record->contextLabel() }}</div>
                    @if($record->action)
                    <div class="text-muted small">{{ $record->action }}</div>
                    @endif
                </div>
                <div class="text-end">
                    <div class="fw-bold" dir="ltr">{{ \App\Support\PdfPersian::toPersianDigits(number_format($record->amount)) }} <span class="text-muted small">ریال</span></div>
                    @if($record->amount_delta !== 0)
                    <div class="small {{ $record->amount_delta > 0 ? 'text-success' : 'text-danger' }}">
                        تغییر: {{ $record->amount_delta > 0 ? '+' : '' }}{{ \App\Support\PdfPersian::toPersianDigits(number_format($record->amount_delta)) }}
                    </div>
                    @endif
                </div>
            </div>
            <div class="small text-muted mt-2 d-grid gap-1">
                @if($record->payment_at)
                <div>زمان پرداخت: <span dir="ltr">{{ \Morilog\Jalali\Jalalian::fromDateTime($record->payment_at)->format('Y/m/d H:i') }}</span></div>
                @endif
                @if($record->card_last_four)
                <div>۴ رقم آخر کارت: <code dir="ltr">{{ $record->card_last_four }}</code></div>
                @endif
                @if($record->transaction_tracking)
                <div>پیگیری: <code dir="ltr">{{ $record->transaction_tracking }}</code></div>
                @endif
                @if($record->posTerminal)
                <div>ترمینال: {{ $record->posTerminal->displayLabel() }}</div>
                @endif
                @if($record->price_adjustment_reason)
                <div>توضیح تغییر مبلغ: {{ $record->price_adjustment_reason }}</div>
                @endif
                @if($record->recordedBy)
                <div>ثبت‌کننده: {{ $record->recordedBy->name }}</div>
                @endif
            </div>
            @if($record->hasDocuments())
            <div class="mt-2 d-flex flex-wrap gap-2">
                @foreach($record->documentPaths() as $docIndex => $path)
                <a href="{{ route($panel . '.bookings.payment-document', ['booking' => $booking, 'record' => $record, 'index' => $docIndex]) }}"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-paperclip me-1"></i>مستند {{ $docIndex + 1 }}
                </a>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>
</section>
@endif
