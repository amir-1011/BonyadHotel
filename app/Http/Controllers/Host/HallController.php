<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Concerns\SavesAccommodationHalls;
use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\Hall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HallController extends Controller
{
    use SavesAccommodationHalls;

    private function authorizeAccommodation(Accommodation $accommodation): void
    {
        abort_if(! $accommodation->isManagedBy(Auth::user()), 403);
    }

    public function index(Request $request, ?Accommodation $accommodation = null)
    {
        $managedIds = Auth::user()->managedAccommodationIds()->all();

        $query = Hall::query()
            ->with(['accommodation:id,name', 'hallType'])
            ->whereIn('accommodation_id', $managedIds ?: [0])
            ->ordered();

        if ($accommodation) {
            $this->authorizeAccommodation($accommodation);
            $query->where('accommodation_id', $accommodation->id);
        } elseif ($request->filled('accommodation_id')) {
            $filterId = (int) $request->input('accommodation_id');
            if (in_array($filterId, $managedIds, true)) {
                $query->where('accommodation_id', $filterId);
            }
        }

        $halls = $query->get();
        $accommodations = Auth::user()->managedAccommodationOptions();

        return view('host.halls.index', compact('halls', 'accommodation', 'accommodations'));
    }

    public function create(Accommodation $accommodation)
    {
        $this->authorizeAccommodation($accommodation);

        return view('host.halls.create', compact('accommodation'));
    }

    public function store(Request $request, Accommodation $accommodation)
    {
        $this->authorizeAccommodation($accommodation);
        $this->storeHall($request, $accommodation);

        return redirect()
            ->route('host.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن با موفقیت ثبت شد.');
    }

    public function edit(Accommodation $accommodation, Hall $hall)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        return view('host.halls.edit', compact('accommodation', 'hall'));
    }

    public function update(Request $request, Accommodation $accommodation, Hall $hall)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        $this->updateHall($request, $hall);

        return redirect()
            ->route('host.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن با موفقیت به‌روز شد.');
    }

    public function destroy(Accommodation $accommodation, Hall $hall)
    {
        $this->authorizeAccommodation($accommodation);
        abort_if($hall->accommodation_id !== $accommodation->id, 404);

        try {
            $this->destroyHall($hall);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('host.halls.accommodation.index', $accommodation)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('host.halls.accommodation.index', $accommodation)
            ->with('status', 'سالن حذف شد.');
    }
}
