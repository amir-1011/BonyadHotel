<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CancellationRequest */
class CancellationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'status'            => $this->status,
            'status_label'      => $this->statusLabel(),
            'reason'            => $this->reasonDisplay(),
            'notes'             => $this->notes,
            'refund_percentage' => (int) $this->refund_percentage,
            'refund_amount'     => (int) $this->refund_amount,
            'refund_account_number' => $this->when(
                $request->user()?->id === $this->requested_by,
                $this->refund_account_number
            ),
            'refund_account_holder_name' => $this->refund_account_holder_name,
            'days_before_checkin' => $this->days_before_checkin,
            'is_settled'        => $this->isSettled(),
            'settled_at'        => $this->settled_at?->toIso8601String(),
            'rejection_reason'  => $this->rejection_reason,
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
