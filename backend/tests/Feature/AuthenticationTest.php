<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_register_and_receive_an_authenticated_session(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Maya Haddad',
            'email' => 'MAYA@example.com',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'maya@example.com')
            ->assertHeader('X-Request-Id');

        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'maya@example.com']);
    }

    public function test_registration_validation_uses_the_standard_error_shape(): void
    {
        User::factory()->create(['email' => 'maya@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'maya@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['message', 'fields', 'request_id']]);
    }

    public function test_user_can_log_in_fetch_their_profile_and_log_out(): void
    {
        $user = User::factory()->create([
            'email' => 'maya@example.com',
            'password' => 'correct-horse',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'MAYA@example.com',
            'password' => 'correct-horse',
            'remember' => false,
        ])->assertOk()->assertJsonPath('data.id', $user->id);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'maya@example.com');

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_invalid_credentials_do_not_reveal_account_details(): void
    {
        User::factory()->create([
            'email' => 'maya@example.com',
            'password' => 'correct-horse',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'maya@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }

    public function test_unauthenticated_api_requests_use_the_standard_error_shape(): void
    {
        $this->getJson('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonStructure(['error' => ['message', 'request_id']]);
    }
}
