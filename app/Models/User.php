<?php

namespace App\Models;

use App\Support\VeteranGroups;
use App\Support\HostPermissions;
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
        'password',
        'national_id',
        'veteran_type',
        'discount_percentage',
        'host_panel_permissions',
        'mobile_verified_at',
        'national_id_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'               => 'hashed',
            'mobile_verified_at'     => 'datetime',
            'national_id_verified_at'=> 'datetime',
            'discount_percentage'    => 'integer',
            'host_panel_permissions' => 'array',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function accommodations()
    {
        return $this->belongsToMany(Accommodation::class, 'accommodation_host')
            ->withTimestamps()
            ->select('accommodations.*');
    }

    public function managedAccommodationIds(): \Illuminate\Support\Collection
    {
        return $this->accommodations()->pluck('accommodations.id');
    }

    public function managesAccommodation(int|Accommodation $accommodation): bool
    {
        $id = $accommodation instanceof Accommodation ? $accommodation->id : $accommodation;

        return $this->accommodations()->where('accommodations.id', $id)->exists();
    }

    public function managedAccommodationOptions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->accommodations()
            ->select('accommodations.id', 'accommodations.name')
            ->orderBy('accommodations.name')
            ->get();
    }

    public function effectiveHostPermissions(): array
    {
        if ($this->isAdmin()) {
            return HostPermissions::defaults();
        }

        if (!$this->isHost()) {
            return [];
        }

        $stored = $this->host_panel_permissions;

        if ($stored === null) {
            return HostPermissions::defaults();
        }

        return array_values(array_intersect($stored, HostPermissions::keys()));
    }

    public function hasHostPanelAccess(string $permission): bool
    {
        return in_array($permission, $this->effectiveHostPermissions(), true);
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

    public function hasStaffAccess(): bool
    {
        return $this->hasAnyRole(['super_admin', 'host']);
    }

    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    public function staffDashboardUrl(): string
    {
        if ($this->isAdmin()) {
            return route('admin.dashboard');
        }

        $permissions = $this->effectiveHostPermissions();
        $first = $permissions[0] ?? 'dashboard';

        return \App\Support\HostPermissions::landingRoute($first);
    }

    public function veteranLabel(): string
    {
        return VeteranGroups::label($this->veteran_type);
    }

    public function normalizedVeteranType(): ?string
    {
        if (!$this->veteran_type) {
            return null;
        }

        return app(\App\Services\VeteranPolicyService::class)->normalizeKey($this->veteran_type) ?? $this->veteran_type;
    }
}
