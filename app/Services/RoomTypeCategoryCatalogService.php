<?php

namespace App\Services;

use App\Models\RoomType;
use App\Models\RoomTypeCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomTypeCategoryCatalogService
{
    public function __construct(
        private readonly RoomTypeAmenityCatalogService $normalizer,
    ) {}

    public function normalize(string $value): string
    {
        return $this->normalizer->normalize($value);
    }

    /**
     * @return Collection<int, RoomTypeCategory>
     */
    public function allOrdered(): Collection
    {
        $this->ensureSeeded();

        return RoomTypeCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->allOrdered()->pluck('name')->all();
    }

    public function add(string $name, ?int $createdBy = null): RoomTypeCategory
    {
        $this->ensureSeeded();

        $name = $this->normalize($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'نام نوع اتاق را وارد کنید.']);
        }

        if (mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'نام نوع اتاق نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        $existing = RoomTypeCategory::query()->where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $maxSort = (int) RoomTypeCategory::query()->max('sort_order');

        return RoomTypeCategory::create([
            'name'       => $name,
            'sort_order' => $maxSort + 1,
            'created_by' => $createdBy,
        ]);
    }

    public function rename(RoomTypeCategory $category, string $newName): RoomTypeCategory
    {
        $oldName = $category->name;
        $newName = $this->normalize($newName);

        if ($newName === '') {
            throw ValidationException::withMessages(['name' => 'نام نوع اتاق را وارد کنید.']);
        }

        if (mb_strlen($newName) > 60) {
            throw ValidationException::withMessages(['name' => 'نام نوع اتاق نباید بیشتر از ۶۰ کاراکتر باشد.']);
        }

        if ($newName === $oldName) {
            return $category;
        }

        $duplicate = RoomTypeCategory::query()
            ->where('name', $newName)
            ->whereKeyNot($category->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'این نام قبلاً در لیست وجود دارد.']);
        }

        DB::transaction(function () use ($category, $oldName, $newName): void {
            RoomType::query()->where('bed_type', $oldName)->update(['bed_type' => $newName]);
            $category->update(['name' => $newName]);
        });

        return $category->fresh();
    }

    public function remove(RoomTypeCategory $category): void
    {
        $category->delete();
    }

    private function ensureSeeded(): void
    {
        if (RoomTypeCategory::query()->exists()) {
            return;
        }

        $sort = 0;
        $seen = [];

        foreach (config('room_types.categories', []) as $name) {
            $name = $this->normalize((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            RoomTypeCategory::create(['name' => $name, 'sort_order' => $sort++]);
        }

        foreach (RoomType::query()->whereNotNull('bed_type')->pluck('bed_type')->unique() as $name) {
            $name = $this->normalize((string) $name);
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            RoomTypeCategory::create(['name' => $name, 'sort_order' => $sort++]);
        }
    }
}
