<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $verificationUrl) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     * 通知のメール表現を取得します。
     */
    public function toMail(object $notifiable): MailMessage
    {
        
        

        return (new MailMessage)
            ->subject('Welcome to Plush!')
            ->line('Please confirm that you want to use this mail address for your Plush account. Once its done, you will be able to use Plush website!')
            ->action('Verify my mail', $this->verificationUrl)
            ->line('Thank you for using Plush!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
