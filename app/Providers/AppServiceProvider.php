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

        // クエリ文字列で署名された URL を SPA に直接デプロイします -
        // 署名は有効なままですが、バックエンド URL がリファラー/ログに漏洩しなくなりました
        VerifyEmail::createUrlUsing(function (mixed $notifiable): string {
            $signedUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id'   => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $parsed = parse_url($signedUrl);
            parse_str($parsed['query'], $queryParams);

            // ID とハッシュはパスに存在します: /api/v1/auth/email/verify/{id}/{hash}
            $segments = explode('/', trim($parsed['path'], '/'));
            $hash     = array_pop($segments);
            $id       = array_pop($segments);

            $query = http_build_query([
                'id'        => $id,
                'hash'      => $hash,
                'expires'   => $queryParams['expires'],
                'signature' => $queryParams['signature'],
            ]);

            return config('app.frontend_url') . '/auth/verify-email?' . $query;
        });
    }
}