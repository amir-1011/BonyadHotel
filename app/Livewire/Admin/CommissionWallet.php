<?php

namespace App\Livewire\Admin;

use App\Models\Accommodation;
use App\Models\City;
use App\Models\PlatformCommissionEntry;
use App\Models\ServiceCatalog;
use App\Services\PlatformCommissionService;
use App\Support\PlatformCommissionEntryFilter;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin', ['title' => 'کیف پول کارمزد', 'pageTitle' => 'کیف پول کارمزد خدمات'])]
class CommissionWallet extends Component
{
    use WithPagination;

    // ── Applied filters (URL + query) ─────────────────────────────────────
    #[Url(as: 'search')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $category = '';

    #[Url(as: 'entry_type')]
    public string $entryType = '';

    #[Url(as: 'reason')]
    public string $reason = '';

    #[Url(as: 'accommodation_id')]
    public string $accommodationId = '';

    #[Url(as: 'city_id')]
    public string $cityId = '';

    #[Url(as: 'service_catalog_id')]
    public string $serviceCatalogId = '';

    #[Url(as: 'booking_source')]
    public string $bookingSource = '';

    #[Url(as: 'booking_status')]
    public string $bookingStatus = '';

    #[Url(as: 'sign')]
    public string $sign = '';

    #[Url(as: 'date_from')]
    public string $dateFrom = '';

    #[Url(as: 'date_to')]
    public string $dateTo = '';

    #[Url(as: 'commission_min')]
    public string $commissionMin = '';

    #[Url(as: 'commission_max')]
    public string $commissionMax = '';

    #[Url(as: 'transaction_min')]
    public string $transactionMin = '';

    #[Url(as: 'transaction_max')]
    public string $transactionMax = '';

    #[Url(as: 'sort')]
    public string $sort = 'id';

    #[Url(as: 'dir')]
    public string $dir = 'desc';

    // ── Draft filters (form inputs, applied on button click) ──────────────
    public string $draftSearch = '';

    public string $draftCategory = '';

    public string $draftEntryType = '';

    public string $draftReason = '';

    public string $draftAccommodationId = '';

    public string $draftCityId = '';

    public string $draftServiceCatalogId = '';

    public string $draftBookingSource = '';

    public string $draftBookingStatus = '';

    public string $draftSign = '';

    public string $draftDateFrom = '';

    public string $draftDateTo = '';

    public string $draftCommissionMin = '';

    public string $draftCommissionMax = '';

    public string $draftTransactionMin = '';

    public string $draftTransactionMax = '';

    public function mount(): void
    {
        $this->syncDraftFromApplied();
    }

