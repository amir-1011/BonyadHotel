<?php

namespace App\Models\Concerns;

trait DisplaysGuestIdentity
{
    public function isForeignGuestProfile(): bool
    {
        return (bool) ($this->is_foreign_guest ?? false);
    }

    public function identityFieldLabel(): string
    {
        return $this->isForeignGuestProfile() ? 'پاسپورت' : 'کد ملی';
    }

    public function identityNumber(): ?string
    {
        if ($this->isForeignGuestProfile()) {
            $passport = trim((string) ($this->passport_number ?? ''));

            return $passport !== '' ? $passport : null;
        }

        $nationalId = trim((string) ($this->national_id ?? ''));

        return $nationalId !== '' ? $nationalId : null;
    }

    public function residenceLocationLabel(): ?string
    {
        if (!$this->isForeignGuestProfile()) {
            return null;
        }

        $city = $this->relationLoaded('residenceCity')
            ? $this->residenceCity
            : ($this->residence_city_id ? $this->residenceCity()->first() : null);

        $country = $this->relationLoaded('country')
            ? $this->country
            : ($this->country_id ? $this->country()->first() : null);

        $parts = array_filter([
            $city?->name,
            $country?->name,
        ]);

        return $parts === [] ? null : implode('، ', $parts);
    }
}
