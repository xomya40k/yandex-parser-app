<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
        ]);

        $response = $this->postJsonAsSpa('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
        ]);

        $response = $this->postJsonAsSpa('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->postJsonAsSpa('/api/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_rejects_short_password(): void
    {
        $this->postJsonAsSpa('/api/login', [
            'email' => fake()->safeEmail(),
            'password' => 'short',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_invalidates_session(): void
    {
        $password = 'password';
        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => $password,
        ]);

        $this->postJsonAsSpa('/api/login', [
            'email' => $user->email,
            'password' => $password,
        ])->assertOk();

        $this->getJsonAsSpa('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->postJsonAsSpa('/api/logout')
            ->assertNoContent();

        $this->getJsonAsSpa('/api/me')->assertUnauthorized();
    }
}
