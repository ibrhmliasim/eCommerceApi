<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_request_password_reset(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'test@test.com']);

        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'test@test.com',
        ])
        ->assertOk()
        ->assertJson(['message' => __('auth.reset_link_sent')]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_points_to_spa(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'test@test.com']);

        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'test@test.com',
        ]);

        Notification::assertSentTo(
            $user,
            ResetPassword::class,
            function (ResetPassword $notification) use ($user): bool {
                $url = $notification->toMail($user)->actionUrl;

                $this->assertStringStartsWith(config('app.frontend_url'), $url);
                $this->assertStringContainsString('/reset-password', $url);
                $this->assertStringContainsString('token=', $url);
                $this->assertStringContainsString('email=', $url);

                return true;
            }
        );
    }

    public function test_user_can_reset_password(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();
    }

    public function test_user_fails_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'test@test.com']);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => 'invalid-token-123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertStatus(422);
    }
}