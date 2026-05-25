<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use App\Notifications\WelcomeNotification;

class SendWelcomeNotificationListener
{
    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->notify(new WelcomeNotification());
    }
}