<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountHoldNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your SheZen Harmony account is on hold')
            ->greeting('SheZen Harmony account notice')
            ->line('Your account has been placed on hold because it requires an administrative review.')
            ->line('Open the SheZen Harmony app to view the reason provided by the administrator.')
            ->line('If you believe this is a mistake, please contact the SheZen Harmony support team.');
    }
}
