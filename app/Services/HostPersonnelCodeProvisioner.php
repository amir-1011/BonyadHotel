<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Accommodation;
use App\Models\Province;
use App\Models\User;
use App\Support\ProvinceAccountingIndicators;

class HostPersonnelCodeProvisioner
{
    public function __construct(
        private readonly ProvinceAccountingCodeService $codeService,
    ) {}

    /**
     * Assign personnel accounting code from the host's first linked accommodation province.
     */
    public function provisionIfNeeded(User $user, ?Accommodation $contextAccommodation = null): User
    {
        if (!$user->isHost() || filled($user->personnel_code)) {
            return $user;
        }

        $province = $this->resolveProvinceFromAccommodations($user, $contextAccommodation);

        if (!$province) {
            return $user;
        }

        $personnelCode = $this->codeService->assignNext(
            $province,
            ProvinceAccountingIndicators::PERSONNEL,
        );

        $user->forceFill([
            'province_id'    => $province->id,
            'personnel_code' => $personnelCode,
        ])->save();

        return $user->fresh(['province']);
    }

    public function resolveProvinceFromAccommodations(User $user, ?Accommodation $contextAccommodation = null): ?Province
    {
        if ($contextAccommodation) {
            $contextAccommodation->loadMissing(['city.province', 'county.province']);
            $fromContext = $contextAccommodation->resolvedProvince();

            if ($fromContext) {
                return $fromContext;
            }
        }

        if ($user->relationLoaded('accommodations')) {
            $firstAccommodation = $user->accommodations
                ->sortBy(fn (Accommodation $accommodation) => $accommodation->pivot?->created_at?->timestamp ?? $accommodation->id)
                ->first();
        } else {
            $firstAccommodation = $user->accommodations()
                ->with(['city.province', 'county.province'])
                ->orderBy('accommodation_host.created_at')
                ->orderBy('accommodations.id')
                ->first();
        }

        return $firstAccommodation?->resolvedProvince();
    }

    public function previewNextForAccommodation(?Accommodation $accommodation): ?string
    {
        $province = $accommodation?->resolvedProvince();

        if (!$province) {
            return null;
        }

        try {
            return $this->codeService->previewNext($province, ProvinceAccountingIndicators::PERSONNEL);
        } catch (\Throwable) {
            return null;
        }
    }
}
