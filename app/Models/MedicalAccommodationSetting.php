<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalAccommodationSetting extends Model
{
    protected $fillable = [
        'accommodation_id',
        'program_employer_id',
        'contract_starts_on',
        'contract_ends_on',
        'skip_cancellation_penalties',
        'require_overnight',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contract_starts_on'           => 'date',
            'contract_ends_on'             => 'date',
            'skip_cancellation_penalties'  => 'boolean',
            'require_overnight'            => 'boolean',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function employer(): BelongsTo
    {
        return $this->belongsTo(ProgramEmployer::class, 'program_employer_id');
    }
}
