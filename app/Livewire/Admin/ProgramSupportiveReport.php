<?php

namespace App\Livewire\Admin;

use App\Models\Program;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Morilog\Jalali\Jalalian;

#[Layout('layouts.admin', ['title' => 'گزارش خدمات حمایتی', 'pageTitle' => 'گزارش خدمات حمایتی'])]
class ProgramSupportiveReport extends Component
{
    #[Url]
    public int $year = 0;

    public function mount(): void
    {
        if ($this->year === 0) {
            $this->year = Jalalian::now()->getYear();
        }
    }

    public function render()
    {
        $jalaliStart = Jalalian::fromFormat('Y-m-d', $this->year . '-01-01');
        $jalaliEnd   = Jalalian::fromFormat('Y-m-d', $this->year . '-12-29');
        $startDate   = $jalaliStart->toCarbon()->format('Y-m-d');
        $endDate     = $jalaliEnd->toCarbon()->addDay()->format('Y-m-d');

        $programs = Program::where('payment_type', Program::PAYMENT_SUPPORTIVE)
            ->where('status', '!=', Program::STATUS_CANCELLED)
            ->whereHas('booking', fn ($q) => $q->whereBetween('check_in', [$startDate, $endDate]))
            ->with('accommodation')
            ->latest('id')
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

        $byAccommodation = $programs->groupBy(fn($p) => $p->accommodation->name ?? 'نامشخص')
            ->map(fn($g) => [
                'count'    => $g->count(),
                'guests'   => $g->sum('guest_count'),
                'discount' => $g->sum('discount_amount'),
            ]);

        $jalaliYears = range(Jalalian::now()->getYear(), Jalalian::now()->getYear() - 5, -1);

        $year = $this->year;

        return view('admin.programs.supportive-report', compact(
            'programs', 'totalDiscount', 'totalGuests', 'totalPrograms',
            'byType', 'byAccommodation', 'year', 'jalaliYears'
        ));
    }
}
