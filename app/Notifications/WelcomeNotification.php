<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to Plush!')
            ->line('We\'re glad to have you here.')
            ->line('You can verify your email anytime from your account page to unlock checkout.')
            ->action('Go to Account', config('app.frontend_url') . '/account');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}