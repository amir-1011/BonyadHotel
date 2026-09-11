<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccommodationPosMapping extends Model
{
    protected $fillable = [
        'accommodation_id',
        'windows_lan_ip',
        'pos_lan_ip',
        'pos_port',
        'agent_port',
        'is_active',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pos_port' => 'integer',
            'agent_port' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function accommodation(): BelongsTo
    {
        return $this->belongsTo(Accommodation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agentPort(): int
    {
        $port = (int) $this->agent_port;

        return $port > 0 ? $port : (int) config('pcpos.default_agent_port', 8088);
    }

    public function posPort(): int
    {
        $port = (int) $this->pos_port;

        return $port > 0 ? $port : (int) config('pcpos.default_pos_port', 1362);
    }
}
