<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Models\City;
use App\Exports\AdminBookingsExport;
use Morilog\Jalali\Jalalian;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'مدیریت رزروها', 'pageTitle' => 'رزروها'])]
class BookingIndex extends Component
{
    use WithPagination;

    #[Url] public string $search           = '';
    #[Url] public string $status           = '';
    #[Url] public string $accommodationId  = '';
    #[Url] public string $cityId           = '';
    #[Url] public string $checkInFrom      = '';
    #[Url] public string $checkInTo        = '';
    #[Url] public string $checkOutFrom     = '';
    #[Url] public string $checkOutTo       = '';
    #[Url] public string $nightsMin        = '';
    #[Url] public string $nightsMax        = '';
    #[Url] public string $priceMin         = '';
    #[Url] public string $priceMax         = '';
    #[Url] public string $guestsMin        = '';
    #[Url] public bool   $hasDiscount      = false;
    #[Url] public string $sort             = 'created_at';
    #[Url] public string $dir              = 'desc';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function updateStatus(int $bookingId, string $newStatus): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($newStatus, $allowed, true)) return;

        Booking::findOrFail($bookingId)->update(['status' => $newStatus]);
        session()->flash('status', 'وضعیت رزرو به‌روز شد.');
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }

    private function toGregorian(?string $jalali): ?string
    {
        if (!$jalali) return null;
        try {
            $normalized = strtr(trim($jalali), [
                '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
                '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
                '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
            ]);
            return Jalalian::fromFormat('Y/m/d', $normalized)->toCarbon()->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function render()
    {
        $query = Booking::with('user', 'accommodation.city', 'roomType');

        if ($this->search) {
            $s = $this->search;
            $query->where(function ($w) use ($s) {
                $w->where('tracking_code', 'like', "%$s%")
                    ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%$s%")->orWhere('mobile', 'like', "%$s%"))
                    ->orWhereHas('accommodation', fn($q) => $q->where('name', 'like', "%$s%"));
            });
        }
        if ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->accommodationId) {
            $query->where('accommodation_id', $this->accommodationId);
        }
        if ($this->cityId) {
            $query->whereHas('accommodation', fn($q) => $q->where('city_id', $this->cityId));
        }
        if ($d = $this->toGregorian($this->checkInFrom)) {
            $query->whereDate('check_in', '>=', $d);
        }
        if ($d = $this->toGregorian($this->checkInTo)) {
            $query->whereDate('check_in', '<=', $d);
        }
        if ($d = $this->toGregorian($this->checkOutFrom)) {
            $query->whereDate('check_out', '>=', $d);
        }
        if ($d = $this->toGregorian($this->checkOutTo)) {
            $query->whereDate('check_out', '<=', $d);
        }
        if ($this->nightsMin) {
            $query->where('nights', '>=', (int) $this->nightsMin);
        }
        if ($this->nightsMax) {
            $query->where('nights', '<=', (int) $this->nightsMax);
        }
        if ($this->priceMin) {
            $query->where('total_price', '>=', (int) str_replace(',', '', $this->priceMin));
        }
        if ($this->priceMax) {
            $query->where('total_price', '<=', (int) str_replace(',', '', $this->priceMax));
        }
        if ($this->guestsMin) {
            $query->where('guests', '>=', (int) $this->guestsMin);
        }
        if ($this->hasDiscount) {
            $query->where('discount_percentage', '>', 0);
        }

        $sortable = ['id', 'check_in', 'check_out', 'nights', 'total_price', 'guests', 'created_at'];
        $sort     = in_array($this->sort, $sortable) ? $this->sort : 'created_at';
        $dir      = $this->dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $totalFiltered  = (clone $query)->sum('total_price');
        $countFiltered  = (clone $query)->count();
        $bookings       = $query->paginate(25);
        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        $cities         = City::orderBy('name')->get(['id', 'name']);

        return view('admin.bookings.index', compact(
            'bookings', 'accommodations', 'cities', 'totalFiltered', 'countFiltered', 'sort', 'dir'
        ));
    }
}
