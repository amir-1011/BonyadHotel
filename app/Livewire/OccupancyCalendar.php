<?php

namespace App\Livewire;

use App\Models\Accommodation;
use App\Services\OccupancyCalendarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Defer;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

/**
 * Deferred: building the monthly occupancy grid loops over every booking per day,
 * so it is excluded from the initial dashboard response and loaded asynchronously
 * right after first paint, keeping the page's first response fast.
 */
#[Defer]
class OccupancyCalendar extends Component
{
    public string $panel = 'admin';

    public ?int $calendarAccommodationId = null;

    public int $calendarYear;

    public int $calendarMonth;

    /** @var array<int> */
    public array $dashboardAccommodationIds = [];

    public bool $useDashboardFilter = false;

    public function mount(string $panel = 'admin', array $dashboardAccommodationIds = [], bool $useDashboardFilter = false): void
    {
        $this->panel = $panel;
        $this->dashboardAccommodationIds = array_values(array_map('intval', $dashboardAccommodationIds));
        $this->useDashboardFilter = $useDashboardFilter;
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
        if ($this->useDashboardFilter) {
            if ($this->panel === 'host') {
                $managed = Auth::user()->managedAccommodationIds()->map(fn ($id) => (int) $id);

                return collect(array_values(array_intersect($this->dashboardAccommodationIds, $managed->all())));
            }

            return collect($this->dashboardAccommodationIds);
        }

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

    public function placeholder(array $params = []): string
    {
        return <<<'HTML'
            <div class="ta-card h-100">
                <div class="ta-card__head">
                    <h2 class="ta-card__title mb-0"><i class="bi bi-calendar3 me-2"></i>تقویم اشغال</h2>
                </div>
                <div class="ta-card__body d-flex align-items-center justify-content-center" style="min-height:220px">
                    <div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem;">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
            HTML;
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

        $showFilter = !$this->useDashboardFilter && (
            $this->panel === 'admin'
            || Auth::user()->hasHostPanelAccess('accommodations')
        );

        $bookingShowUrl = $this->panel === 'host'
            ? route('host.bookings.show', ['booking' => 999999])
            : route('admin.bookings.show', ['booking' => 999999]);

        return view('livewire.occupancy-calendar', compact(
            'occupancyCalendar', 'accommodations', 'showFilter', 'bookingShowUrl'
        ));
    }
}
