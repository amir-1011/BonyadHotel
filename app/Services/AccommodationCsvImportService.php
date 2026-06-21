<?php

namespace App\Services;

use App\Models\Accommodation;
use App\Models\AccommodationType;
use App\Models\RoomRate;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccommodationCsvImportService
{
    public function __construct(
        private readonly LocationCatalogService $locations,
    ) {}

    /** @var array<string, string> */
    private array $columnMap = [];

    /** @var list<string> */
    private array $requiredColumns = [
        'accommodation_code',
        'province_name',
        'city_name',
        'accommodation_name',
        'type',
        'management_status',
        'price_per_night',
        'capacity',
        'rooms',
        'room_code',
        'room_name',
        'room_capacity',
        'room_count',
        'rate_name',
        'rate_price_per_night',
        'cancellation_policy',
        'payment_type',
    ];

    /**
     * @return array{success: bool, imported: int, errors: list<string>, warnings: list<string>, summary: array<string, int>}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $rows = $this->parseFile($path);

        if (empty($rows)) {
            return $this->result(false, 0, ['فایل CSV خالی است یا قابل خواندن نیست.'], []);
        }

        $grouped = $this->groupRows($rows);
        $errors = [];
        $warnings = [];
        $imported = 0;

        foreach ($grouped as $accommodationCode => $accommodationRows) {
            $groupResult = $this->validateAccommodationGroup($accommodationCode, $accommodationRows);
            if (!empty($groupResult['errors'])) {
                $errors = array_merge($errors, $groupResult['errors']);
                continue;
            }

            $warnings = array_merge($warnings, $groupResult['warnings']);

            if ($dryRun) {
                $imported++;
                continue;
            }

            try {
                DB::transaction(function () use ($accommodationCode, $accommodationRows, &$imported, &$warnings) {
                    $persistWarnings = $this->persistAccommodationGroup($accommodationCode, $accommodationRows);
                    $warnings = array_merge($warnings, $persistWarnings);
                    $imported++;
                });
            } catch (\Throwable $e) {
                $errors[] = "اقامتگاه «{$accommodationCode}»: {$e->getMessage()}";
            }
        }

        return $this->result(empty($errors), $imported, $errors, $warnings, [
            'accommodations' => count($grouped),
            'rows'         => count($rows),
        ]);
    }

    /**
     * @return list<array{row:int, data:array<string, string>}>
     */
    public function parseFile(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $firstLine = $this->stripBom($firstLine);
        $delimiter = $this->detectDelimiter($firstLine);
        $headers = str_getcsv(trim($firstLine), $delimiter);
        $headers = array_map(fn ($h) => $this->normalizeHeader((string) $h), $headers);

        $missing = array_diff($this->requiredColumns, $headers);
        if (!empty($missing)) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'ستون‌های الزامی یافت نشد: ' . implode('، ', $missing)
            );
        }

        $this->columnMap = array_flip($headers);
        $rows = [];
        $lineNumber = 1;

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $values = str_getcsv($line, $delimiter);
            if ($this->isBlankRow($values)) {
                continue;
            }

            $data = [];
            foreach ($this->columnMap as $column => $index) {
                $data[$column] = trim((string) ($values[$index] ?? ''));
            }

            $rows[] = ['row' => $lineNumber, 'data' => $data];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param list<array{row:int, data:array<string, string>}> $rows
     * @return array<string, list<array{row:int, data:array<string, string>}>>
     */
    private function groupRows(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $code = $row['data']['accommodation_code'] ?? '';
            if ($code === '') {
                continue;
            }
            $grouped[$code][] = $row;
        }

        return $grouped;
    }

    /**
     * @param list<array{row:int, data:array<string, string>}> $rows
     * @return array{errors: list<string>, warnings: list<string>}
     */
    private function validateAccommodationGroup(string $accommodationCode, array $rows): array
    {
        $errors = [];
        $warnings = [];
        $first = $rows[0]['data'];
        $firstRow = $rows[0]['row'];

        foreach (['province_name', 'city_name', 'accommodation_name', 'type', 'management_status'] as $field) {
            if (($first[$field] ?? '') === '') {
                $errors[] = "ردیف {$firstRow}: فیلد «{$field}» برای اقامتگاه «{$accommodationCode}» الزامی است.";
            }
        }

        if (!$this->locations->findCityId($first['province_name'] ?? '', $first['city_name'] ?? '')) {
            $warnings[] = "ردیف {$firstRow}: استان «{$first['province_name']}» و شهر «{$first['city_name']}» در سیستم نیست و هنگام درون‌ریزی اضافه خواهد شد.";
        }

        $type = $this->normalizeType($first['type'] ?? '');
        if ($type === null && trim($first['type'] ?? '') !== '') {
            $warnings[] = "ردیف {$firstRow}: نوع «{$first['type']}» در سیستم نیست و هنگام درون‌ریزی اضافه خواهد شد.";
        } elseif ($type === null) {
            $errors[] = "ردیف {$firstRow}: نوع اقامتگاه «{$first['type']}» معتبر نیست.";
        }

        $management = $this->normalizeManagementStatus($first['management_status'] ?? '');
        if ($management === null) {
            $errors[] = "ردیف {$firstRow}: وضعیت اداره «{$first['management_status']}» معتبر نیست.";
        }

        if ($this->toInt($first['price_per_night'] ?? '') === null || $this->toInt($first['price_per_night'] ?? '') < 0) {
            $errors[] = "ردیف {$firstRow}: قیمت پایه اقامتگاه معتبر نیست.";
        }

        if (($cap = $this->toInt($first['capacity'] ?? '')) === null || $cap < 1) {
            $errors[] = "ردیف {$firstRow}: ظرفیت اقامتگاه باید حداقل ۱ باشد.";
        }

        if (($rooms = $this->toInt($first['rooms'] ?? '')) === null || $rooms < 1) {
            $errors[] = "ردیف {$firstRow}: تعداد اتاق اقامتگاه باید حداقل ۱ باشد.";
        }

        $hostMobile = trim($first['host_mobile'] ?? '');
        if ($hostMobile !== '' && !User::where('mobile', $hostMobile)->role('host')->exists()) {
            $errors[] = "ردیف {$firstRow}: میزبان با شماره «{$hostMobile}» یافت نشد.";
        }

        foreach ($this->parsePhones($first['phones'] ?? '') as $phoneIndex => $phone) {
            if (!$this->isValidPhone($phone['type'], $phone['number'])) {
                $errors[] = "ردیف {$firstRow}: شماره تماس " . ($phoneIndex + 1) . " معتبر نیست.";
            }
        }

        $accommodationFields = [
            'province_name', 'city_name', 'accommodation_name', 'type', 'management_status',
            'price_per_night', 'capacity', 'rooms', 'address', 'lat', 'lng', 'description',
            'amenities', 'image', 'is_active', 'host_mobile', 'phones',
        ];

        $rateKeys = [];
        /** @var array<string, array<string, string>> $roomMeta */
        $roomMeta = [];
        $roomFields = [
            'room_name', 'room_description', 'bed_type', 'room_capacity',
            'extra_capacity', 'extra_capacity_price', 'size_sqm', 'smoking',
            'has_private_bathroom', 'room_count', 'sort_order', 'room_amenities', 'room_is_active',
        ];

        foreach ($rows as $row) {
            $data = $row['data'];
            $line = $row['row'];

            foreach ($accommodationFields as $field) {
                if (($data[$field] ?? '') !== ($first[$field] ?? '')) {
                    $errors[] = "ردیف {$line}: فیلد «{$field}» با ردیف اول اقامتگاه «{$accommodationCode}» یکسان نیست.";
                    break;
                }
            }

            $roomCode = $data['room_code'] ?? '';
            if ($roomCode === '') {
                $errors[] = "ردیف {$line}: کد اتاق الزامی است.";
                continue;
            }

            if ($data['room_name'] === '') {
                $errors[] = "ردیف {$line}: نام اتاق الزامی است.";
            }

            if (($roomCap = $this->toInt($data['room_capacity'] ?? '')) === null || $roomCap < 1) {
                $errors[] = "ردیف {$line}: ظرفیت اتاق باید حداقل ۱ باشد.";
            }

            $extraCap = $this->toInt($data['extra_capacity'] ?? '');
            if ($extraCap !== null && $extraCap > 0) {
                $extraPrice = $this->toInt($data['extra_capacity_price'] ?? '');
                if ($extraPrice === null) {
                    $errors[] = "ردیف {$line}: با وجود ظرفیت اضافه، قیمت نفر اضافه الزامی است.";
                }
            }

            if (!isset($roomMeta[$roomCode])) {
                $roomMeta[$roomCode] = array_intersect_key($data, array_flip($roomFields));
            } else {
                foreach ($roomFields as $field) {
                    if (($data[$field] ?? '') !== ($roomMeta[$roomCode][$field] ?? '')) {
                        $errors[] = "ردیف {$line}: مشخصات اتاق «{$roomCode}» با ردیف‌های قبلی یکسان نیست.";
                        break;
                    }
                }
            }

            if ($data['rate_name'] === '') {
                $errors[] = "ردیف {$line}: نام تعرفه الزامی است.";
            }

            $ratePrice = $this->toInt($data['rate_price_per_night'] ?? '');
            if ($ratePrice === null || $ratePrice < 1) {
                $errors[] = "ردیف {$line}: قیمت تعرفه باید بزرگ‌تر از صفر باشد.";
            }

            if ($this->normalizeCancellationPolicy($data['cancellation_policy'] ?? '') === null) {
                $errors[] = "ردیف {$line}: سیاست لغو «{$data['cancellation_policy']}» معتبر نیست.";
            }

            if ($this->normalizePaymentType($data['payment_type'] ?? '') === null) {
                $errors[] = "ردیف {$line}: نوع پرداخت «{$data['payment_type']}» معتبر نیست.";
            }

            $rateKey = $roomCode . '::' . $data['rate_name'];
            if (isset($rateKeys[$rateKey])) {
                $errors[] = "ردیف {$line}: تعرفه «{$data['rate_name']}» برای اتاق «{$roomCode}» تکراری است (ردیف {$rateKeys[$rateKey]}).";
            } else {
                $rateKeys[$rateKey] = $line;
            }
        }

        return [
            'errors'   => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param list<array{row:int, data:array<string, string>}> $rows
     * @return list<string>
     */
    private function persistAccommodationGroup(string $accommodationCode, array $rows): array
    {
        $warnings = [];
        $first = $rows[0]['data'];

        $location = $this->locations->resolveOrCreateCity(
            $first['province_name'],
            $first['city_name']
        );

        if ($location['province_created']) {
            $warnings[] = "استان «{$location['province_name']}» به فهرست اضافه شد.";
        }
        if ($location['city_created']) {
            $warnings[] = "شهر «{$location['city_name']}» در استان «{$location['province_name']}» به فهرست اضافه شد.";
        }

        $cityId = $location['id'];

        $typeLabel = trim($first['type']);
        $typeExisted = $this->normalizeType($typeLabel) !== null
            || AccommodationType::where('label', $typeLabel)->exists();
        $typeKey = $this->resolveTypeKey($typeLabel);
        if (!$typeExisted) {
            $warnings[] = "نوع اقامتگاه «{$typeLabel}» به فهرست اضافه شد.";
        }

        $hostId = null;
        $hostMobile = trim($first['host_mobile'] ?? '');
        if ($hostMobile !== '') {
            $hostId = User::where('mobile', $hostMobile)->role('host')->value('id');
        }

        $accommodation = Accommodation::create([
            'city_id'            => $cityId,
            'host_id'            => $hostId,
            'name'               => $first['accommodation_name'],
            'description'        => $first['description'] ?: null,
            'type'               => $typeKey,
            'management_status'  => $this->normalizeManagementStatus($first['management_status']),
            'price_per_night'    => (int) $this->toInt($first['price_per_night']),
            'capacity'           => (int) $this->toInt($first['capacity']),
            'rooms'              => (int) $this->toInt($first['rooms']),
            'address'            => $first['address'] ?: null,
            'phone_numbers'      => $this->parsePhones($first['phones'] ?? '') ?: null,
            'lat'                => $this->toFloat($first['lat'] ?? ''),
            'lng'                => $this->toFloat($first['lng'] ?? ''),
            'amenities'          => $this->parseList($first['amenities'] ?? ''),
            'image'              => $first['image'] ?: null,
            'images'             => [],
            'is_active'          => $this->toBool($first['is_active'] ?? '1', true),
        ]);

        if ($hostId) {
            $accommodation->grantHostAccess(User::find($hostId));
        }

        $roomRows = [];
        foreach ($rows as $row) {
            $roomCode = $row['data']['room_code'];
            $roomRows[$roomCode][] = $row;
        }

        foreach ($roomRows as $roomCode => $rateRows) {
            $roomData = $rateRows[0]['data'];

            $roomType = $accommodation->roomTypes()->create([
                'name'                 => $roomData['room_name'],
                'description'          => $roomData['room_description'] ?: null,
                'bed_type'             => $roomData['bed_type'] ?: null,
                'capacity'             => (int) $this->toInt($roomData['room_capacity']),
                'extra_capacity'       => ($ec = $this->toInt($roomData['extra_capacity'] ?? '')) > 0 ? $ec : null,
                'extra_capacity_price' => ($ec = $this->toInt($roomData['extra_capacity'] ?? '')) > 0
                    ? (int) $this->toInt($roomData['extra_capacity_price'] ?? '0')
                    : null,
                'size_sqm'             => $this->toFloat($roomData['size_sqm'] ?? ''),
                'smoking'              => $this->toBool($roomData['smoking'] ?? '0', false),
                'has_private_bathroom' => $this->toBool($roomData['has_private_bathroom'] ?? '1', true),
                'images'               => [],
                'amenities'            => $this->parseList($roomData['room_amenities'] ?? ''),
                'room_count'           => $this->roomCountFromData($roomData),
                'sort_order'           => (int) ($this->toInt($roomData['sort_order'] ?? '0') ?? 0),
                'is_active'            => $this->toBool($roomData['room_is_active'] ?? '1', true),
            ]);

            foreach ($rateRows as $rateRow) {
                $rateData = $rateRow['data'];

                $roomType->rates()->create([
                    'name'                       => $rateData['rate_name'],
                    'price_per_night'            => (int) $this->toInt($rateData['rate_price_per_night']),
                    'breakfast_included'         => $this->toBool($rateData['breakfast_included'] ?? '0', false),
                    'breakfast_price_per_person' => $this->toInt($rateData['breakfast_price_per_person'] ?? '') ?: null,
                    'cancellation_policy'        => $this->normalizeCancellationPolicy($rateData['cancellation_policy']),
                    'payment_type'               => $this->normalizePaymentType($rateData['payment_type']),
                    'is_active'                  => $this->toBool($rateData['rate_is_active'] ?? '1', true),
                ]);
            }
        }

        return $warnings;
    }

    private function resolveTypeKey(string $value): string
    {
        return $this->normalizeType($value)
            ?? AccommodationType::findOrCreateByLabel($value)->key;
    }

    /**
     * @param array<string, string> $data
     */
    private function roomCountFromData(array $data): int
    {
        $count = $this->toInt($data['room_count'] ?? '');

        return ($count === null || $count < 1) ? 1 : $count;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = $this->stripBom($header);
        $header = Str::lower($header);
        $header = str_replace([' ', '-'], '_', $header);

        $aliases = [
            'کد_اقامتگاه' => 'accommodation_code',
            'نام_استان' => 'province_name',
            'نام_شهر' => 'city_name',
            'نام_اقامتگاه' => 'accommodation_name',
            'نوع' => 'type',
            'وضعیت_اداره' => 'management_status',
            'قیمت_پایه' => 'price_per_night',
            'ظرفیت' => 'capacity',
            'تعداد_اتاق_اقامتگاه' => 'rooms',
            'آدرس' => 'address',
            'عرض_جغرافیایی' => 'lat',
            'طول_جغرافیایی' => 'lng',
            'توضیحات' => 'description',
            'امکانات' => 'amenities',
            'تصویر' => 'image',
            'فعال' => 'is_active',
            'موبایل_میزبان' => 'host_mobile',
            'شماره_تماس' => 'phones',
            'کد_اتاق' => 'room_code',
            'نام_اتاق' => 'room_name',
            'توضیحات_اتاق' => 'room_description',
            'نوع_تخت' => 'bed_type',
            'ظرفیت_اتاق' => 'room_capacity',
            'ظرفیت_اضافه' => 'extra_capacity',
            'قیمت_نفر_اضافه' => 'extra_capacity_price',
            'متراژ' => 'size_sqm',
            'سیگاری' => 'smoking',
            'حمام_اختصاصی' => 'has_private_bathroom',
            'تعداد_اتاق' => 'room_count',
            'ترتیب_نمایش' => 'sort_order',
            'امکانات_اتاق' => 'room_amenities',
            'اتاق_فعال' => 'room_is_active',
            'نام_تعرفه' => 'rate_name',
            'قیمت_تعرفه' => 'rate_price_per_night',
            'صبحانه_رایگان' => 'breakfast_included',
            'قیمت_صبحانه' => 'breakfast_price_per_person',
            'سیاست_لغو' => 'cancellation_policy',
            'نوع_پرداخت' => 'payment_type',
            'تعرفه_فعال' => 'rate_is_active',
        ];

        return $aliases[$header] ?? $header;
    }

    private function normalizeType(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $byKey = AccommodationType::where('key', Str::lower($raw))->value('key');
        if ($byKey) {
            return $byKey;
        }

        $byLabel = AccommodationType::where('label', $raw)->value('key');
        if ($byLabel) {
            return $byLabel;
        }

        $value = Str::lower($raw);

        return match ($value) {
            'hotel', 'هتل' => 'hotel',
            'villa', 'ویلا' => 'villa',
            'apartment', 'آپارتمان', 'اپارتمان' => 'apartment',
            'hostel', 'هاستل' => 'hostel',
            'traditional', 'سنتی', 'اقامتگاه_سنتی', 'اقامتگاه سنتی' => 'traditional',
            default => null,
        };
    }

    private function normalizeManagementStatus(?string $value): ?string
    {
        $value = $this->normalizeFa(trim((string) $value));

        return match ($value) {
            'outsourced', 'برون‌سپاری', 'برونسپاری' => Accommodation::MANAGEMENT_OUTSOURCED,
            'self_governing', 'خودگردان' => Accommodation::MANAGEMENT_SELF_GOVERNING,
            default => null,
        };
    }

    private function normalizeCancellationPolicy(?string $value): ?string
    {
        $value = $this->normalizeFa(trim((string) $value));

        return match ($value) {
            'free', 'لغو_رایگان', 'لغو رایگان' => 'free',
            'non_refundable', 'غیر_قابل_استرداد', 'غیر قابل استرداد' => 'non_refundable',
            default => null,
        };
    }

    private function normalizePaymentType(?string $value): ?string
    {
        $value = $this->normalizeFa(trim((string) $value));

        return match ($value) {
            'pay_at_hotel', 'پرداخت_در_محل', 'پرداخت در محل' => 'pay_at_hotel',
            'prepay_online', 'پرداخت_آنلاین', 'پرداخت آنلاین' => 'prepay_online',
            default => null,
        };
    }

    /**
     * @return list<array{number:string, type:string, note:?string}>
     */
    private function parsePhones(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $phones = [];
        foreach (explode('|', $raw) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $segments = array_map('trim', explode(':', $part, 3));
            if (count($segments) < 2) {
                continue;
            }

            [$type, $number] = $segments;
            $note = $segments[2] ?? null;
            $type = Str::lower($type);
            $type = match ($type) {
                'mobile', 'همراه', 'موبایل' => 'mobile',
                'landline', 'ثابت' => 'landline',
                default => $type,
            };

            $phones[] = [
                'number' => $number,
                'type'   => $type,
                'note'   => $note ?: null,
            ];
        }

        return $phones;
    }

    /**
     * @return list<string>
     */
    private function parseList(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }

        $separator = str_contains($raw, '|') ? '|' : ',';

        return array_values(array_filter(array_map('trim', explode($separator, $raw))));
    }

    private function isValidPhone(string $type, string $number): bool
    {
        return match ($type) {
            'mobile' => (bool) preg_match('/^09[0-9]{9}$/', $number),
            'landline' => (bool) preg_match('/^0[1-9][0-9]{8,10}$/', $number),
            default => false,
        };
    }

    private function toBool(string $value, bool $default): bool
    {
        $value = $this->normalizeFa(trim($value));
        if ($value === '') {
            return $default;
        }

        return in_array($value, ['1', 'true', 'yes', 'بله', 'فعال'], true);
    }

    private function toInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = str_replace([',', ' '], '', $value);

        return is_numeric($value) ? (int) $value : null;
    }

    private function toFloat(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = str_replace([',', ' '], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function detectDelimiter(string $line): string
    {
        $comma = substr_count($line, ',');
        $semi = substr_count($line, ';');

        return $semi > $comma ? ';' : ',';
    }

    private function stripBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }

    private function normalizeFa(string $value): string
    {
        return $this->locations->normalizeFa($value);
    }

    /**
     * @param list<string> $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param array<string, int> $summary
     * @return array{success: bool, imported: int, errors: list<string>, warnings: list<string>, summary: array<string, int>}
     */
    private function result(bool $success, int $imported, array $errors, array $warnings, array $summary = []): array
    {
        return [
            'success'  => $success,
            'imported' => $imported,
            'errors'   => $errors,
            'warnings' => $warnings,
            'summary'  => $summary,
        ];
    }
}
