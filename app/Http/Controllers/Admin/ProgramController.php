<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\Accommodation;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    /* ── لیست همه برنامه‌ها ─────────────────────────────────────────────── */
    public function index(Request $request)
    {
        $query = Program::with('accommodation')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('is_supportive_service')) {
            $query->where('is_supportive_service', (bool) $request->is_supportive_service);
        }
        if ($request->filled('accommodation_id')) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        $programs         = $query->paginate(25)->withQueryString();
        $accommodations   = Accommodation::orderBy('name')->get(['id', 'name']);

        return view('admin.programs.index', compact('programs', 'accommodations'));
    }

    /* ── نمایش برنامه ───────────────────────────────────────────────────── */
    public function show(Program $program)
    {
        $program->load('accommodation', 'roomTypes');
        return view('admin.programs.show', compact('program'));
    }

    /* ── تغییر وضعیت ────────────────────────────────────────────────────── */
    public function updateStatus(Request $request, Program $program)
    {
        $data = $request->validate(['status' => 'required|in:active,completed,cancelled']);
        $program->update($data);
        return back()->with('status', 'وضعیت برنامه به‌روز شد.');
    }

    /* ── گزارش خدمات حمایتی (ادمین) ─────────────────────────────────────── */
    public function supportiveReport(Request $request)
    {
        $year = $request->get('year', \Morilog\Jalali\Jalalian::now()->getYear());

        $jalaliStart = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', $year . '-01-01');
        $jalaliEnd   = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', $year . '-12-29');
        $startDate   = $jalaliStart->toCarbon()->format('Y-m-d');
        $endDate     = $jalaliEnd->toCarbon()->addDay()->format('Y-m-d');

        $programs = Program::where('is_supportive_service', true)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with('accommodation')
            ->latest('start_date')
            ->get();

        $totalDiscount  = $programs->sum('discount_amount');
        $totalGuests    = $programs->sum('guest_count');
        $totalPrograms  = $programs->count();

        $byType = $programs->groupBy(fn($p) => $p->supportive_service_type ?: 'نامشخص')
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

        $jalaliYears = range(\Morilog\Jalali\Jalalian::now()->getYear(), \Morilog\Jalali\Jalalian::now()->getYear() - 5, -1);

        return view('admin.programs.supportive-report', compact(
            'programs', 'totalDiscount', 'totalGuests', 'totalPrograms',
            'byType', 'byAccommodation', 'year', 'jalaliYears'
        ));
    }
}
