<?php

namespace App\Livewire\Admin;

use App\Services\AdminVeteranDiscountStatsService;
use Livewire\Attributes\Defer;
use Livewire\Component;

/**
 * Deferred: veteran discount aggregation can be slow on large datasets, so it is
 * loaded asynchronously after first paint to keep the dashboard response fast.
 */
#[Defer]
class VeteranDiscountStats extends Component
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
            <div class="col-6 col-lg-4 col-xl-3">
                <div class="ta-metric h-100">
                    <div class="d-flex align-items-center justify-content-center" style="min-height:5.5rem">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">در حال بارگذاری...</span>
                        </div>
                    </div>
                </div>
            </div>
            HTML;

        return '<div class="row g-4 mb-4">' . str_repeat($card, 7) . '</div>';
    }

    public function render(AdminVeteranDiscountStatsService $service)
    {
        $data = $service->build($this->dashboardAccommodationIds);

        return view('components.admin.veteran-discount-stats', $data);
    }
}
