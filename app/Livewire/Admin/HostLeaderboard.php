<?php

namespace App\Livewire\Admin;

use App\Services\HostLeaderboardService;
use Livewire\Attributes\Defer;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Deferred: aggregates confirmed bookings across all hosts, so it is excluded from
 * the initial admin dashboard response and loaded asynchronously right after first
 * paint, keeping the page's first response fast.
 */
#[Defer]
class HostLeaderboard extends Component
{
    #[Url(as: 'lb_all')]
    public bool $allTime = false;

    #[Url(as: 'lb_month')]
    public string $month = '';

    /** @var array<int> */
    public array $dashboardAccommodationIds = [];

    public function mount(HostLeaderboardService $service, array $dashboardAccommodationIds = []): void
    {
        $this->dashboardAccommodationIds = array_values(array_map('intval', $dashboardAccommodationIds));

        if ($this->allTime) {
            return;
        }

        if ($this->month === '' || !$service->isValidMonthKey($this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function showAllTime(): void
    {
        $this->allTime = true;
    }

    public function showMonthly(HostLeaderboardService $service): void
    {
        $this->allTime = false;

        if ($this->month === '' || !$service->isValidMonthKey($this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function updatedMonth(HostLeaderboardService $service): void
    {
        $this->allTime = false;

        if (!$service->isValidMonthKey($this->month)) {
            $this->month = now()->format('Y-m');
        }
    }

    public function placeholder(array $params = []): string
    {
        return <<<'HTML'
            <div class="ta-card">
                <div class="ta-card__head">
                    <h2 class="ta-card__title mb-0"><i class="bi bi-trophy me-2"></i>برترین کاربران</h2>
                </div>
                <div class="ta-card__body d-flex align-items-center justify-content-center" style="min-height:180px">
                    <div class="spinner-border text-primary" role="status" style="width:2rem;height:2rem;">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
            HTML;
    }

    public function render(HostLeaderboardService $service)
    {
        $monthKey = $this->allTime ? HostLeaderboardService::ALL_TIME_KEY : $this->month;
        $data     = $service->build($monthKey, accommodationIds: $this->dashboardAccommodationIds);

        return view('components.admin.host-leaderboard', $data);
    }
}
