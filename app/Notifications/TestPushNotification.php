<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class TestPushNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Notifikasi uji SIGAP-Alkes')
            ->body('Notifikasi push pada perangkat ini aktif dan siap menerima peringatan alat kesehatan.')
            ->success()
            ->getDatabaseMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Notifikasi uji SIGAP-Alkes',
            'body' => 'Notifikasi push pada perangkat ini aktif dan siap menerima peringatan alat kesehatan.',
            'url' => url('/admin'),
            'tag' => 'sigap-test-push',
            'requireInteraction' => false,
            'actions' => [
                ['action' => 'open', 'title' => 'Buka SIGAP-Alkes'],
            ],
        ];
    }
}
