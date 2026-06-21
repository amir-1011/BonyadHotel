<?php

namespace App\Livewire\Concerns;

use App\Models\Accommodation;

trait ManagesAccommodationContactInfo
{
    public string $managementStatus = '';

    /** @var array<int, array{number:string, type:string, note:string}> */
    public array $phoneNumbers = [];

    protected function contactInfoRules(): array
    {
        return [
            'managementStatus' => ['required', 'in:outsourced,self_governing'],
            'phoneNumbers' => ['array'],
            'phoneNumbers.*.number' => ['nullable', 'string', 'max:20'],
            'phoneNumbers.*.type' => ['required_with:phoneNumbers.*.number', 'in:mobile,landline'],
            'phoneNumbers.*.note' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function loadContactInfoFrom(Accommodation $accommodation): void
    {
        $this->managementStatus = $accommodation->management_status ?? '';
        $this->phoneNumbers = array_map(
            fn (array $phone) => [
                'number' => $phone['number'] ?? '',
                'type'   => $phone['type'] ?? 'mobile',
                'note'   => $phone['note'] ?? '',
            ],
            $accommodation->phone_numbers ?? []
        );

        if (empty($this->phoneNumbers)) {
            $this->phoneNumbers = [$this->emptyPhoneRow()];
        }
    }

    protected function emptyPhoneRow(): array
    {
        return ['number' => '', 'type' => 'mobile', 'note' => ''];
    }

    public function addPhoneNumber(): void
    {
        $this->phoneNumbers[] = $this->emptyPhoneRow();
    }

    public function removePhoneNumber(int $index): void
    {
        unset($this->phoneNumbers[$index]);
        $this->phoneNumbers = array_values($this->phoneNumbers);

        if (empty($this->phoneNumbers)) {
            $this->phoneNumbers = [$this->emptyPhoneRow()];
        }
    }

    protected function validateContactInfo(): void
    {
        $this->validate($this->contactInfoRules());

        foreach ($this->phoneNumbers as $index => $phone) {
            $number = trim($phone['number'] ?? '');
            if ($number === '') {
                continue;
            }

            $type = $phone['type'] ?? 'mobile';
            $rule = $type === 'mobile'
                ? 'regex:/^09[0-9]{9}$/'
                : 'regex:/^0[1-9][0-9]{8,10}$/';

            $this->validate([
                "phoneNumbers.{$index}.number" => ['required', 'string', $rule],
            ], [
                "phoneNumbers.{$index}.number.regex" => $type === 'mobile'
                    ? 'شماره همراه معتبر نیست. مثال: 09123456789'
                    : 'شماره ثابت معتبر نیست. مثال: 02112345678',
            ]);
        }
    }

    protected function normalizedPhoneNumbers(): array
    {
        return array_values(array_filter(
            array_map(fn (array $phone) => [
                'number' => trim($phone['number'] ?? ''),
                'type'   => $phone['type'] ?? 'mobile',
                'note'   => trim($phone['note'] ?? '') ?: null,
            ], $this->phoneNumbers),
            fn (array $phone) => $phone['number'] !== ''
        ));
    }

    protected function contactInfoAttributes(): array
    {
        return [
            'management_status' => $this->managementStatus,
            'phone_numbers'     => $this->normalizedPhoneNumbers() ?: null,
        ];
    }
}
