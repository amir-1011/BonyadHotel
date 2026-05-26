<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    private function myAccommodationIds()
    {
        return Auth::user()->accommodations()->pluck('id');
    }

    /* ── لیست برنامه‌ها ─────────────────────────────────────────────────── */
    public function index(Request $request)
    {
        $accIds = $this->myAccommodationIds();

        $query = Program::whereIn('accommodation_id', $accIds)
            ->with('accommodation')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('is_supportive_service')) {
            $query->where('is_supportive_service', (bool) $request->is_supportive_service);
        }
        if ($request->filled('accommodation_id') && $accIds->contains($request->accommodation_id)) {
            $query->where('accommodation_id', $request->accommodation_id);
        }

        $programs = $query->paginate(20)->withQueryString();
        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);

        return view('host.programs.index', compact('programs', 'myAccommodations'));
    }

    /* ── فرم ایجاد ─────────────────────────────────────────────────────── */
    public function create(Request $request)
    {
        $accIds = $this->myAccommodationIds();
        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);

        // اتاق‌های اقامتگاه انتخابی
        $roomTypes = collect();
        $selectedAccId = $request->get('accommodation_id', $myAccommodations->first()?->id);
        if ($selectedAccId && $accIds->contains($selectedAccId)) {
            $roomTypes = RoomType::where('accommodation_id', $selectedAccId)
                ->where('is_active', true)->get(['id', 'name', 'room_count']);
        }

        return view('host.programs.create', compact('myAccommodations', 'roomTypes', 'selectedAccId'));
    }

    /* ── ذخیره برنامه جدید ─────────────────────────────────────────────── */
    public function store(Request $request)
    {
        $accIds = $this->myAccommodationIds();

        $data = $request->validate([
            'accommodation_id'        => ['required', 'integer', function($a,$v,$f){ if(!$this->myAccommodationIds()->contains($v)) $f('اقامتگاه مجاز نیست'); }],
            'title'                   => 'required|string|max:200',
            'description'             => 'nullable|string|max:2000',
            'program_type'            => 'required|in:camp,event,other',
            'start_date'              => 'required|date',
            'end_date'                => 'required|date|after_or_equal:start_date',
            'rooms_allocated'         => 'required|integer|min:1',
            'guest_count'             => 'required|integer|min:1',
            'employer'                => 'nullable|string|max:200',
            'contractor'              => 'nullable|string|max:200',
            'total_amount'            => 'required|integer|min:0',
            'deposit_amount'          => 'nullable|integer|min:0',
            'discount_amount'         => 'nullable|integer|min:0',
            'discount_percentage'     => 'nullable|integer|min:0|max:100',
            'is_supportive_service'   => 'nullable|boolean',
            'supportive_service_type' => 'nullable|string|max:200',
            'notes'                   => 'nullable|string|max:3000',
            'room_types'              => 'nullable|array',
            'room_types.*.id'         => 'integer|exists:room_types,id',
            'room_types.*.count'      => 'integer|min:1',
        ]);

        $program = Program::create([
            'accommodation_id'        => $data['accommodation_id'],
            'title'                   => $data['title'],
            'description'             => $data['description'] ?? null,
            'program_type'            => $data['program_type'],
            'start_date'              => $data['start_date'],
            'end_date'                => $data['end_date'],
            'rooms_allocated'         => $data['rooms_allocated'],
            'guest_count'             => $data['guest_count'],
            'employer'                => $data['employer'] ?? null,
            'contractor'              => $data['contractor'] ?? null,
            'total_amount'            => $data['total_amount'],
            'deposit_amount'          => $data['deposit_amount'] ?? 0,
            'discount_amount'         => $data['discount_amount'] ?? 0,
            'discount_percentage'     => $data['discount_percentage'] ?? 0,
            'is_supportive_service'   => !empty($data['is_supportive_service']),
            'supportive_service_type' => $data['supportive_service_type'] ?? null,
            'notes'                   => $data['notes'] ?? null,
            'status'                  => 'active',
        ]);

        // اتاق‌های مرتبط
        if (!empty($data['room_types'])) {
            $sync = [];
            foreach ($data['room_types'] as $rt) {
                $sync[$rt['id']] = ['rooms_count' => $rt['count'] ?? 1];
            }
            $program->roomTypes()->sync($sync);
        }

        return redirect()->route('host.programs.show', $program)
            ->with('status', 'برنامه «' . $program->title . '» با موفقیت ثبت شد.');
    }

    /* ── نمایش یک برنامه ────────────────────────────────────────────────── */
    public function show(Program $program)
    {
        abort_if(!$this->myAccommodationIds()->contains($program->accommodation_id), 403);
        $program->load('accommodation', 'roomTypes');
        return view('host.programs.show', compact('program'));
    }

    /* ── فرم ویرایش ─────────────────────────────────────────────────────── */
    public function edit(Program $program)
    {
        $accIds = $this->myAccommodationIds();
        abort_if(!$accIds->contains($program->accommodation_id), 403);

        $myAccommodations = Auth::user()->accommodations()->orderBy('name')->get(['id', 'name']);
        $roomTypes = RoomType::where('accommodation_id', $program->accommodation_id)
            ->where('is_active', true)->get(['id', 'name', 'room_count']);

        $selectedRoomTypes = $program->roomTypes()->get()->keyBy('id');

        return view('host.programs.edit', compact('program', 'myAccommodations', 'roomTypes', 'selectedRoomTypes'));
    }

    /* ── به‌روزرسانی ─────────────────────────────────────────────────────── */
    public function update(Request $request, Program $program)
    {
        abort_if(!$this->myAccommodationIds()->contains($program->accommodation_id), 403);

        $data = $request->validate([
            'title'                   => 'required|string|max:200',
            'description'             => 'nullable|string|max:2000',
            'program_type'            => 'required|in:camp,event,other',
            'start_date'              => 'required|date',
            'end_date'                => 'required|date|after_or_equal:start_date',
            'rooms_allocated'         => 'required|integer|min:1',
            'guest_count'             => 'required|integer|min:1',
            'employer'                => 'nullable|string|max:200',
            'contractor'              => 'nullable|string|max:200',
            'total_amount'            => 'required|integer|min:0',
            'deposit_amount'          => 'nullable|integer|min:0',
            'discount_amount'         => 'nullable|integer|min:0',
            'discount_percentage'     => 'nullable|integer|min:0|max:100',
            'is_supportive_service'   => 'nullable|boolean',
            'supportive_service_type' => 'nullable|string|max:200',
            'notes'                   => 'nullable|string|max:3000',
            'status'                  => 'required|in:active,completed,cancelled',
            'room_types'              => 'nullable|array',
            'room_types.*.id'         => 'integer|exists:room_types,id',
            'room_types.*.count'      => 'integer|min:1',
        ]);

        $program->update([
            'title'                   => $data['title'],
            'description'             => $data['description'] ?? null,
            'program_type'            => $data['program_type'],
            'start_date'              => $data['start_date'],
            'end_date'                => $data['end_date'],
            'rooms_allocated'         => $data['rooms_allocated'],
            'guest_count'             => $data['guest_count'],
            'employer'                => $data['employer'] ?? null,
            'contractor'              => $data['contractor'] ?? null,
            'total_amount'            => $data['total_amount'],
            'deposit_amount'          => $data['deposit_amount'] ?? 0,
            'discount_amount'         => $data['discount_amount'] ?? 0,
            'discount_percentage'     => $data['discount_percentage'] ?? 0,
            'is_supportive_service'   => !empty($data['is_supportive_service']),
            'supportive_service_type' => $data['supportive_service_type'] ?? null,
            'notes'                   => $data['notes'] ?? null,
            'status'                  => $data['status'],
        ]);

        if (!empty($data['room_types'])) {
            $sync = [];
            foreach ($data['room_types'] as $rt) {
                $sync[$rt['id']] = ['rooms_count' => $rt['count'] ?? 1];
            }
            $program->roomTypes()->sync($sync);
        } else {
            $program->roomTypes()->detach();
        }

        return redirect()->route('host.programs.show', $program)
            ->with('status', 'برنامه با موفقیت به‌روزرسانی شد.');
    }

    /* ── حذف ────────────────────────────────────────────────────────────── */
    public function destroy(Program $program)
    {
        abort_if(!$this->myAccommodationIds()->contains($program->accommodation_id), 403);
        $program->delete();
        return redirect()->route('host.programs.index')->with('status', 'برنامه حذف شد.');
    }

    /* ── گزارش خدمات حمایتی ─────────────────────────────────────────────── */
    public function supportiveReport(Request $request)
    {
        $accIds = $this->myAccommodationIds();

        $year = $request->get('year', \Morilog\Jalali\Jalalian::now()->getYear());

        // تبدیل سال شمسی به بازه میلادی
        $jalaliStart = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', $year . '-01-01');
        $jalaliEnd   = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', $year . '-12-29');
        $startDate = $jalaliStart->toCarbon()->format('Y-m-d');
        $endDate   = $jalaliEnd->toCarbon()->addDay()->format('Y-m-d');

        $query = Program::whereIn('accommodation_id', $accIds)
            ->where('is_supportive_service', true)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with('accommodation')
            ->latest('start_date');

        $programs = $query->get();

        $totalDiscount    = $programs->sum('discount_amount');
        $totalGuests      = $programs->sum('guest_count');
        $totalPrograms    = $programs->count();

        // گروه‌بندی بر اساس نوع خدمت حمایتی
        $byType = $programs->groupBy(fn($p) => $p->supportive_service_type ?: 'نامشخص')
            ->map(fn($g) => [
                'count'    => $g->count(),
                'guests'   => $g->sum('guest_count'),
                'discount' => $g->sum('discount_amount'),
            ]);

        $jalaliYears = range(\Morilog\Jalali\Jalalian::now()->getYear(), \Morilog\Jalali\Jalalian::now()->getYear() - 5, -1);

        return view('host.programs.supportive-report', compact(
            'programs', 'totalDiscount', 'totalGuests', 'totalPrograms',
            'byType', 'year', 'jalaliYears'
        ));
    }
}