    public function applyFilters(): void
    {
        $this->search = $this->draftSearch;
        $this->category = $this->draftCategory;
        $this->entryType = $this->draftEntryType;
        $this->reason = $this->draftReason;
        $this->accommodationId = $this->draftAccommodationId;
        $this->cityId = $this->draftCityId;
        $this->serviceCatalogId = $this->draftServiceCatalogId;
        $this->bookingSource = $this->draftBookingSource;
        $this->bookingStatus = $this->draftBookingStatus;
        $this->sign = $this->draftSign;
        $this->dateFrom = $this->draftDateFrom;
        $this->dateTo = $this->draftDateTo;
        $this->commissionMin = $this->draftCommissionMin;
        $this->commissionMax = $this->draftCommissionMax;
        $this->transactionMin = $this->draftTransactionMin;
        $this->transactionMax = $this->draftTransactionMax;

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'category', 'entryType', 'reason',
            'accommodationId', 'cityId', 'serviceCatalogId',
            'bookingSource', 'bookingStatus', 'sign',
            'dateFrom', 'dateTo',
            'commissionMin', 'commissionMax',
            'transactionMin', 'transactionMax',
        ]);
        $this->sort = 'id';
        $this->dir = 'desc';
        $this->syncDraftFromApplied();
        $this->resetPage();
        $this->dispatch('commission-wallet-dates-sync', from: $this->draftDateFrom, to: $this->draftDateTo);
    }

    private function syncDraftFromApplied(): void
    {
        $this->draftSearch = $this->search;
        $this->draftCategory = $this->category;
        $this->draftEntryType = $this->entryType;
        $this->draftReason = $this->reason;
        $this->draftAccommodationId = $this->accommodationId;
        $this->draftCityId = $this->cityId;
        $this->draftServiceCatalogId = $this->serviceCatalogId;
        $this->draftBookingSource = $this->bookingSource;
        $this->draftBookingStatus = $this->bookingStatus;
        $this->draftSign = $this->sign;
        $this->draftDateFrom = $this->dateFrom;
        $this->draftDateTo = $this->dateTo;
        $this->draftCommissionMin = $this->commissionMin;
        $this->draftCommissionMax = $this->commissionMax;
        $this->draftTransactionMin = $this->transactionMin;
        $this->draftTransactionMax = $this->transactionMax;
    }

    /** @return array<string, mixed> */
    private function filterParams(): array
    {
        return [
            'search'             => $this->search,
            'category'           => $this->category,
            'entry_type'         => $this->entryType,
            'reason'             => $this->reason,
            'accommodation_id'   => $this->accommodationId,
            'city_id'            => $this->cityId,
            'service_catalog_id' => $this->serviceCatalogId,
            'booking_source'     => $this->bookingSource,
            'booking_status'     => $this->bookingStatus,
            'sign'               => $this->sign,
            'date_from'          => $this->dateFrom,
            'date_to'            => $this->dateTo,
            'commission_min'     => $this->commissionMin,
            'commission_max'     => $this->commissionMax,
            'transaction_min'    => $this->transactionMin,
            'transaction_max'    => $this->transactionMax,
            'sort'               => $this->sort,
            'dir'                => $this->dir,
        ];
    }

    public function render(PlatformCommissionService $commission)
    {
        $filter = PlatformCommissionEntryFilter::make($this->filterParams());

        $baseQuery = PlatformCommissionEntry::query()
            ->with(['booking', 'accommodation.city', 'serviceCatalog', 'createdBy']);

        $filteredQuery = (clone $baseQuery);
        $filter->apply($filteredQuery);

        $entries = (clone $filteredQuery)->paginate(20);

        $filteredStats = [
            'count'              => (clone $filteredQuery)->count(),
            'sum_commission'     => (int) (clone $filteredQuery)->sum('commission_amount'),
            'sum_credits'        => (int) (clone $filteredQuery)->where('commission_amount', '>', 0)->sum('commission_amount'),
            'sum_debits'         => abs((int) (clone $filteredQuery)->where('commission_amount', '<', 0)->sum('commission_amount')),
            'sum_transaction'    => (int) (clone $filteredQuery)->sum('transaction_amount'),
        ];

        $stats = [
            'balance'        => $commission->walletBalance(),
            'total_credits'  => (int) PlatformCommissionEntry::query()->where('commission_amount', '>', 0)->sum('commission_amount'),
            'total_reversals'=> abs((int) PlatformCommissionEntry::query()->where('commission_amount', '<', 0)->sum('commission_amount')),
            'entries_count'  => PlatformCommissionEntry::count(),
        ];

        $driver = DB::getDriverName();
        $monthExpr = match ($driver) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql'  => "to_char(created_at, 'YYYY-MM')",
            default  => "DATE_FORMAT(created_at, '%Y-%m')",
        };
        $monthly = PlatformCommissionEntry::query()
            ->selectRaw("{$monthExpr} as month, SUM(commission_amount) as total")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $accommodations = Accommodation::orderBy('name')->get(['id', 'name']);
        $cities = City::orderBy('name')->get(['id', 'name']);
        $serviceCatalogs = ServiceCatalog::active()->ordered()->get(['id', 'name', 'key']);
        $hasActiveFilters = $filter->hasActiveFilters();
        $exportQuery = $filter->exportQuery();
        $hasDraftChanges = $this->hasDraftChanges();

        return view('admin.commission-wallet.index', compact(
            'entries',
            'stats',
            'filteredStats',
            'monthly',
            'accommodations',
            'cities',
            'serviceCatalogs',
            'hasActiveFilters',
            'exportQuery',
            'hasDraftChanges',
        ));
    }

    private function hasDraftChanges(): bool
    {
        return $this->draftSearch !== $this->search
            || $this->draftCategory !== $this->category
            || $this->draftEntryType !== $this->entryType
            || $this->draftReason !== $this->reason
            || $this->draftAccommodationId !== $this->accommodationId
            || $this->draftCityId !== $this->cityId
            || $this->draftServiceCatalogId !== $this->serviceCatalogId
            || $this->draftBookingSource !== $this->bookingSource
            || $this->draftBookingStatus !== $this->bookingStatus
            || $this->draftSign !== $this->sign
            || $this->draftDateFrom !== $this->dateFrom
            || $this->draftDateTo !== $this->dateTo
            || $this->draftCommissionMin !== $this->commissionMin
            || $this->draftCommissionMax !== $this->commissionMax
            || $this->draftTransactionMin !== $this->transactionMin
            || $this->draftTransactionMax !== $this->transactionMax;
    }
}
