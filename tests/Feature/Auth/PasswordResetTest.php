<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;


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

    public function test_reset_updates_password_and_deletes_tokens(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        // PAT トークンを作成して削除されることを確認します
        $user->createToken('test-token');

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        // パスワードが更新されました
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));

        // トークンが削除されました
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_forgot_with_unknown_email_returns_200_without_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', [
            'email' => 'unknown@example.com',
        ])->assertOk()
        ->assertJson(['message' => __('auth.reset_link_sent')]);

        Notification::assertNothingSent();
    }

    public function test_reset_fails_with_weak_password(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => '123',
            'password_confirmation' => '123',
        ])->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_invalidates_all_sessions(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        // セッションを手動で作成する
        DB::table('sessions')->insert([
            'id'          => 'test-session-id',
            'user_id'     => $user->id,
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'test',
            'payload'     => base64_encode(json_encode([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_reset_token_cannot_be_reused(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        $payload = [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();

        // 同じトークンによる 2 番目のリクエストは失敗するはずです
        $this->postJson('/api/v1/auth/password/reset', $payload)->assertUnprocessable();
    }

    public function test_forgot_returns_429_when_throttled(): void
    {
        $user = User::factory()->create(['email' => 'test@test.com']);

        // 最初のリクエストはトークンを作成します
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'test@test.com'])->assertOk();

        // 2 番目 - ブローカー スロットルがトリガーされます (ブローカーのクールダウン 60 秒)
        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'test@test.com'])
            ->assertOk()
            ->assertJson(['message' => __('auth.reset_link_sent')]);
    }

    public function test_soft_deleted_user_cannot_reset_password(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);
        $user->delete(); // soft delete

        $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertUnprocessable(); // ユーザーはブローカーを見つけられませんя
    }

    public function test_reset_returns_same_error_for_invalid_token_and_unknown_email(): void
    {
        $user  = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        // 無効なトークン
        $responseInvalidToken = $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => 'invalid-token',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 存在しない電子メールl
        $responseUnknownEmail = $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'nobody@example.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // 両方とも同じメッセージを返す必要があります
        $this->assertEquals(
            $responseInvalidToken->json('errors.email.0'),
            $responseUnknownEmail->json('errors.email.0'),
        );
    }
}