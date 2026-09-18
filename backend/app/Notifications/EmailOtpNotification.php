<?php

namespace App\Notifications;

use App\Models\EmailOtpChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly int $expiresMinutes,
        public readonly string $purpose,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->purpose === EmailOtpChallenge::PURPOSE_PASSWORD_RESET) {
            return (new MailMessage)
                ->subject('SheZen Harmony Password Reset Code')
                ->greeting('Reset your SheZen Harmony password')
                ->line('Your password reset code is:')
                ->line($this->code)
                ->line("This code expires in {$this->expiresMinutes} minutes.")
                ->line('If you did not request a password reset, you can ignore this email.');
        }

        return (new MailMessage)
            ->subject('SheZen Harmony Verification Code')
            ->greeting('Verify your USP student email')
            ->line('Your SheZen Harmony verification code is:')
            ->line($this->code)
            ->line("This code expires in {$this->expiresMinutes} minutes.")
            ->line('If you did not request this code, you can ignore this email.');
    }
}
