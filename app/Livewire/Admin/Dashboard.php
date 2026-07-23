<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\Booking;
use App\Services\AdminDashboardDataService;
use App\Livewire\Concerns\ManagesDashboardAccommodationFilter;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'داشبورد مدیریت', 'pageTitle' => 'داشبورد فروش'])]
class Dashboard extends Component
{
    use ManagesDashboardAccommodationFilter;

    public function mount(): void
    {
        $this->bootDashboardAccommodationFilter();
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Accommodation::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Accommodation $acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    public function render(AdminDashboardDataService $dataService)
    {
        $effectiveIds = $this->effectiveDashboardAccommodationIds();
        $data = $dataService->build($effectiveIds);

        return view('admin.dashboard', array_merge($data, [
            'filterKey' => $this->dashboardAccommodationFilterKey(),
            'effectiveAccommodationIds' => $effectiveIds,
            'dashboardAccommodationOptions' => $this->dashboardAccommodationOptionList(),
        ]));
    }

    public function updateBookingStatus(int $bookingId, string $status): void
    {
        $allowed = ['pending', 'confirmed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return;
        }

        $booking = Booking::findOrFail($bookingId);

        if (!in_array($booking->accommodation_id, $this->effectiveDashboardAccommodationIds(), true)) {
            abort(403);
        }

        if ($status === 'cancelled' && $booking->status === 'confirmed' && $booking->canRequestCancellation()) {
            $this->redirect(route('admin.bookings.show', $booking) . '?cancel=1');
            return;
        }

        $booking->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: 'وضعیت رزرو به‌روز شد.');
    }
}
