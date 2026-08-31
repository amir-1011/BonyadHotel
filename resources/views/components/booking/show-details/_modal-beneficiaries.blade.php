@php
    $beneficiaryCosts = $booking->beneficiaryCosts;
@endphp

@if($beneficiaryCosts->isEmpty())
<div class="text-muted small">ذینفعی برای این رزرو ثبت نشده است.</div>
@else
<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>ذینفع</th>
                <th>شناسه</th>
                <th>بدهی (ریال)</th>
                <th>کاربر متصل</th>
                <th>مدارک</th>
            </tr>
        </thead>
        <tbody>
            @foreach($beneficiaryCosts as $cost)
            <tr>
                <td>
                    <div class="fw-semibold">{{ $cost->beneficiary?->name ?? '—' }}</div>
                    @if($cost->description)
                    <div class="small text-muted">{{ $cost->description }}</div>
                    @endif
                </td>
                <td class="small">{{ $cost->beneficiary?->beneficiary_code ?? '—' }}</td>
                <td class="fw-semibold">{{ \App\Support\PdfPersian::toPersianDigits(number_format($cost->debt_amount)) }}</td>
                <td class="small">
                    @php $linkedUser = $cost->user ?? $cost->beneficiary?->user; @endphp
                    @if($linkedUser && $panel === 'admin')
                    <a wire:navigate href="{{ route('admin.users.show', $linkedUser) }}" class="text-decoration-none">
                        {{ $linkedUser->name ?? $linkedUser->mobile }}
                    </a>
                    @elseif($linkedUser)
                    <span>{{ $linkedUser->name ?? $linkedUser->mobile }}</span>
                    @else
                    —
                    @endif
                </td>
                <td>
                    @if(!empty($cost->documents))
                    <x-program.document-list :paths="$cost->documents" :compact="true" />
                    @else
                    —
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif
