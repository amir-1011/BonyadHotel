<?php

namespace App\Livewire\Concerns;

use App\Models\FacilityExchangeItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait QueriesFacilityExchangeItems
{
    protected function buildFacilityItemsQuery(string $type): Builder
    {
        $query = FacilityExchangeItem::query()
            ->where('type', $type)
            ->with(['user', 'brand', 'category', 'province']);

        if ($this->search !== '') {
            $s = $this->search;
            $query->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhereHas('brand', fn ($q) => $q->where('name', 'like', "%{$s}%"));
            });
        }

        if ($this->categoryId > 0) {
            $query->where('category_id', $this->categoryId);
        }

        if ($this->provinceId > 0) {
            $query->where('province_id', $this->provinceId);
        }

        if ($this->supportsMineOnlyFilter() && $this->mineOnly === '1') {
            $query->where('user_id', Auth::id());
        }

        return $query;
    }
}
