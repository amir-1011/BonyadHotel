<?php

namespace App\Livewire;

use App\Models\Accommodation;
use App\Services\OccupancyCalendarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

class OccupancyCalendar extends Component
{
    public string $panel = 'admin';

    public ?int $calendarAccommodationId = null;

    public int $calendarYear;

    public int $calendarMonth;

    public function mount(string $panel = 'admin'): void
    {
        $this->panel = $panel;
        $now         = Jalalian::now();
        $this->calendarYear  = $now->getYear();
        $this->calendarMonth = $now->getMonth();
    }

    public function prevCalendarMonth(): void
    {
        if ($this->calendarMonth <= 1) {
            $this->calendarMonth = 12;
            $this->calendarYear--;
        } else {
            $this->calendarMonth--;
        }
    }

    public function nextCalendarMonth(): void
    {
        if ($this->calendarMonth >= 12) {
            $this->calendarMonth = 1;
            $this->calendarYear++;
        } else {
            $this->calendarMonth++;
        }
    }

    public function goToCalendarToday(): void
    {
        $now = Jalalian::now();
        $this->calendarYear  = $now->getYear();
        $this->calendarMonth = $now->getMonth();
    }

    public function updatedCalendarAccommodationId($value): void
    {
        if ($value === '' || $value === null) {
            $this->calendarAccommodationId = null;

            return;
        }

        $id = (int) $value;
        if ($this->panel === 'host') {
            abort_unless(Auth::user()->managesAccommodation($id), 403);
        }
        $this->calendarAccommodationId = $id;
    }

  protected function scopedAccommodationIds(): Collection
    {
        if ($this->panel === 'host') {
            $managed = Auth::user()->managedAccommodationIds();

            if ($this->calendarAccommodationId) {
                return $managed->contains($this->calendarAccommodationId)
                    ? collect([$this->calendarAccommodationId])
                    : collect();
            }

            return $managed;
        }

        if ($this->calendarAccommodationId) {
            return collect([$this->calendarAccommodationId]);
        }

        return Accommodation::query()->pluck('id');
    }

    public function render()
    {
        $occupancyCalendar = app(OccupancyCalendarService::class)->build(
            $this->scopedAccommodationIds(),
            $this->calendarYear,
            $this->calendarMonth,
        );

        $accommodations = $this->panel === 'host'
            ? Auth::user()->accommodations()->orderBy('name')->get(['id', 'name'])
            : Accommodation::orderBy('name')->get(['id', 'name']);

        $showFilter = $this->panel === 'admin'
            || Auth::user()->hasHostPanelAccess('accommodations');

        $bookingShowUrl = $this->panel === 'host'
            ? route('host.bookings.show', ['booking' => 999999])
            : route('admin.bookings.show', ['booking' => 999999]);

        return view('livewire.occupancy-calendar', compact(
            'occupancyCalendar', 'accommodations', 'showFilter', 'bookingShowUrl'
        ));
    }
}
