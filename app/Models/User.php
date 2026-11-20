<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'room_id', 'testing_institution_id'])]
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
        ];
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
