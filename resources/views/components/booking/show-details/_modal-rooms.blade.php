@if($hasRoomLines)
<div class="table-responsive">
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>نوع اتاق</th>
                <th>تعرفه</th>
                <th>بزرگسال</th>
                <th>کودک زیر ۶</th>
                <th>کف‌خواب</th>
                <th>اتاق مصرفی</th>
                <th>رزرو کامل</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roomLines as $i => $line)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td><strong>{{ $line->roomType?->name ?? '—' }}</strong></td>
                <td>{{ $line->roomRate?->name ?? '—' }}</td>
                <td>{{ $line->adults }}</td>
                <td>{{ $line->children_under_6 }}</td>
                <td>{{ $line->extra_guests ?: '—' }}</td>
                <td>{{ $line->rooms_consumed }}</td>
                <td>{{ $line->bill_full_rooms ? 'بله' : '—' }}</td>
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
        <span>{{ $booking->roomRate->name }} · {{ number_format($booking->roomRate->price_per_night) }} ت/شب</span>
    </li>
    @endif
</ul>
@else
<p class="text-muted mb-0">اطلاعات اتاق ثبت نشده است.</p>
@endif
