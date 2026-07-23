<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramBeneficiaryCost extends Model
{
    protected $fillable = [
        'program_id',
        'program_beneficiary_id',
        'debt_amount',
        'description',
        'documents',
    ];

    protected function casts(): array
    {
        return [
            'documents' => 'array',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(ProgramBeneficiary::class, 'program_beneficiary_id');
    }
}
