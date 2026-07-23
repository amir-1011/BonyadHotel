<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingBeneficiaryCost extends Model
{
    protected $fillable = [
        'booking_id',
        'program_beneficiary_id',
        'user_id',
        'debt_amount',
        'description',
        'documents',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'documents' => 'array',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(ProgramBeneficiary::class, 'program_beneficiary_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
