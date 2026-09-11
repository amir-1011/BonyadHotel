<?php

namespace App\Services;

use App\Models\HallType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HallTypeCatalogService
{
    public function __construct(
        private readonly HallAmenityCatalogService $normalizer,
    ) {}

    public function normalize(string $value): string
    {
        return $this->normalizer->normalize($value);
    }

    /**
     * @return Collection<int, HallType>
     */
    public function allOrdered(): Collection
    {
        $this->ensureSeeded();

        return HallType::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function add(string $name, ?int $createdBy = null): HallType
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'نام نوع سالن را وارد کنید.']);
        }

        if (mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'نام نوع سالن نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        $existing = HallType::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $maxSort = (int) HallType::query()->max('sort_order');

        return HallType::create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
            'created_by' => $createdBy,
        ]);
    }

    public function rename(HallType $type, string $newName): HallType
    {
        $newName = $this->normalize($newName);

        if ($newName === '') {
            throw ValidationException::withMessages(['name' => 'نام نوع سالن را وارد کنید.']);
        }

        if (mb_strlen($newName) > 60) {
            throw ValidationException::withMessages(['name' => 'نام نوع سالن نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        if ($newName === $type->name) {
            return $type;
        }

        $duplicate = HallType::query()
            ->where('name', $newName)
            ->whereKeyNot($type->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'این نام قبلاً در لیست وجود دارد.']);
        }

        $type->update(['name' => $newName]);

        return $type->fresh();
    }

    public function remove(HallType $type): void
    {
        if ($type->halls()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'این نوع سالن به سالن تعریف‌شده‌ای متصل است و قابل حذف نیست.',
            ]);
        }

        $type->delete();
    }

    private function ensureSeeded(): void
    {
        if (HallType::query()->exists()) {
            return;
        }

        $sort = 0;
        $seen = [];

        foreach (config('halls.types', []) as $name) {
            $name = $this->normalize((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            HallType::create(['name' => $name, 'sort_order' => $sort++]);
        }
    }
}
