<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;

use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $user->id,
                'hash' => sha1($user->email),
            ]
        );
    }

    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->postJson($this->makeVerificationUrl($user));

        $response->assertOk()
                 ->assertJson(['message' => __('auth.email_verified')]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_cannot_verify_with_invalid_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = $this->makeVerificationUrl($user) . '&fake=123';

        $this->postJson($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_user_gets_appropriate_response(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->postJson($this->makeVerificationUrl($user))
             ->assertStatus(409)
             ->assertJson(['message' => __('auth.email_already_verified')]);
    }

    public function test_already_verified_user_cannot_resend(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend');

        $response->assertStatus(409)
            ->assertJson(['message' => __('auth.email_already_verified')]);

        Notification::assertNothingSent();
    }

    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend');

        $response->assertOk()
                 ->assertJson(['message' => __('auth.verification_sent')]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_verification_email_contains_spa_url_format(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $user->notify(new VerifyEmailNotification());

        Notification::assertSentTo(
            $user,
            VerifyEmailNotification::class,
            function (VerifyEmailNotification $notification) use ($user): bool {
                $mail = $notification->toMail($user);
                $url = $mail->actionUrl;

                // URL はバックエンドではなく SPA につながります
                $this->assertStringStartsWith(config('app.frontend_url'), $url);
                $this->assertStringContainsString('/auth/verify-email', $url);

                // パラメータはクエリ文字列を介して渡されます
                $this->assertStringContainsString('id=' . $user->id, $url);
                $this->assertStringContainsString('hash=', $url);
                $this->assertStringContainsString('signature=', $url);
                $this->assertStringContainsString('expires=', $url);

                $this->assertStringNotContainsString('verify_url=', $url);

                return true;
            }
        );
    }

    public function test_expired_signature_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            [
                'id'   => $user->id,
                'hash' => sha1($user->email),
            ]
        );

        $this->postJson($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $user->id,
                'hash' => sha1('wrong@email.com'),
            ]
        );

        $this->postJson($url)
            ->assertForbidden()
            ->assertJson(['message' => __('auth.invalid_verification_link')]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_soft_deleted_user_cannot_verify(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = $this->makeVerificationUrl($user);

        $user->delete();

        $this->postJson($url)
            ->assertForbidden()
            ->assertJson(['message' => __('auth.invalid_verification_link')]);
    }

    public function test_unauthenticated_user_cannot_resend(): void
    {
        $this->postJson('/api/v1/auth/email/resend')
            ->assertUnauthorized();
    }

    public function test_wrong_id_is_rejected(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => 99999,
                'hash' => sha1($user->email),
            ]
        );

        $this->postJson($url)
            ->assertForbidden()
            ->assertJson(['message' => __('auth.invalid_verification_link')]);
    }

    public function test_verified_event_is_fired_on_successful_verification(): void
    {
        Event::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->postJson($this->makeVerificationUrl($user))
            ->assertOk();

        Event::assertDispatched(Verified::class, function (Verified $event) use ($user): bool {
            return $event->user->id === $user->id;
        });
    }

    public function test_user_cannot_verify_with_tampered_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = $this->makeVerificationUrl($user);

        // 署名を無効なものに置き換えます
        $tampered = preg_replace('/signature=[^&]+/', 'signature=invalidsignature', $url);

        $this->postJson($tampered)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }
}
