<?php

namespace App\Providers;

use App\Listeners\SendWelcomeNotificationListener;
use App\Listeners\SendVerifyEmailNotificationListener;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;


use Illuminate\Auth\Notifications\ResetPassword;
 

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Registered::class, SendWelcomeNotificationListener::class);
        Event::listen(Registered::class, SendVerifyEmailNotificationListener::class);
    }
}
