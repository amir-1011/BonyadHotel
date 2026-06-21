<?php

namespace App\Services;

use App\Models\City;
use App\Models\Province;

class LocationCatalogService
{
    /** @var array<string, string> */
    private array $provinceAliases = [
        'تهران بزرگ' => 'تهران',
    ];

    public function normalizeFa(string $value): string
    {
        $value = str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    public function findCityId(string $provinceName, string $cityName): ?int
    {
        $provinceName = $this->normalizeFa($provinceName);
        $cityName = $this->normalizeFa($cityName);

        if ($provinceName === '' || $cityName === '') {
            return null;
        }

        $province = $this->findProvince($provinceName);
        if (!$province) {
            return null;
        }

        return City::query()
            ->where('province_id', $province->id)
            ->where('name', $cityName)
            ->value('id');
    }

    /**
     * @return array{id:int, province_created:bool, city_created:bool, province_name:string, city_name:string}
     */
    public function resolveOrCreateCity(string $provinceName, string $cityName): array
    {
        $provinceName = $this->normalizeFa($provinceName);
        $cityName = $this->normalizeFa($cityName);

        $provinceCreated = false;
        $cityCreated = false;

        $province = $this->findProvince($provinceName);
        if (!$province) {
            $province = Province::create(['name' => $provinceName]);
            $provinceCreated = true;
        }

        $city = City::query()
            ->where('province_id', $province->id)
            ->where('name', $cityName)
            ->first();

        if (!$city) {
            $city = City::create([
                'province_id' => $province->id,
                'name'        => $cityName,
            ]);
            $cityCreated = true;
        }

        return [
            'id'               => $city->id,
            'province_created' => $provinceCreated,
            'city_created'     => $cityCreated,
            'province_name'    => $province->name,
            'city_name'        => $city->name,
        ];
    }

    public function findProvince(string $provinceName): ?Province
    {
        $provinceName = $this->normalizeFa($provinceName);
        if ($provinceName === '') {
            return null;
        }

        $canonical = $this->provinceAliases[$provinceName] ?? $provinceName;

        return Province::where('name', $canonical)->first()
            ?? ($canonical !== $provinceName ? Province::where('name', $provinceName)->first() : null);
    }

    public function createProvince(string $name): Province
    {
        return Province::create(['name' => $this->normalizeFa($name)]);
    }
}
