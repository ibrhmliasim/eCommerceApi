<?php

namespace Tests\Feature\Auth;

use App\Models\User;

use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user, 'sanctum')->getJson($url);

        $response->assertStatus(200)
                ->assertJson(['message' => 'Email verified successfully']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_user_cannot_verify_with_invalid_signature(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        // Генерим правильный URL
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $invalidUrl = $url . '&fake=123';

        $response = $this->actingAs($user, 'sanctum')->getJson($invalidUrl);

        $response->assertStatus(403);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend');

        $response->assertStatus(200)
                ->assertJson([
                    'message' => 'Email verification sent'
                ]);

        Notification::assertSentTo($user, VerifyEmail::class);
    }
}
