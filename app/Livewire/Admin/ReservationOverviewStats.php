<?php

namespace App\Livewire\Admin;

use App\Services\AdminDashboardDataService;
use App\Services\AdminVeteranDiscountStatsService;
use Livewire\Attributes\Defer;
use Livewire\Component;

/**
 * Deferred: overview metrics and veteran discount stats are aggregated on demand,
 * so they load asynchronously after first paint to keep the dashboard response fast.
 */
#[Defer]
class ReservationOverviewStats extends Component
{
    /** @var array<int> */
    public array $dashboardAccommodationIds = [];

    public function mount(array $dashboardAccommodationIds = []): void
    {
        $this->dashboardAccommodationIds = array_values(array_map('intval', $dashboardAccommodationIds));
    }

    public function placeholder(array $params = []): string
    {
        $card = <<<'HTML'
            <div class="col-6 col-xl-3">
                <div class="ta-stat-card" style="min-height:9.5rem">
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="spinner-border spinner-border-sm text-secondary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
            HTML;

        return '<div class="admin-overview-stats">'
            . '<div class="row g-3 mb-3">' . str_repeat($card, 4) . '</div>'
            . '<div class="text-center mb-4">'
            . '<button type="button" class="btn btn-light btn-sm px-4" disabled>مشاهده بیشتر</button>'
            . '</div>'
            . '</div>';
    }

    public function render(
        AdminDashboardDataService $dashboardDataService,
        AdminVeteranDiscountStatsService $veteranStatsService,
    ) {
        $overview = $dashboardDataService->buildOverviewStats($this->dashboardAccommodationIds);
        $veteran = $veteranStatsService->build($this->dashboardAccommodationIds);

        return view('components.admin.reservation-overview-stats', array_merge($overview, $veteran));
    }
}
