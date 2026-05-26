<?php

namespace App\Livewire\Pages;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\RoomRate;
use App\Models\RoomType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'ثبت رزرو'])]
class BookingCreate extends Component
{
    public Accommodation $accommodation;

    #[Url(as: 'check_in')]
    public string $checkIn  = '';

    #[Url(as: 'check_out')]
    public string $checkOut = '';

    #[Url]
    public int $guests = 1;

    public ?int $roomTypeId = null;
    public ?int $roomRateId = null;

    public function mount(Accommodation $accommodation): void
    {
        $this->accommodation = $accommodation;
    }

    public function store(): void
    {
        $this->validate([
            'checkIn'  => ['required', 'date', 'after_or_equal:today'],
            'checkOut' => ['required', 'date', 'after:checkIn'],
            'guests'   => ['required', 'integer', 'min:1', 'max:' . $this->accommodation->capacity],
        ], [
            'checkIn.required'        => 'تاریخ ورود الزامی است.',
            'checkIn.after_or_equal'  => 'تاریخ ورود نمی‌تواند در گذشته باشد.',
            'checkOut.required'       => 'تاریخ خروج الزامی است.',
            'checkOut.after'          => 'تاریخ خروج باید بعد از تاریخ ورود باشد.',
            'guests.max'              => "حداکثر ظرفیت این اقامتگاه {$this->accommodation->capacity} نفر است.",
        ]);

        $roomRate = null;
        $roomType = null;

        if ($this->roomRateId) {
            $roomRate = RoomRate::find($this->roomRateId);
            if ($roomRate) {
                $roomType = $roomRate->roomType;
                if ($roomType->accommodation_id !== $this->accommodation->id) {
                    $roomRate = null;
                    $roomType = null;
                }
            }
        } elseif ($this->roomTypeId) {
            $roomType = RoomType::find($this->roomTypeId);
            if ($roomType && $roomType->accommodation_id !== $this->accommodation->id) {
                $roomType = null;
            }
        }

        if ($roomType) {
            $roomsNeeded = (int) ceil($this->guests / max(1, (int) $roomType->capacity));
            if (!$roomType->isAvailable($this->checkIn, $this->checkOut, $roomsNeeded)) {
                $this->addError('checkIn', 'متأسفانه ظرفیت کافی برای تعداد نفرات انتخابی در بازه تاریخ انتخابی شما وجود ندارد.');
                return;
            }
        } else {
            $roomsNeeded = 1;
            if (!$this->accommodation->isAvailable($this->checkIn, $this->checkOut)) {
                $this->addError('checkIn', 'متأسفانه این اقامتگاه در بازه تاریخ انتخابی شما رزرو شده است.');
                return;
            }
        }

        $pricePerNight = $roomRate ? $roomRate->price_per_night : $this->accommodation->price_per_night;
        $user          = Auth::user();
        $nights        = (int) (new \DateTime($this->checkIn))->diff(new \DateTime($this->checkOut))->days;

        $availMap  = $roomType ? $roomType->availabilityMap($this->checkIn, $this->checkOut) : [];
        $basePrice = 0;
        $cursor    = new \DateTime($this->checkIn);
        $endDate   = new \DateTime($this->checkOut);
        while ($cursor < $endDate) {
            $dayKey     = $cursor->format('Y-m-d');
            $dayData    = $availMap[$dayKey] ?? null;
            $nightPrice = ($dayData && isset($dayData['effective_price']) && $dayData['effective_price'] !== null)
                ? (int) $dayData['effective_price']
                : $pricePerNight;
            $basePrice += $nightPrice * $this->guests;
            $cursor->modify('+1 day');
        }

        $discountPct    = $user->discount_percentage;
        $discountAmount = (int) round($basePrice * $discountPct / 100);
        $totalPrice     = $basePrice - $discountAmount;

        $booking = Booking::create([
            'user_id'             => $user->id,
            'accommodation_id'    => $this->accommodation->id,
            'room_type_id'        => $roomType?->id,
            'room_rate_id'        => $roomRate?->id,
            'check_in'            => $this->checkIn,
            'check_out'           => $this->checkOut,
            'guests'              => $this->guests,
            'rooms_consumed'      => $roomsNeeded,
            'nights'              => $nights,
            'base_price'          => $basePrice,
            'discount_percentage' => $discountPct,
            'discount_amount'     => $discountAmount,
            'total_price'         => $totalPrice,
            'status'              => 'confirmed',
            'tracking_code'       => strtoupper(Str::random(10)),
        ]);

        session()->flash('status', 'رزرو شما با موفقیت ثبت شد.');
        $this->redirectRoute('bookings.show', $booking, navigate: true);
    }

    public function render()
    {
        $accommodation = $this->accommodation;
        $checkIn       = $this->checkIn;
        $checkOut      = $this->checkOut;
        $guests        = $this->guests;

        return view('bookings.create', compact('accommodation', 'checkIn', 'checkOut', 'guests'));
    }
}
