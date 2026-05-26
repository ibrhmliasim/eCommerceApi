<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

// ShouldQueue と Queueable は削除されます - Notificationは同期的に送信されます 
class VerifyEmailNotification extends VerifyEmail
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify your email — Plush')
            ->line('Please verify your email to unlock checkout.')
            ->action('Verify my email', $url)
            ->line('This link expires in 10 minutes.');
    }
}