<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_show_returns_404_when_no_current_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertNotFound();
    }

    public function test_store_rejects_invalid_yandex_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organization', [
                'yandex_maps_url' => $this->faker->url(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['yandex_maps_url']);
    }

    public function test_first_store_creates_organization_and_sets_current(): void
    {
        $user = User::factory()->create();
        $url = $this->yandexMapsOrganizationUrl();

        $response = $this->actingAs($user)
            ->postJson('/api/organization', [
                'yandex_maps_url' => $url,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.yandex_maps_url', $url)
            ->assertJsonPath('data.status', OrganizationStatus::Pending->value);

        $organizationId = $response->json('data.id');

        $this->assertDatabaseHas('organizations', [
            'id' => $organizationId,
            'yandex_maps_url' => $url,
            'status' => OrganizationStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'current_organization_id' => $organizationId,
        ]);
    }

    public function test_storing_same_url_reuses_existing_organization(): void
    {
        $user = User::factory()->create();
        $url = $this->yandexMapsOrganizationUrl();
        $existing = Organization::factory()->ready()->create([
            'yandex_maps_url' => $url,
            'name' => $this->faker->company,
            'rating' => $this->faker->randomFloat(2, 1, 5),
            'total_ratings' => $this->faker->numberBetween(1, 100),
            'total_reviews' => $this->faker->numberBetween(1, 50),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/organization', [
                'yandex_maps_url' => $url,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.name', $existing->name)
            ->assertJsonPath('data.status', OrganizationStatus::Ready->value)
            ->assertJsonPath('data.total_reviews', $existing->total_reviews);

        $this->assertSame(1, Organization::query()->count());
        $this->assertSame($existing->id, $user->fresh()->current_organization_id);
    }

    public function test_storing_new_url_creates_another_organization_without_touching_old(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->ready()->create([
            'yandex_maps_url' => $this->yandexMapsOrganizationUrl(),
            'name' => $this->faker->company(),
            'total_reviews' => $this->faker->numberBetween(1, 20),
        ]);

        $user->forceFill(['current_organization_id' => $first->id])->save();

        $newUrl = $this->yandexMapsOrganizationUrl();

        $response = $this->actingAs($user)
            ->postJson('/api/organization', [
                'yandex_maps_url' => $newUrl,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.yandex_maps_url', $newUrl)
            ->assertJsonPath('data.status', OrganizationStatus::Pending->value);

        $secondId = $response->json('data.id');
        $this->assertNotSame($first->id, $secondId);

        $first->refresh();
        $this->assertSame(OrganizationStatus::Ready, $first->status);

        $this->assertSame($secondId, $user->fresh()->current_organization_id);
        $this->assertSame(2, Organization::query()->count());
    }

    public function test_storing_previous_url_again_restores_same_organization(): void
    {
        $user = User::factory()->create();
        $firstUrl = $this->yandexMapsOrganizationUrl();
        $first = Organization::factory()->ready()->create([
            'yandex_maps_url' => $firstUrl,
            'name' => $this->faker->company(),
            'total_reviews' => $this->faker->numberBetween(5, 30),
        ]);
        $second = Organization::factory()->pending()->create([
            'yandex_maps_url' => $this->yandexMapsOrganizationUrl(),
        ]);

        $user->forceFill(['current_organization_id' => $second->id])->save();

        $response = $this->actingAs($user)
            ->postJson('/api/organization', [
                'yandex_maps_url' => $firstUrl,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $first->id)
            ->assertJsonPath('data.name', $first->name)
            ->assertJsonPath('data.total_reviews', $first->total_reviews)
            ->assertJsonPath('data.status', OrganizationStatus::Ready->value);

        $this->assertSame($first->id, $user->fresh()->current_organization_id);
    }

    public function test_show_returns_current_organization(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->ready()->create([
            'yandex_maps_url' => $this->yandexMapsOrganizationUrl(),
            'name' => $this->faker->company(),
        ]);
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        $this->actingAs($user)
            ->getJson('/api/organization')
            ->assertOk()
            ->assertJsonPath('data.id', $organization->id)
            ->assertJsonPath('data.name', $organization->name);
    }

    public function test_organization_endpoints_require_authentication(): void
    {
        $this->getJson('/api/organization')->assertUnauthorized();
        $this->postJson('/api/organization', [
            'yandex_maps_url' => $this->yandexMapsOrganizationUrl(),
        ])->assertUnauthorized();
    }

    private function yandexMapsOrganizationUrl(): string
    {
        return sprintf(
            'https://yandex.ru/maps/org/%s/%s/',
            $this->faker->unique()->slug(2),
            $this->faker->unique()->numerify('##########'),
        );
    }
}
