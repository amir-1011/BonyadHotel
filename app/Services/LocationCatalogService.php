<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use App\Models\County;
use App\Models\Province;
use App\Models\ResidenceCity;

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

    public function findCountyId(string $provinceName, string $countyName): ?int
    {
        $provinceName = $this->normalizeFa($provinceName);
        $countyName = $this->normalizeFa($countyName);

        if ($provinceName === '' || $countyName === '') {
            return null;
        }

        $province = $this->findProvince($provinceName);
        if (!$province) {
            return null;
        }

        return County::query()
            ->where('province_id', $province->id)
            ->where('name', $countyName)
            ->value('id');
    }

    /**
     * @return array{id:int, province_created:bool, county_created:bool, province_name:string, county_name:string}
     */
    public function resolveOrCreateCounty(string $provinceName, string $countyName): array
    {
        $provinceName = $this->normalizeFa($provinceName);
        $countyName = $this->normalizeFa($countyName);

        $provinceCreated = false;
        $countyCreated = false;

        $province = $this->findProvince($provinceName);
        if (!$province) {
            $province = Province::create(['name' => $provinceName]);
            $provinceCreated = true;
        }

        $county = County::query()
            ->where('province_id', $province->id)
            ->where('name', $countyName)
            ->first();

        if (!$county) {
            $county = County::create([
                'province_id' => $province->id,
                'name'        => $countyName,
            ]);
            $countyCreated = true;
        }

        return [
            'id'               => $county->id,
            'province_created' => $provinceCreated,
            'county_created'   => $countyCreated,
            'province_name'    => $province->name,
            'county_name'      => $county->name,
        ];
    }

    public function createCounty(int $provinceId, string $name): County
    {
        return County::create([
            'province_id' => $provinceId,
            'name'        => $this->normalizeFa($name),
        ]);
    }

    public function createCountry(string $name): Country
    {
        return Country::create(['name' => $this->normalizeFa($name)]);
    }

    /**
     * @return array{id:int, country_created:bool, city_created:bool, country_name:string, city_name:string}
     */
    public function resolveOrCreateResidenceCity(string $countryName, string $cityName): array
    {
        $countryName = $this->normalizeFa($countryName);
        $cityName = $this->normalizeFa($cityName);

        $countryCreated = false;
        $cityCreated = false;

        $country = Country::query()->where('name', $countryName)->first();
        if (!$country) {
            $country = Country::create(['name' => $countryName]);
            $countryCreated = true;
        }

        $city = ResidenceCity::query()
            ->where('country_id', $country->id)
            ->where('name', $cityName)
            ->first();

        if (!$city) {
            $city = ResidenceCity::create([
                'country_id' => $country->id,
                'name'       => $cityName,
            ]);
            $cityCreated = true;
        }

        return [
            'id'             => $city->id,
            'country_created' => $countryCreated,
            'city_created'   => $cityCreated,
            'country_name'   => $country->name,
            'city_name'      => $city->name,
        ];
    }

    public function createResidenceCity(int $countryId, string $name): ResidenceCity
    {
        return ResidenceCity::create([
            'country_id' => $countryId,
            'name'       => $this->normalizeFa($name),
        ]);
    }
}
