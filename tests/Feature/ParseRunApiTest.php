<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ParseRunStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ParseRunApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_store_queues_parse_run_and_returns_202(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $response = $this->actingAs($user)
            ->postJson('/api/organization/parse-run');

        $response->assertAccepted()
            ->assertJsonPath('data.status', ParseRunStatus::Pending->value)
            ->assertJsonPath('data.processed_reviews', 0)
            ->assertJsonPath('data.processed_pages', 0)
            ->assertJsonPath('data.progress_percent', null)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'processed_reviews',
                    'total_reviews',
                    'processed_pages',
                    'progress_percent',
                    'attempt',
                    'max_attempts',
                    'error_code',
                    'error_message',
                    'queued_at',
                    'started_at',
                    'finished_at',
                ],
            ]);

        $runId = $response->json('data.id');

        Queue::assertPushed(ParseOrganizationJob::class, function (ParseOrganizationJob $job) use ($organization, $runId): bool {
            return $job->organizationId === $organization->id
                && $job->parseRunId === $runId;
        });
    }

    public function test_store_is_idempotent_for_active_run(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        $first = $this->actingAs($user)
            ->postJson('/api/organization/parse-run')
            ->assertAccepted();

        $second = $this->actingAs($user)
            ->postJson('/api/organization/parse-run')
            ->assertAccepted();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, ParseRun::query()->where('organization_id', $organization->id)->count());
        Queue::assertPushed(ParseOrganizationJob::class, 1);
    }

    public function test_show_returns_progress_fields_for_mid_run(): void
    {
        [$user, $organization] = $this->userWithOrganization();

        ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
            'processed_reviews' => 100,
            'total_reviews' => 400,
            'processed_pages' => 2,
            'attempt' => 1,
            'max_attempts' => 3,
        ]);

        $this->actingAs($user)
            ->getJson('/api/organization/parse-run')
            ->assertOk()
            ->assertJsonPath('data.status', ParseRunStatus::Processing->value)
            ->assertJsonPath('data.processed_reviews', 100)
            ->assertJsonPath('data.total_reviews', 400)
            ->assertJsonPath('data.processed_pages', 2)
            ->assertJsonPath('data.progress_percent', 25)
            ->assertJsonPath('data.attempt', 1)
            ->assertJsonPath('data.max_attempts', 3);
    }

    public function test_show_returns_null_data_when_no_run_exists(): void
    {
        [$user] = $this->userWithOrganization();

        $this->actingAs($user)
            ->getJson('/api/organization/parse-run')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_endpoints_return_404_without_current_organization(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/organization/parse-run')
            ->assertNotFound();

        $this->actingAs($user)
            ->getJson('/api/organization/parse-run')
            ->assertNotFound();
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/organization/parse-run')->assertUnauthorized();
        $this->getJson('/api/organization/parse-run')->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function userWithOrganization(): array
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->pending()->create();
        $user->forceFill(['current_organization_id' => $organization->id])->save();

        return [$user, $organization];
    }
}
