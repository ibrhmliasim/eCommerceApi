<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            [
                'id'   => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        $verificationUrl = config('app.frontend_url')
            . '/auth/verify-email?verify_url='
            . urlencode($backendUrl);

        return (new MailMessage)
            ->subject('Verify your email — Plush')
            ->line('Please verify your email to unlock checkout.')
            ->action('Verify my email', $verificationUrl)
            ->line('This link expires in 10 minutes.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}