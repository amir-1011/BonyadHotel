<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HallType extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function halls(): HasMany
    {
        return $this->hasMany(Hall::class);
    }
}
