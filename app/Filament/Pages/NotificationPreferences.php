<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\PushSubscription;
use App\Notifications\TestPushNotification;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
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

        $deviceCount = $user->pushSubscriptions()->count();
        $user->notify(new TestPushNotification);

        $body = $deviceCount > 0
            ? "Notifikasi push uji berhasil dikirim ke {$deviceCount} perangkat terdaftar dan lonceng notifikasi."
            : 'Notifikasi uji dikirim ke lonceng notifikasi. Aktifkan notifikasi di perangkat ini terlebih dahulu untuk menguji Web Push.';

        Notification::make()
            ->success()
            ->title('Notifikasi uji SIGAP-Alkes')
            ->body($body)
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

    /**
     * @return Collection<int, PushSubscription>
     */
    public function getDevices(): Collection
    {
        return Auth::user()?->pushSubscriptions()->latest('updated_at')->get() ?? collect();
    }
}
