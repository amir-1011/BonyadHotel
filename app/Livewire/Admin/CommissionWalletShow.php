<?php

namespace App\Livewire\Admin;

use App\Models\PlatformCommissionEntry;
use App\Services\PlatformCommissionService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin', ['title' => 'جزئیات کارمزد', 'pageTitle' => 'جزئیات تراکنش کارمزد'])]
class CommissionWalletShow extends Component
{
    public PlatformCommissionEntry $entry;

    public function mount(PlatformCommissionEntry $entry): void
    {
        $this->entry = $entry->load([
            'booking.user',
            'booking.accommodation.city.province',
            'booking.roomType',
            'booking.roomRate',
            'booking.services.serviceCatalog',
            'booking.guestDetails',
            'booking.createdBy',
            'accommodation.city.province',
            'serviceCatalog',
            'createdBy',
        ]);
    }

    public function render(PlatformCommissionService $commission)
    {
        $history = collect();
        $categoryNet = 0;
        $currentTarget = null;
        $allCategoryTargets = [];

        if ($this->entry->booking_id) {
            $history = PlatformCommissionEntry::query()
                ->where('booking_id', $this->entry->booking_id)
                ->where('category_key', $this->entry->category_key)
                ->with('createdBy')
                ->orderBy('id')
                ->get();

            $categoryNet = (int) $history->sum('commission_amount');

            if ($this->entry->booking && $this->entry->booking->status === 'confirmed') {
                $allCategoryTargets = $commission->buildCommissionTargets($this->entry->booking);
                $currentTarget = $allCategoryTargets[$this->entry->category_key] ?? null;
            }
        }

        $bookingCommissionNet = $this->entry->booking_id
            ? (int) PlatformCommissionEntry::query()->where('booking_id', $this->entry->booking_id)->sum('commission_amount')
            : 0;

        $categoryNets = [];
        if ($this->entry->booking_id && $allCategoryTargets !== []) {
            $categoryNets = PlatformCommissionEntry::query()
                ->where('booking_id', $this->entry->booking_id)
                ->selectRaw('category_key, SUM(commission_amount) as net')
                ->groupBy('category_key')
                ->pluck('net', 'category_key')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return view('admin.commission-wallet.show', compact(
            'history',
            'categoryNet',
            'currentTarget',
            'allCategoryTargets',
            'bookingCommissionNet',
            'categoryNets',
        ));
    }
}
