<?php

namespace Tests\Feature\Auth;

use App\Models\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Forgot password Test
     */
    public function test_user_can_request_password_reset(): void 
    {
        Notification::fake();

        User::factory()->create([
            'email'    => 'test@test.com',
        ]);

        $response = $this->postJson('/api/v1/auth/password/forgot', [
            'email'                 => 'test@test.com',
        ]);

        $response->assertStatus(200)
             ->assertJson(['message' => 'If this email exists, a reset link has been sent.']);
    }

    /**
     * Reset password Test
     */
    public function test_user_can_reset_password() 
    {
        $user = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => $token,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_fails_with_invalid_token()
    {
        $user = User::factory()->create(['email' => 'test@test.com']);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'email'                 => 'test@test.com',
            'token'                 => 'invalid-token-123',
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }
}
