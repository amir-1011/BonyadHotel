<?php

namespace App\Models;

use App\Models\Concerns\DisplaysGuestIdentity;
use App\Support\VeteranGroups;
use App\Support\HostPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use DisplaysGuestIdentity;
    use HasApiTokens;
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'mobile',
        'password',
        'national_id',
        'is_foreign_guest',
        'passport_number',
        'country_id',
        'residence_city_id',
        'veteran_type',
        'secondary_veteran_type',
        'discount_percentage',
        'host_panel_permissions',
        'host_position_title',
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
            'is_foreign_guest'       => 'boolean',
        ];
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function programBeneficiary()
    {
        return $this->hasOne(ProgramBeneficiary::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function residenceCity()
    {
        return $this->belongsTo(ResidenceCity::class);
    }

    public function beneficiaryBookingCosts()
    {
        return $this->hasMany(BookingBeneficiaryCost::class);
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

    /**
     * @return array<string, list<string>>
     */
    public function effectiveHostPermissionGrants(): array
    {
        if ($this->isAdmin()) {
            return HostPermissions::fullAccessGrants();
        }

        if (!$this->isHost()) {
            return [];
        }

        return HostPermissions::normalizeStored($this->host_panel_permissions);
    }

    /**
     * @return list<string> Enabled module keys (for menu visibility).
     */
    public function effectiveHostPermissions(): array
    {
        return HostPermissions::enabledModulesFromGrants($this->effectiveHostPermissionGrants());
    }

    public function hasHostPanelAccess(string $module): bool
    {
        return HostPermissions::grantsHaveModuleAccess(
            $module,
            $this->effectiveHostPermissionGrants()
        );
    }

    public function hostCan(string $pageKey, string $action): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if (!$this->isHost()) {
            return false;
        }

        return HostPermissions::grantsAllow(
            $pageKey,
            $action,
            $this->effectiveHostPermissionGrants()
        );
    }

    public function hostCanAny(string $pageKey, array $actions): bool
    {
        foreach ($actions as $action) {
            if ($this->hostCan($pageKey, $action)) {
                return true;
            }
        }

        return false;
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

    public function hostRoleLabel(): string
    {
        if (!$this->isHost()) {
            return '';
        }

        return filled($this->host_position_title)
            ? trim((string) $this->host_position_title)
            : 'میزبان';
    }

    public function roleBadgeLabel(?string $roleName = null): string
    {
        $roleName ??= $this->roles->first()?->name;

        return match ($roleName) {
            'host'        => $this->hostRoleLabel(),
            'super_admin' => 'ادمین',
            'guest'       => 'مهمان',
            default       => $roleName ?: 'کاربر',
        };
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

        if ($this->hasHostPanelAccess('dashboard')) {
            return route('host.dashboard');
        }

        $permissions = $this->effectiveHostPermissions();
        $first = $permissions[0] ?? 'dashboard';

        return \App\Support\HostPermissions::landingRoute($first);
    }

    public function veteranLabel(?int $accommodationId = null): string
    {
        return VeteranGroups::labelsForTypes($this->normalizedVeteranTypes($accommodationId), $accommodationId);
    }

    public function accommodationDiscountFor(?int $accommodationId): int
    {
        $types = $this->normalizedVeteranTypes($accommodationId);

        if (empty($types)) {
            return (int) $this->discount_percentage;
        }

        if ($accommodationId) {
            return VeteranGroups::accommodationDiscountForTypes($types, $accommodationId);
        }

        return (int) $this->discount_percentage;
    }

    /**
     * @return array<int, string>
     */
    public function normalizedVeteranTypes(?int $accommodationId = null): array
    {
        $policy = app(\App\Services\VeteranPolicyService::class);

        if ($accommodationId !== null) {
            $policy = $policy->forAccommodation($accommodationId);
        }

        return $policy->normalizeVeteranTypes(
            $this->normalizedVeteranType(),
            $this->normalizedSecondaryVeteranType(),
        );
    }

    public function normalizedVeteranType(): ?string
    {
        if (!$this->veteran_type) {
            return null;
        }

        return app(\App\Services\VeteranPolicyService::class)->normalizeKey($this->veteran_type) ?? $this->veteran_type;
    }

    public function normalizedSecondaryVeteranType(): ?string
    {
        if (!$this->secondary_veteran_type) {
            return null;
        }

        return app(\App\Services\VeteranPolicyService::class)->normalizeKey($this->secondary_veteran_type)
            ?? $this->secondary_veteran_type;
    }

    public function hasVeteranGroup(): bool
    {
        return $this->veteran_type || $this->secondary_veteran_type;
    }
}
