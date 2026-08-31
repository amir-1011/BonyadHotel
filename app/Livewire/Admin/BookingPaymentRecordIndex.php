<?php

namespace App\Livewire\Admin;

use App\Models\BookingPaymentRecord;
use App\Models\PosTerminal;
use App\Models\Province;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'تراکنش‌های مالی رزرو', 'pageTitle' => 'تراکنش‌های مالی رزرو'])]
class BookingPaymentRecordIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'province')]
    public string $provinceFilter = '';

    #[Url(as: 'terminal')]
    public string $terminalFilter = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    public string $draftSearch = '';

    public string $draftProvinceFilter = '';

    public string $draftTerminalFilter = '';

    public string $draftDateFrom = '';

    public string $draftDateTo = '';

    public function mount(): void
    {
        $this->syncDraftFromApplied();
    }

    public function applyFilters(): void
    {
        $this->search = $this->draftSearch;
        $this->provinceFilter = $this->draftProvinceFilter;
        $this->terminalFilter = $this->draftTerminalFilter;
        $this->dateFrom = $this->draftDateFrom;
        $this->dateTo = $this->draftDateTo;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'provinceFilter', 'terminalFilter', 'dateFrom', 'dateTo',
        ]);
        $this->syncDraftFromApplied();
        $this->resetPage();
    }

    protected function syncDraftFromApplied(): void
    {
        $this->draftSearch = $this->search;
        $this->draftProvinceFilter = $this->provinceFilter;
        $this->draftTerminalFilter = $this->terminalFilter;
        $this->draftDateFrom = $this->dateFrom;
        $this->draftDateTo = $this->dateTo;
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->provinceFilter !== ''
            || $this->terminalFilter !== ''
            || $this->dateFrom !== ''
            || $this->dateTo !== '';
    }

    protected function filteredQuery(): Builder
    {
        $query = BookingPaymentRecord::query()
            ->with([
                'booking.accommodation.city.province',
                'posTerminal.province',
                'recordedBy',
            ])
            ->orderByDesc('payment_at')
            ->orderByDesc('id');

        if ($this->search !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('transaction_tracking', 'like', $term)
                    ->orWhere('card_last_four', 'like', $term)
                    ->orWhereHas('booking', fn ($b) => $b->where('tracking_code', 'like', $term));
            });
        }

        if ($this->provinceFilter !== '') {
            $provinceId = (int) $this->provinceFilter;
            $query->where(function ($q) use ($provinceId) {
                $q->whereHas('posTerminal', fn ($t) => $t->where('province_id', $provinceId))
                    ->orWhereHas('booking.accommodation', function ($a) use ($provinceId) {
                        $a->whereHas('city', fn ($c) => $c->where('province_id', $provinceId))
                            ->orWhereHas('county', fn ($co) => $co->where('province_id', $provinceId));
                    });
            });
        }

        if ($this->terminalFilter !== '') {
            $query->where('pos_terminal_id', (int) $this->terminalFilter);
        }

        if ($this->dateFrom !== '') {
            $gregorian = \App\Support\JalaliDateTimeInput::toGregorianDate($this->dateFrom);
            if ($gregorian) {
                $query->whereDate('payment_at', '>=', $gregorian);
            }
        }

        if ($this->dateTo !== '') {
            $gregorian = \App\Support\JalaliDateTimeInput::toGregorianDate($this->dateTo);
            if ($gregorian) {
                $query->whereDate('payment_at', '<=', $gregorian);
            }
        }

        return $query;
    }

    public function render()
    {
        $query = $this->filteredQuery();

        return view('admin.booking-payment-records.index', [
            'records' => $query->paginate(25),
            'provinces' => Province::query()->orderBy('name')->get(),
            'terminals' => PosTerminal::query()->with('province')->orderBy('province_id')->orderBy('terminal_number')->get(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'panel' => 'admin',
        ]);
    }
}
