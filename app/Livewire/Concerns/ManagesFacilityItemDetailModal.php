<?php

namespace App\Livewire\Concerns;

use App\Models\FacilityExchangeItem;

trait ManagesFacilityItemDetailModal
{
    public ?int $detailItemId = null;

    public function openDetail(int $id): void
    {
        $exists = FacilityExchangeItem::query()
            ->where('type', $this->facilityDetailType())
            ->where('id', $id)
            ->exists();

        if (!$exists) {
            return;
        }

        $this->detailItemId = $id;
        $this->js('window.facilityDetailScheduleOpen?.()');
    }

    public function closeDetail(): void
    {
        $this->detailItemId = null;
    }

    public function requestCloseDetail(): void
    {
        $this->dispatch('facility-detail-close-requested');
    }

    protected function facilityDetailType(): string
    {
        return FacilityExchangeItem::TYPE_SURPLUS;
    }

    protected function resolveDetailItem(): ?FacilityExchangeItem
    {
        if (!$this->detailItemId) {
            return null;
        }

        return FacilityExchangeItem::query()
            ->where('type', $this->facilityDetailType())
            ->with(['user', 'brand', 'category', 'province'])
            ->find($this->detailItemId);
    }

    protected function closeDetailIfMatches(int $id): void
    {
        if ($this->detailItemId === $id) {
            $this->closeDetail();
        }
    }
}
