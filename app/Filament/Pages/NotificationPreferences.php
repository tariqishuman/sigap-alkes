<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Notifications\TestPushNotification;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class NotificationPreferences extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Notifikasi di Perangkat Ini';

    protected static ?string $slug = 'notifikasi-perangkat';

    protected string $view = 'filament.pages.notification-preferences';

    public bool $pushPrefImportant = true;

    public bool $pushPrefInfo = false;

    public function mount(): void
    {
        $user = Auth::user();
        $this->pushPrefImportant = (bool) ($user?->push_pref_important ?? true);
        $this->pushPrefInfo = (bool) ($user?->push_pref_info ?? false);
    }

    public function savePreferences(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->update([
            'push_pref_important' => $this->pushPrefImportant,
            'push_pref_info' => $this->pushPrefInfo,
        ]);

        Notification::make()
            ->success()
            ->title('Preferensi notifikasi disimpan')
            ->send();
    }

    public function sendTestPush(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $user->notify(new TestPushNotification);

        $this->dispatch('sigap-test-push-sent', title: 'Notifikasi uji SIGAP-Alkes', body: 'Notifikasi push pada perangkat ini aktif dan siap menerima peringatan alat kesehatan.', url: url('/admin'));

        Notification::make()
            ->success()
            ->title('Notifikasi uji SIGAP-Alkes')
            ->body('Notifikasi push uji berhasil dikirim ke perangkat ini dan lonceng notifikasi.')
            ->send();
    }

    public function isCriticalLockedOn(): bool
    {
        $user = Auth::user();

        return $user?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
        ]) ?? false;
    }

    public function isMagangOnly(): bool
    {
        $user = Auth::user();

        return $user !== null
            && $user->hasRole(UserRole::Magang->value)
            && ! $user->hasAnyRole([
                UserRole::SuperAdmin->value,
                UserRole::AdminIpsrs->value,
                UserRole::Teknisi->value,
                UserRole::KepalaRuangan->value,
            ]);
    }

    public function getDeviceCount(): int
    {
        return Auth::user()?->pushSubscriptions()->count() ?? 0;
    }
}
