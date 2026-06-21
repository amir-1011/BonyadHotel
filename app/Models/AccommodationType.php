<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AccommodationType extends Model
{
    protected $fillable = ['key', 'label', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public static function options(): Collection
    {
        return static::orderBy('label')->pluck('label', 'key');
    }

    public static function validKeys(): array
    {
        return static::pluck('key')->all();
    }

    public static function labelFor(?string $key): string
    {
        if (!$key) {
            return '';
        }

        return static::where('key', $key)->value('label') ?? $key;
    }

    public static function findOrCreateByLabel(string $label): self
    {
        $label = trim($label);

        $existing = static::where('label', $label)->first();
        if ($existing) {
            return $existing;
        }

        $baseKey = Str::slug($label, '_');
        if ($baseKey === '') {
            $baseKey = 'custom';
        }

        $key = $baseKey;
        $suffix = 1;
        while (static::where('key', $key)->exists()) {
            $key = $baseKey . '_' . $suffix++;
        }

        return static::create([
            'key'       => $key,
            'label'     => $label,
            'is_system' => false,
        ]);
    }

    public function accommodations()
    {
        return Accommodation::where('type', $this->key);
    }

    public function isInUse(): bool
    {
        return Accommodation::where('type', $this->key)->exists();
    }
}
