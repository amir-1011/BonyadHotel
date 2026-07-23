<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use Livewire\Attributes\Url;

trait ManagesDashboardAccommodationFilter
{
    /** @var array<int> Applied selection when not in "all" mode. */
    #[Url(as: 'acc')]
    public array $selectedAccommodationIds = [];

    public bool $dashboardAccommodationAllSelected = true;

    /** @var array<int> */
    public array $draftDashboardAccommodationIds = [];

    public bool $draftDashboardAccommodationAllSelected = true;

    /**
     * Per-request memoization cache for {@see resolveDashboardAccommodationOptions()}.
     * Avoids re-querying the same accommodation list multiple times within a single
     * render cycle (label, ids, sanitize, and view all call into this).
     *
     * @var list<array{id: int, name: string}>|null
     */
    protected ?array $dashboardAccommodationOptionsCache = null;

    protected function bootDashboardAccommodationFilter(): void
    {
        $this->selectedAccommodationIds = $this->sanitizeDashboardAccommodationIds($this->selectedAccommodationIds);
        $this->dashboardAccommodationAllSelected = $this->selectedAccommodationIds === [];
        $this->syncDraftDashboardAccommodationFilter();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function dashboardAccommodationOptionList(): array
    {
        if ($this->dashboardAccommodationOptionsCache === null) {
            $this->dashboardAccommodationOptionsCache = $this->resolveDashboardAccommodationOptions();
        }

        return $this->dashboardAccommodationOptionsCache;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    protected function resolveDashboardAccommodationOptions(): array
    {
        return [];
    }

    public function resetDraftDashboardAccommodationFilter(): void
    {
        $this->syncDraftDashboardAccommodationFilter();
    }

    /** @return array<int> */
    public function effectiveDashboardAccommodationIds(): array
    {
        $all = $this->allDashboardAccommodationIds();

        if ($this->dashboardAccommodationAllSelected) {
            return $all;
        }

        return array_values(array_intersect(
            array_map('intval', $this->selectedAccommodationIds),
            $all,
        ));
    }

    public function isDraftDashboardAccommodationSelected(int $id): bool
    {
        if ($this->draftDashboardAccommodationAllSelected) {
            return true;
        }

        return in_array($id, $this->draftDashboardAccommodationIds, true);
    }

    public function toggleDraftDashboardAccommodation(int $id): void
    {
        $allIds = $this->allDashboardAccommodationIds();

        if (!in_array($id, $allIds, true)) {
            return;
        }

        if ($this->draftDashboardAccommodationAllSelected) {
            $this->draftDashboardAccommodationAllSelected = false;
            $this->draftDashboardAccommodationIds = array_values(array_filter(
                $allIds,
                fn (int $itemId) => $itemId !== $id,
            ));
        } elseif (in_array($id, $this->draftDashboardAccommodationIds, true)) {
            $this->draftDashboardAccommodationIds = array_values(array_filter(
                $this->draftDashboardAccommodationIds,
                fn (int $itemId) => $itemId !== $id,
            ));
        } else {
            $this->draftDashboardAccommodationIds[] = $id;
            sort($this->draftDashboardAccommodationIds);

            if (count($this->draftDashboardAccommodationIds) === count($allIds)) {
                $this->selectAllDraftDashboardAccommodations();
            }
        }
    }

    public function selectAllDraftDashboardAccommodations(): void
    {
        $this->draftDashboardAccommodationAllSelected = true;
        $this->draftDashboardAccommodationIds = [];
    }

    public function clearDraftDashboardAccommodations(): void
    {
        $this->draftDashboardAccommodationAllSelected = false;
        $this->draftDashboardAccommodationIds = [];
    }

    public function applyDashboardAccommodationFilter(): void
    {
        $this->dashboardAccommodationAllSelected = $this->draftDashboardAccommodationAllSelected;
        $this->selectedAccommodationIds = $this->dashboardAccommodationAllSelected
            ? []
            : $this->sanitizeDashboardAccommodationIds($this->draftDashboardAccommodationIds);

        $this->onDashboardAccommodationFilterChanged();
    }

    public function applyDashboardAccommodationFilterFromClient(bool $allSelected, array $ids): void
    {
        $this->draftDashboardAccommodationAllSelected = $allSelected;
        $this->draftDashboardAccommodationIds = $allSelected
            ? []
            : $this->sanitizeDashboardAccommodationIds($ids);

        $this->applyDashboardAccommodationFilter();
    }

    public function dashboardAccommodationFilterLabel(): string
    {
        $options = $this->dashboardAccommodationOptionList();
        $total = count($options);

        if ($total === 0) {
            return 'اقامتگاهی موجود نیست';
        }

        if ($this->dashboardAccommodationAllSelected) {
            return "همه اقامتگاه‌ها ({$total})";
        }

        $count = count($this->selectedAccommodationIds);

        if ($count === 0) {
            return 'هیچ اقامتگاهی انتخاب نشده';
        }

        if ($count === 1) {
            $id = $this->selectedAccommodationIds[0];
            $option = collect($options)->firstWhere('id', $id);
            $name = is_array($option) ? ($option['name'] ?? '') : '';

            return $name !== '' ? Str::limit($name, 32) : '۱ اقامتگاه';
        }

        return "{$count} از {$total} اقامتگاه";
    }

    public function hasPendingDashboardAccommodationFilter(): bool
    {
        if ($this->draftDashboardAccommodationAllSelected !== $this->dashboardAccommodationAllSelected) {
            return true;
        }

        if ($this->draftDashboardAccommodationAllSelected) {
            return false;
        }

        $draft = $this->sanitizeDashboardAccommodationIds($this->draftDashboardAccommodationIds);
        $applied = $this->sanitizeDashboardAccommodationIds($this->selectedAccommodationIds);

        return $draft !== $applied;
    }

    public function dashboardAccommodationFilterKey(): string
    {
        $ids = $this->effectiveDashboardAccommodationIds();
        sort($ids);

        return md5(implode(',', $ids));
    }

    public function showDashboardAccommodationFilter(): bool
    {
        return count($this->dashboardAccommodationOptionList()) > 0;
    }

    /** @return array<int> */
    protected function allDashboardAccommodationIds(): array
    {
        return collect($this->dashboardAccommodationOptionList())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** @param  array<int>  $ids */
    protected function sanitizeDashboardAccommodationIds(array $ids): array
    {
        $allowed = $this->allDashboardAccommodationIds();

        return array_values(array_intersect(array_map('intval', $ids), $allowed));
    }

    protected function syncDraftDashboardAccommodationFilter(): void
    {
        $this->draftDashboardAccommodationAllSelected = $this->dashboardAccommodationAllSelected;
        $this->draftDashboardAccommodationIds = $this->selectedAccommodationIds;
    }

    protected function onDashboardAccommodationFilterChanged(): void
    {
        $this->syncDraftDashboardAccommodationFilter();
        $this->dispatch('dashboard-accommodation-filter-changed');
    }
}
