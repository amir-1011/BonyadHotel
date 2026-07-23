<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BookingReserverFilterCatalog
{
    /**
     * @param  array<int>|null  $scopedAccommodationIds
     * @return Collection<int, User>
     */
    public function reservers(?string $accommodationId, ?array $scopedAccommodationIds = null): Collection
    {
        $query = Booking::query()->withReserver();

        if ($accommodationId !== null && $accommodationId !== '') {
            $id = (int) $accommodationId;

            if ($scopedAccommodationIds !== null && !in_array($id, $scopedAccommodationIds, true)) {
                return new Collection();
            }

            $query->where('accommodation_id', $id);
        } elseif ($scopedAccommodationIds !== null) {
            if ($scopedAccommodationIds === []) {
                return new Collection();
            }

            $query->whereIn('accommodation_id', $scopedAccommodationIds);
        }

        $reserverIds = $query
            ->selectRaw('DISTINCT COALESCE(created_by, user_id) as reserver_id')
            ->pluck('reserver_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($reserverIds->isEmpty()) {
            return new Collection();
        }

        return User::query()
            ->whereIn('id', $reserverIds)
            ->orderBy('name')
            ->get(['id', 'name', 'mobile']);
    }
}
