<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Organization;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = app(UserRepository::class);
    }

    public function test_find_by_id(): void
    {
        $user = User::factory()->create();

        $found = $this->userRepository->findById($user->id);

        $this->assertInstanceOf(User::class, $found);
        $this->assertEquals($user->id, $found->id);
        $this->assertEquals($user->email, $found->email);
    }

    public function test_find_by_id_not_found(): void
    {
        $this->assertNull($this->userRepository->findById(fake()->numberBetween(1000, 9999)));
    }

    public function test_update_current_organization(): void
    {
        $user = User::factory()->create([
            'current_organization_id' => null,
        ]);
        $organization = Organization::factory()->create();

        $this->userRepository->updateCurrentOrganization($user, $organization);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_organization_id' => $organization->id,
        ]);
        $this->assertEquals($organization->id, $user->fresh()->current_organization_id);
    }

    public function test_update_current_organization_replaces_previous(): void
    {
        $previous = Organization::factory()->create();
        $next = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $previous->id,
        ]);

        $this->userRepository->updateCurrentOrganization($user, $next);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_organization_id' => $next->id,
        ]);
        $this->assertEquals($next->id, $user->fresh()->current_organization_id);
    }
}
