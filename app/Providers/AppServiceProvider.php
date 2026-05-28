<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;

use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        URL::useOrigin(config('app.url'));
        
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        ResetPassword::createUrlUsing(fn (mixed $user, string $token): string =>
            config('app.frontend_url') . '/auth/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email)
        );

        VerifyEmail::createUrlUsing(function (mixed $notifiable): string {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
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