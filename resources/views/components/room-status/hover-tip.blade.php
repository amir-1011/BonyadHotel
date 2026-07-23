@props(['room'])

@php
    $tip = null;
    if (($room['status'] ?? '') === 'blocked') {
        $tip = ['type' => 'blocked', 'text' => $room['block_reason'] ?? 'مسدود توسط میزبان'];
    } elseif (($room['status'] ?? '') === 'occupied' && !empty($room['current_booking'])) {
        $tip = ['type' => 'booking', 'booking' => $room['current_booking']];
    } elseif (!empty($room['has_future']) && !empty($room['future_bookings'])) {
        $tip = ['type' => 'booking', 'booking' => $room['future_bookings'][0], 'future' => true];
    }
@endphp

@if($tip)
<div class="room-status-box__hover-tip" role="tooltip">
    @if($tip['type'] === 'blocked')
    <div class="room-status-box__hover-tip-row">
        <span class="room-status-box__hover-tip-key">دلیل مسدودی</span>
        <span>{{ $tip['text'] }}</span>
    </div>
    @else
    @php $b = $tip['booking']; @endphp
    @if(!empty($tip['future']))
    <div class="room-status-box__hover-tip-row room-status-box__hover-tip-row--muted">
        <span class="room-status-box__hover-tip-key">وضعیت</span>
        <span>رزرو آینده</span>
    </div>
    @endif
    @if(!empty($b['guest_name']))
    <div class="room-status-box__hover-tip-row">
        <span class="room-status-box__hover-tip-key">مهمان</span>
        <span>{{ $b['guest_name'] }}</span>
    </div>
    @endif
    <div class="room-status-box__hover-tip-row">
        <span class="room-status-box__hover-tip-key">ورود</span>
        <span dir="ltr">@jalali($b['check_in'])</span>
    </div>
    <div class="room-status-box__hover-tip-row">
        <span class="room-status-box__hover-tip-key">خروج</span>
        <span dir="ltr">@jalali($b['check_out'])</span>
    </div>
    @if(!empty($b['guests']))
    <div class="room-status-box__hover-tip-row room-status-box__hover-tip-row--muted">
        <span class="room-status-box__hover-tip-key">تعداد</span>
        <span>{{ $b['guests'] }} نفر</span>
    </div>
    @endif
    @endif
</div>
@endif
