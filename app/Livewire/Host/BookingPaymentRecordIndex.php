<?php

namespace App\Livewire\Host;

use App\Livewire\Admin\BookingPaymentRecordIndex as AdminBookingPaymentRecordIndex;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;

#[Layout('layouts.host', ['title' => 'تراکنش‌های مالی', 'pageTitle' => 'تراکنش‌های مالی رزرو'])]
class BookingPaymentRecordIndex extends AdminBookingPaymentRecordIndex
{
    protected function filteredQuery(): Builder
    {
        $accommodationIds = auth()->user()->managedAccommodationIds();

        return parent::filteredQuery()
            ->whereHas('booking', fn ($b) => $b->whereIn('accommodation_id', $accommodationIds));
    }

    public function render()
    {
        $query = $this->filteredQuery();

        return view('host.booking-payment-records.index', [
            'records' => $query->paginate(25),
            'provinces' => \App\Models\Province::query()->orderBy('name')->get(),
            'terminals' => \App\Models\PosTerminal::query()->with('province')->orderBy('province_id')->orderBy('terminal_number')->get(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'panel' => 'host',
        ]);
    }
}
