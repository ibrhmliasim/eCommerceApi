<?php

namespace Tests\Feature\Auth;

use App\Models\User;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Register/登録
     */
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'test@test.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'first_name'            => 'Test',
            'last_name'             => 'Testonia',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                    'data' => [
                        'id',
                        'email',
                        'first_name',
                        'last_name',
                        'role',
                        'created_at'
                    ]
                 ]);

        $this->assertDataBaseHas('users', [
            'email'=> 'test@test.com',
        ]);
    }

    public function test_user_can_register_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@test.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email'                => 'test@test.com',
            'password'             => 'password',
            'password_confirmation'=> 'password',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'    => 'not-an-email',
            'password' => '123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Login/ログイン
     */
    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email'    => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['id', 'email']
                 ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Invalid credentials']);
    }

    /**
     * Me/個人的
     */

    public function test_authenticated_user_can_get_profile(): void
    {
        /** @var \App\Models\User $user */
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)
                         ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['id', 'email']
                 ]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
