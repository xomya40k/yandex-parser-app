<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Repositories\OrganizationRepository;
use Database\Factories\OrganizationFactory;
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

    public function test_create_organization()
    {
        $organizationData = OrganizationFactory::new()->make();

        $organization = $this->organizationRepository->create($organizationData->toArray());

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertEquals($organizationData->name, $organization->name);
        $this->assertEquals($organizationData->yandex_maps_url, $organization->yandex_maps_url);
    }

    public function test_find_by_yandex_url_organization()
    {
        $organization = OrganizationFactory::new()->create();

        $this->assertInstanceOf(Organization::class, $this->organizationRepository->findByYandexUrl($organization->yandex_maps_url));
        $this->assertEquals($organization->id, $this->organizationRepository->findByYandexUrl($organization->yandex_maps_url)->id);
    }

    public function test_find_by_yandex_url_organization_not_found()
    {
        $this->assertNull($this->organizationRepository->findByYandexUrl(fake()->url()));
    }

    public function test_update_status_organization()
    {
        $organization = OrganizationFactory::new()->pending()->create();

        $organization = $this->organizationRepository->updateStatus($organization, OrganizationStatus::Ready);

        $this->assertInstanceOf(Organization::class, $organization);
        $this->assertEquals(OrganizationStatus::Ready, $organization->status);
    }
}
