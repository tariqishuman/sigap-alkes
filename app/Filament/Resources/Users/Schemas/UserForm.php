<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Pengguna')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        Select::make('roles')
                            ->label('Role')
                            ->relationship(
                                name: 'roles',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => auth()->user()?->hasRole(UserRole::SuperAdmin->value)
                                    ? $query
                                    : $query->where('name', '!=', UserRole::SuperAdmin->value),
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record): string => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
                            ->disabled(fn (?User $record): bool => $record !== null && $record->hasRole(UserRole::SuperAdmin->value) && auth()->id() === $record->id)
                            ->helperText(fn (?User $record): ?string => ($record !== null && $record->hasRole(UserRole::SuperAdmin->value) && auth()->id() === $record->id)
                                ? 'Role Super Admin pada akun Anda sendiri tidak dapat dilepas.'
                                : null)
                            ->rule(fn (?User $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                $roleIds = is_array($value) ? $value : [$value];
                                $superAdminRoleId = Role::where('name', UserRole::SuperAdmin->value)->value('id');

                                if (! auth()->user()?->hasRole(UserRole::SuperAdmin->value) && in_array($superAdminRoleId, array_map('intval', $roleIds), true)) {
                                    $fail('Hanya Super Admin yang dapat memberikan role Super Admin.');
                                }

                                if ($record !== null && $record->hasRole(UserRole::SuperAdmin->value) && ! in_array($superAdminRoleId, array_map('intval', $roleIds), true)) {
                                    $fail('Role Super Admin tidak boleh dicabut dari akun Super Admin.');
                                }
                            })
                            ->multiple()
                            ->preload()
                            ->required(),
                        Select::make('room_id')
                            ->label('Ruangan (Khusus Kepala Ruangan)')
                            ->relationship('room', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),
            ]);
    }
}
