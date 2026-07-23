<?php

namespace App\Models;

use App\Models\Concerns\DisplaysGuestIdentity;
use Illuminate\Database\Eloquent\Model;

class BookingGuestDetail extends Model
{
    use DisplaysGuestIdentity;

    /** Stored relation value for the primary guest (display label: {@see RELATION_MAIN_GUEST_LABEL}). */
    public const RELATION_MAIN_GUEST = 'رزرو‌کننده';

    public const RELATION_MAIN_GUEST_LABEL = 'مهمان اصلی';

    /** @var list<string> */
    public const RELATION_OPTIONS = [
        'همسر', 'پدر', 'مادر', 'فرزند', 'خواهر', 'برادر', 'دوست', 'همکار', 'غیره',
    ];

    protected $fillable = [
        'booking_id', 'booking_room_id', 'sort_order', 'full_name', 'national_id',
        'is_foreign_guest', 'passport_number', 'country_id', 'residence_city_id',
        'mobile', 'relation',
        'excluded_from_veteran_discount', 'manual_discount_percentage', 'manual_discount_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_foreign_guest' => 'boolean',
            'excluded_from_veteran_discount' => 'boolean',
            'manual_discount_percentage'      => 'integer',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function residenceCity()
    {
        return $this->belongsTo(ResidenceCity::class);
    }

    public function relationLabel(): string
    {
        return self::formatRelationLabel($this->relation);
    }

    public static function formatRelationLabel(?string $relation): string
    {
        if ($relation === self::RELATION_MAIN_GUEST) {
            return self::RELATION_MAIN_GUEST_LABEL;
        }

        return $relation ?? '';
    }

    public static function isGenericGuestName(?string $name, int $sortOrder): bool
    {
        $name = trim((string) $name);

        return $name === '' || $name === 'مهمان ' . ($sortOrder + 1);
    }
}
