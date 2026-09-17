<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationRepository $organizationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationRepository = app(OrganizationRepository::class);
    }

    public function test_create_organization(): void
    {
        $organizationData = Organization::factory()->make();

        $organization = $this->organizationRepository->create($organizationData->toArray());

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertEquals($organizationData->name, $organization->name);
        $this->assertEquals($organizationData->yandex_maps_url, $organization->yandex_maps_url);
    }

    public function test_find_by_yandex_url_organization(): void
    {
        $organization = Organization::factory()->create();

        $found = $this->organizationRepository->findByYandexUrl($organization->yandex_maps_url);

        $this->assertInstanceOf(Organization::class, $found);
        $this->assertEquals($organization->id, $found->id);
    }

    public function test_find_by_yandex_url_organization_not_found(): void
    {
        $this->assertNull($this->organizationRepository->findByYandexUrl(fake()->url()));
    }
}
