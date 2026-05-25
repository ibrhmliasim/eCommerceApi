<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (mixed $user, string $token): string {
            return config('app.frontend_url') . "/reset-password?token={$token}&email={$user->email}";
        });

        VerifyEmail::createUrlUsing(function (mixed $notifiable): string {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(10),
                [
                    'id'   => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );
    
            $query = parse_url($signedUrl, PHP_URL_QUERY);
    
            return config('app.frontend_url') . '/auth/verify-email?' . $query;
        });
    }
}