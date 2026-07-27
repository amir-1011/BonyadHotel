<?php

namespace App\Models;

use App\Models\Concerns\HasProgramAccountingProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramEmployer extends Model
{
    use HasProgramAccountingProfile;
    protected $fillable = [
        'province_id',
        'user_id',
        'name',
        'employer_code',
        'national_or_economic_id',
        'mobile',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function displayLabel(): string
    {
        return $this->name . ' (' . $this->employer_code . ')';
    }

    protected function accountingCodeValue(): ?string
    {
        return filled($this->employer_code) ? (string) $this->employer_code : null;
    }

    protected function accountingEntityTypeLabel(): string
    {
        return 'ارگان / اداره';
    }
}
