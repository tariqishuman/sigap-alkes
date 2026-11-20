<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyInfoDigestNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(public array $lines) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Ringkasan Harian Info SIGAP-Alkes — '.today()->translatedFormat('d F Y'))
            ->greeting('Halo '.($notifiable->name ?? 'Pengguna').',')
            ->line('Berikut ringkasan informasi harian pemeliharaan dan operasional alat kesehatan (tanpa data pasien & tanpa rincian biaya):');

        foreach ($this->lines as $line) {
            $mail->line('• '.$line);
        }

        return $mail->action('Buka SIGAP-Alkes', url('/admin'));
    }
}
