<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SheZen Harmony Verification Code')
            ->greeting('Verify your USP student email')
            ->line('Your SheZen Harmony verification code is:')
            ->line($this->code)
            ->line("This code expires in {$this->expiresMinutes} minutes.")
            ->line('If you did not request this code, you can ignore this email.');
    }
}
