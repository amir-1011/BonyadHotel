<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Accommodation;
use App\Models\AccommodationType;
use Illuminate\Validation\Rule;
use App\Models\Booking;
use App\Models\City;
use App\Models\Province;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

class AccommodationController extends Controller
{
    public function index(Request $request)
    {
        $query = Accommodation::with('city.province', 'host')->latest();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$s}%")
                ->orWhereHas('host', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('hosts', fn ($q) => $q->where('name', 'like', "%{$s}%"))
            );
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $accommodations = $query->paginate(20)->withQueryString();
        return view('admin.accommodations.index', compact('accommodations'));
    }

    public function create()
    {
        $provinces = Province::with('cities')->orderBy('name')->get();
        $hosts = User::role('host')->orderBy('name')->get();
        return view('admin.accommodations.create', compact('provinces', 'hosts'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));
        $data['images'] = [];

        if ($request->hasFile('images')) {
            $data['images'] = app(ImageUploadService::class)->storeManyWebp($request->file('images', []), 'accommodations');
        }

        $accommodation = Accommodation::create($data);

        if (!empty($data['host_id'])) {
            $accommodation->grantHostAccess(User::find($data['host_id']));
        }
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه با موفقیت ثبت شد.');
    }

    public function edit(Accommodation $accommodation)
    {
        $provinces = Province::with('cities')->orderBy('name')->get();
        $hosts = User::role('host')->orderBy('name')->get();
        return view('admin.accommodations.edit', compact('accommodation', 'provinces', 'hosts'));
    }

    public function update(Request $request, Accommodation $accommodation)
    {
        $data = $this->validated($request, $accommodation);
        $data['amenities'] = $this->parseAmenities($request->input('amenities_raw', ''));

        $existingImages = $accommodation->images ?? [];
        $keepImages = $request->input('keep_images', []);

        foreach ($existingImages as $img) {
            if (!in_array($img, $keepImages)) {
                Storage::disk('public')->delete($img);
            }
        }

        $finalImages = array_values(array_intersect($existingImages, $keepImages));

        if ($request->hasFile('new_images')) {
            $finalImages = array_merge(
                $finalImages,
                app(ImageUploadService::class)->storeManyWebp($request->file('new_images', []), 'accommodations')
            );
        }
        $data['images'] = $finalImages;

        $accommodation->update($data);

        if (!empty($data['host_id'])) {
            $accommodation->grantHostAccess(User::find($data['host_id']));
        }
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه ویرایش شد.');
    }

    public function destroy(Accommodation $accommodation)
    {
        $accommodation->delete();
        return redirect()->route('admin.accommodations.index')
            ->with('status', 'اقامتگاه حذف شد.');
    }

    public function toggleActive(Accommodation $accommodation)
    {
        $accommodation->update(['is_active' => !$accommodation->is_active]);
        return back()->with('status', $accommodation->is_active ? 'اقامتگاه فعال شد.' : 'اقامتگاه غیرفعال شد.');
    }

    private function validated(Request $request, ?Accommodation $accommodation = null): array
    {
        return $request->validate([
            'city_id'        => ['required', 'exists:cities,id'],
            'host_id'        => ['nullable', 'exists:users,id'],
            'name'           => ['required', 'string', 'max:200'],
            'description'    => ['nullable', 'string'],
            'type'           => ['required', Rule::exists('accommodation_types', 'key')],
            'price_per_night'=> ['required', 'integer', 'min:0'],
            'capacity'       => ['required', 'integer', 'min:1'],
            'rooms'          => ['required', 'integer', 'min:1'],
            'address'        => ['nullable', 'string'],
            'lat'            => ['nullable', 'numeric'],
            'lng'            => ['nullable', 'numeric'],
            'image'          => ['nullable', 'string'],
            'is_active'      => ['boolean'],
            'images.*'       => ['nullable', 'image', 'max:4096'],
            'new_images.*'   => ['nullable', 'image', 'max:4096'],
        ]);
    }

    public function salesReport(Accommodation $accommodation)
    {
        $accommodation->load('city.province', 'host', 'roomTypes');

        $driver  = DB::getDriverName();
        $dayExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM-DD')",
            default  => "DATE(created_at)",
        };
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };

        // Last 30 days — fill every date slot (including zero-days)
        $rawDaily = Booking::where('accommodation_id', $accommodation->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->selectRaw("{$dayExpr} as day, SUM(total_price) as total, COUNT(*) as count")
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $dailyRevenue = [];
        for ($i = 29; $i >= 0; $i--) {
            $carbon = now()->subDays($i);
            $d = $carbon->format('Y-m-d');
            $jalaliDay = Jalalian::fromCarbon($carbon)->format('Y/m/d');
            $dailyRevenue[] = [
                'day'   => $jalaliDay,
                'total' => (float) ($rawDaily[$d]->total ?? 0),
                'count' => (int)   ($rawDaily[$d]->count ?? 0),
            ];
        }

        // Last 12 months
        $rawMonthly = Booking::where('accommodation_id', $accommodation->id)
            ->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$monthExpr} as month, SUM(total_price) as total, COUNT(*) as count")
            ->groupBy('month')->orderBy('month')->get()->keyBy('month');

        $monthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $carbon = now()->subMonths($i);
            $m = $carbon->format('Y-m');
            $jalaliMonth = Jalalian::fromCarbon($carbon)->format('Y/m');
            $monthlyRevenue[] = [
                'month' => $jalaliMonth,
                'total' => (float) ($rawMonthly[$m]->total ?? 0),
                'count' => (int)   ($rawMonthly[$m]->count ?? 0),
            ];
        }

        // Booking status breakdown
        $statusBreakdown = Booking::where('accommodation_id', $accommodation->id)
            ->selectRaw('status, COUNT(*) as count, SUM(total_price) as total')
            ->groupBy('status')->get();

        // KPIs
        $today     = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->whereDate('created_at', today())->sum('total_price');
        $thisWeek  = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->where('created_at', '>=', now()->startOfWeek())->sum('total_price');
        $thisMonth = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->where('created_at', '>=', now()->startOfMonth())->sum('total_price');
        $lastMonth = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')
            ->where('created_at', '>=', now()->subMonth()->startOfMonth())
            ->where('created_at', '<',  now()->startOfMonth())
            ->sum('total_price');
        $totalRevenue    = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->sum('total_price');
        $totalBookings   = Booking::where('accommodation_id', $accommodation->id)->count();
        $totalConfirmed  = Booking::where('accommodation_id', $accommodation->id)->where('status', 'confirmed')->count();
        $totalPending    = Booking::where('accommodation_id', $accommodation->id)->where('status', 'pending')->count();
        $totalCancelled  = Booking::where('accommodation_id', $accommodation->id)->where('status', 'cancelled')->count();
        $growthRate      = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : null;
        $avgRevPerBooking = $totalConfirmed > 0 ? round($totalRevenue / $totalConfirmed) : 0;

        // Room-type breakdown
        $roomTypeBreakdown = Booking::where('bookings.accommodation_id', $accommodation->id)
            ->where('bookings.status', 'confirmed')
            ->join('room_types', 'bookings.room_type_id', '=', 'room_types.id')
            ->selectRaw('room_types.name as rt_name, COUNT(bookings.id) as count, SUM(bookings.total_price) as total')
            ->groupBy('room_types.name')->orderByDesc('total')->get();

        // Recent bookings
        $recentBookings = Booking::where('accommodation_id', $accommodation->id)
            ->with('user', 'roomType')
            ->latest()->limit(20)->get();

        // Reviews
        $avgRating   = $accommodation->averageRating();
        $reviewCount = $accommodation->reviewCount();

        return view('admin.accommodations.report', compact(
            'accommodation', 'dailyRevenue', 'monthlyRevenue', 'statusBreakdown',
            'today', 'thisWeek', 'thisMonth', 'lastMonth', 'growthRate',
            'totalRevenue', 'totalBookings', 'totalConfirmed', 'totalPending', 'totalCancelled',
            'avgRevPerBooking', 'roomTypeBreakdown', 'recentBookings', 'avgRating', 'reviewCount'
        ));
    }

    private function parseAmenities(string $raw): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }
}
