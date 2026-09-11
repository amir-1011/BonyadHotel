<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SavesAccommodationHalls;
use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Hall;
use Illuminate\Http\Request;

class HallController extends Controller
{
    use SavesAccommodationHalls;

    public function index(Request $request, ?Accommodation $accommodation = null)
    {
        $query = Hall::query()
            ->with(['accommodation:id,name', 'hallType'])
            ->ordered();

        if ($accommodation) {
            $query->where('accommodation_id', $accommodation->id);
        } elseif ($request->filled('accommodation_id')) {
            $query->where('accommodation_id', (int) $request->input('accommodation_id'));
        }

        $halls = $query->get();
        $accommodations = Accommodation::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.halls.index', compact('halls', 'accommodation', 'accommodations'));
    }

    public function create(Accommodation $accommodation)
    {
        return view('admin.halls.create', compact('accommodation'));
    }

    public function store(Request $request, Accommodation $accommodation)
    {
        $this->storeHall($request, $accommodation);

        return redirect()
            ->route('admin.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن با موفقیت ثبت شد.');
    }

    public function edit(Accommodation $accommodation, Hall $hall)
    {
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        return view('admin.halls.edit', compact('accommodation', 'hall'));
    }

    public function update(Request $request, Accommodation $accommodation, Hall $hall)
    {
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        $this->updateHall($request, $hall);

        return redirect()
            ->route('admin.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن با موفقیت به‌روز شد.');
    }

    public function destroy(Accommodation $accommodation, Hall $hall)
    {
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        try {
            $this->destroyHall($hall);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.halls.accommodation.index', $accommodation)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن حذف شد.');
    }
}
