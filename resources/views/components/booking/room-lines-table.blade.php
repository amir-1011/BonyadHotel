{{-- Shared table for booking room lines (show pages + PDF) --}}
@props(['booking', 'compact' => false])

@php
    $roomLines = $booking->bookingRooms;
    $hasRoomLines = $roomLines->isNotEmpty();
@endphp

@if($hasRoomLines)
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle {{ $compact ? '' : '' }}">
        <thead class="table-light">
            <tr>
                <th class="col-index">#</th>
                <th>نوع اتاق</th>
                <th>اتاق اختصاصی</th>
                <th>تعرفه</th>
                @unless($compact)
                <th>بزرگسال</th>
                <th>کودک زیر ۶</th>
                <th>کف‌خواب</th>
                <th>اتاق مصرفی</th>
                @endunless
            </tr>
        </thead>
        <tbody>
            @foreach($roomLines as $i => $line)
            <tr wire:key="booking-room-line-{{ $booking->id }}-{{ $line->id ?? $i }}">
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $line->roomType?->name ?? '—' }}</strong></td>
                <td>
                    @if($line->room)
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle">{{ $line->room->name }}</span>
                    @else
                    <span class="text-muted">—</span>
                    @endif
                </td>
                <td>
                    {{ $line->roomRate?->name ?? '—' }}
                    @unless($compact)
                        @if($line->roomRate)
                            <span class="text-muted small d-block">{{ \App\Support\PdfPersian::toPersianDigits(number_format($line->roomRate->price_per_night)) }} ریال/شب/تخت</span>
                        @endif
                    @endunless
                </td>
                @unless($compact)
                <td>{{ $line->adults }}</td>
                <td>{{ $line->children_under_6 }}</td>
                <td>{{ $line->extra_guests ?: '—' }}</td>
                <td>{{ $line->rooms_consumed }}</td>
                @endunless
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@elseif($booking->roomType)
<ul class="list-group list-group-flush">
    <li class="list-group-item d-flex justify-content-between px-0">
        <span class="text-muted">نوع اتاق</span>
        <strong>{{ $booking->roomType->name }}</strong>
    </li>
    @if($booking->roomRate)
    <li class="list-group-item d-flex justify-content-between px-0">
        <span class="text-muted">تعرفه</span>
        <span>{{ $booking->roomRate->name }}@unless($compact) · {{ \App\Support\PdfPersian::toPersianDigits(number_format($booking->roomRate->price_per_night)) }} ریال/شب/تخت @endunless</span>
    </li>
    @endif
</ul>
@else
<p class="text-muted mb-0">اطلاعات اتاق ثبت نشده است.</p>
@endif
