<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
 
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verify your email — Plush')
            ->line('Please verify your email to unlock checkout.')
            ->action('Verify my email', $url)
            ->line('This link expires soon.');
    }
}