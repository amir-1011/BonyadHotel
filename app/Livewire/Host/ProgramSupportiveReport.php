<?php

namespace App\Livewire\Host;

use App\Models\Program;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

#[Layout('layouts.host', ['title' => 'گزارش خدمات حمایتی', 'pageTitle' => 'گزارش خدمات حمایتی'])]
class ProgramSupportiveReport extends Component
{
    #[Url] public int $year = 0;

    public function mount(): void
    {
        if (!$this->year) {
            $this->year = Jalalian::now()->getYear();
        }
    }

    public function render()
    {
        $accIds = Auth::user()->managedAccommodationIds();

        // Convert Jalali year to Gregorian range
        $jalaliStart = Jalalian::fromFormat('Y-m-d', $this->year . '-01-01');
        $jalaliEnd   = Jalalian::fromFormat('Y-m-d', $this->year . '-12-29');
        $startDate   = $jalaliStart->toCarbon()->format('Y-m-d');
        $endDate     = $jalaliEnd->toCarbon()->addDay()->format('Y-m-d');

        $programs = Program::whereIn('accommodation_id', $accIds)
            ->where('payment_type', Program::PAYMENT_SUPPORTIVE)
            ->where('status', '!=', Program::STATUS_CANCELLED)
            ->whereHas('booking', fn ($q) => $q->whereBetween('check_in', [$startDate, $endDate]))
            ->with('accommodation')
            ->latest('start_date')
            ->get();

        $totalDiscount  = $programs->sum('discount_amount');
        $totalGuests    = $programs->sum('guest_count');
        $totalPrograms  = $programs->count();

        $byType = $programs->groupBy(fn ($p) => $p->programTypeLabel())
            ->map(fn($g) => [
                'count'    => $g->count(),
                'guests'   => $g->sum('guest_count'),
                'discount' => $g->sum('discount_amount'),
            ]);

        $jalaliYears = range(Jalalian::now()->getYear(), Jalalian::now()->getYear() - 5, -1);
        $year        = $this->year;

        return view('host.programs.supportive-report', compact(
            'programs', 'totalDiscount', 'totalGuests', 'totalPrograms',
            'byType', 'year', 'jalaliYears'
        ));
    }
}
