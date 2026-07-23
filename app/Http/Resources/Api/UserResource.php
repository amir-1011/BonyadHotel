<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'mobile'                  => $this->mobile,
            'national_id'             => $this->when($this->national_id, fn () => substr($this->national_id, 0, 3) . '*******'),
            'veteran_type'            => $this->veteran_type,
            'secondary_veteran_type'  => $this->secondary_veteran_type,
            'discount_percentage'     => (int) $this->discount_percentage,
            'veteran_label'           => $this->veteranLabel(),
            'mobile_verified_at'      => $this->mobile_verified_at?->toIso8601String(),
            'national_id_verified_at' => $this->national_id_verified_at?->toIso8601String(),
            'requires_profile_setup'  => blank($this->name),
        ];
    }
}
