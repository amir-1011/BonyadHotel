<?php

namespace App\Livewire\Host;

use App\Models\Booking;
use App\Services\HostDashboardDataService;
use App\Livewire\Concerns\AssertsHostPermissions;
use App\Livewire\Concerns\ManagesDashboardAccommodationFilter;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.host', ['title' => 'داشبورد میزبان', 'pageTitle' => 'داشبورد میزبان'])]
class Dashboard extends Component
{
    use ManagesDashboardAccommodationFilter;
    use AssertsHostPermissions;

    public function mount(): void
    {
        $this->bootDashboardAccommodationFilter();
    }

    protected function resolveDashboardAccommodationOptions(): array
    {
        return Auth::user()
            ->managedAccommodationOptions()
            ->map(fn ($acc) => ['id' => (int) $acc->id, 'name' => (string) $acc->name])
            ->values()
            ->all();
    }

    public function confirm(int $bookingId): void
    {
        $this->assertHostCan('dashboard', 'edit');
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->managesAccommodation($booking->accommodation_id), 403);
        abort_unless($booking->canEditBookingDetails(Auth::user()), 403, 'پس از پایان دوره رزرو امکان ویرایش وجود ندارد.');
        $booking->update(['status' => 'confirmed']);
        session()->flash('status', 'رزرو تأیید شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو تأیید شد.');
    }

    public function cancel(int $bookingId): void
    {
        $this->assertHostCan('dashboard', 'edit');
        $booking = Booking::findOrFail($bookingId);
        abort_unless(Auth::user()->managesAccommodation($booking->accommodation_id), 403);
        abort_unless($booking->canEditBookingDetails(Auth::user()), 403, 'پس از پایان دوره رزرو امکان ویرایش وجود ندارد.');

        if ($booking->status === 'confirmed' && $booking->canRequestCancellation()) {
            $this->redirect(route('host.bookings.show', $booking) . '?cancel=1');
            return;
        }

        abort_if($booking->status !== 'pending', 422);
        $booking->update(['status' => 'cancelled']);
        session()->flash('status', 'رزرو لغو شد.');
        $this->dispatch('toast', type: 'success', message: 'رزرو لغو شد.');
    }

    public function render()
    {
        $user = Auth::user();
        $effectiveIds = $this->effectiveDashboardAccommodationIds();
        $data = app(HostDashboardDataService::class)->build($user, $effectiveIds);

        return view('host.dashboard', array_merge($data, [
            'hostUser' => $user,
            'filterKey' => $this->dashboardAccommodationFilterKey(),
            'effectiveAccommodationIds' => $effectiveIds,
            'dashboardAccommodationOptions' => $this->dashboardAccommodationOptionList(),
        ]));
    }
}
