<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'mobile',
        'national_id',
        'veteran_type',
        'discount_percentage',
        'mobile_verified_at',
        'national_id_verified_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'mobile_verified_at'     => 'datetime',
            'national_id_verified_at'=> 'datetime',
            'discount_percentage'    => 'integer',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function accommodations()
    {
        return $this->hasMany(Accommodation::class, 'host_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(Accommodation::class, 'user_favorites')->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isHost(): bool
    {
        return $this->hasRole('host');
    }

    public function veteranLabel(): string
    {
        return match($this->veteran_type) {
            'martyr_family'          => 'خانواده شهید',
            'veteran_25_49'          => 'جانباز ۲۵ تا ۴۹ درصد',
            'veteran_50_69'          => 'جانباز ۵۰ تا ۶۹ درصد',
            'veteran_70_plus'        => 'جانباز ۷۰ درصد و بالاتر',
            'freed_prisoner_family'  => 'خانواده آزاده',
            default                  => 'کاربر عادی',
        };
    }
}
