<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================
    //  Register / 登録
    // =========================================================

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'test@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
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
                         'created_at',
                     ],
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'test@test.com']);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@test.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'email'                 => 'test@test.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
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

    // =========================================================
    //  Login / ログイン
    // =========================================================

    public function test_user_can_login(): void
    {
        User::factory()->create([
            'email'    => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->withHeaders([
            'Accept'  => 'application/json',
            'Referer' => 'http://localhost:3000',
        ])->postJson('/api/v1/auth/login', [
            'email'    => 'test@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['id', 'email'],
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

        // ValidationException → 422, メールフィールドのエラー
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_for_deleted_user(): void
    {
        // SoftDelete — пользователь удалён, но в БД есть
        $user = User::factory()->create([
            'email'    => 'deleted@test.com',
            'password' => bcrypt('password123'),
        ]);
        $user->delete();

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'deleted@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    // =========================================================
    //  Logout / ログアウト
    // =========================================================

    public function test_authenticated_user_can_logout(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
                    ->withSession([])
                    ->actingAs($user, 'web')
                    ->withHeaders([
                         'Accept'  => 'application/json',
                         'Referer' => 'http://localhost:3000',
                     ])
                     ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully']);
        
        $this->assertGuest('web');         
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }

    // =========================================================
    //  Me / プロフィール
    // =========================================================

    public function test_authenticated_user_can_get_profile(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)
                         ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => ['id', 'email'],
                 ]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}