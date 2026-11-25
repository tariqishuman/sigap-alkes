<?php

namespace App\Models;

use App\Enums\AlertTier;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'room_id', 'testing_institution_id', 'push_pref_important', 'push_pref_info'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, LogsActivity, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'room_id' => 'integer',
            'testing_institution_id' => 'integer',
            'push_pref_important' => 'boolean',
            'push_pref_info' => 'boolean',
        ];
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Decide whether this user receives Web Push for a given alert tier (Milestone R3 Bagian 6).
     * - Magang never receives push.
     * - Manajemen only receives push for Critical escalations.
     * - Teknisi / Admin IPSRS always receive Critical push; Important & Info follow user preferences.
     */
    public function shouldReceivePushForTier(AlertTier $tier, bool $isCriticalEscalation = false): bool
    {
        $isOperator = $this->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
        ]);

        if (! $isOperator) {
            if ($this->hasAnyRole([UserRole::Magang->value, UserRole::Demo->value])) {
                return false;
            }

            if ($this->hasRole(UserRole::Manajemen->value) && ! $this->hasRole(UserRole::KepalaRuangan->value)) {
                return $tier === AlertTier::Critical && $isCriticalEscalation;
            }
        }

        return match ($tier) {
            AlertTier::Critical => true,
            AlertTier::Important => (bool) ($this->push_pref_important ?? true),
            AlertTier::Info => (bool) ($this->push_pref_info ?? false),
        };
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function testingInstitution(): BelongsTo
    {
        return $this->belongsTo(TestingInstitution::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(array_map(fn (UserRole $r): string => $r->value, UserRole::cases()));
    }

    public function isBadanKalibrasiOnly(): bool
    {
        return $this->hasRole(UserRole::BadanKalibrasi->value)
            && ! $this->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
            ]);
    }

    /**
     * Roles allowed to view acquisition costs, asset book values, and repair costs.
     * Hidden from kepala_ruangan, badan_kalibrasi, and magang.
     */
    public function canViewCosts(): bool
    {
        return $this->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
            UserRole::Manajemen->value,
            UserRole::Demo->value,
        ]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'room_id', 'testing_institution_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
