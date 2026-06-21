{{-- Reusable room picker for manual booking (admin/host) --}}
@if($roomTypes->isEmpty())
<div class="alert alert-warning mb-0">برای این اقامتگاه هنوز نوع اتاقی تعریف نشده است.</div>
@else
<div class="row g-3">
    @foreach($roomTypes as $roomType)
    @if($roomType->rates->isNotEmpty())
    <div class="col-12">
        <div class="border rounded p-3 {{ $roomTypeId == $roomType->id ? 'border-primary bg-primary-subtle' : '' }}">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <div class="fw-semibold">{{ $roomType->name }}</div>
                    <div class="small text-muted">
                        <i class="bi bi-people me-1"></i>{{ $roomType->capacity }} نفر
                        @if($roomType->extra_capacity)
                        · <i class="bi bi-person-add me-1"></i>کف‌خوابی تا {{ $roomType->extra_capacity }} نفر
                        @endif
                    </div>
                </div>
                <button type="button" wire:click="$set('roomTypeId', {{ $roomType->id }})" class="btn btn-sm btn-outline-primary">انتخاب اتاق</button>
            </div>
            <div class="d-flex flex-column gap-2">
                @foreach($roomType->rates as $rate)
                <label class="d-flex align-items-center justify-content-between border rounded p-2 {{ $roomRateId == $rate->id ? 'border-success bg-success-subtle' : 'bg-white' }}" style="cursor:pointer;">
                    <div class="d-flex align-items-center gap-2">
                        <input type="radio" wire:model.live="roomRateId" value="{{ $rate->id }}" name="room_rate_pick">
                        <div>
                            <div class="small fw-semibold">{{ $rate->name }}</div>
                            <div class="small text-muted">{{ number_format($rate->price_per_night) }} تومان/شب/نفر</div>
                        </div>
                    </div>
                </label>
                @endforeach
            </div>
        </div>
    </div>
    @endif
    @endforeach
</div>
@endif
