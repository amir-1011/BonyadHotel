<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HostPositionTitle extends Model
{
    protected $fillable = [
        'label',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_system'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public static function findOrCreateByLabel(string $label): self
    {
        $label = trim($label);

        return static::query()->firstOrCreate(
            ['label' => $label],
            ['is_system' => false, 'sort_order' => 1000],
        );
    }

    /** @return list<string> */
    public static function optionLabels(): array
    {
        return static::query()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->pluck('label')
            ->all();
    }
}
