<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\Parsing\FailParseRunDTO;
use App\DTOs\Parsing\ParseRunAttemptDTO;
use App\DTOs\Parsing\ParseRunProgressDTO;
use App\DTOs\Parsing\ParseRunTargetDTO;
use App\Enums\OrganizationStatus;
use App\Enums\ParseRunStatus;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Services\Parsing\ParseRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ParseRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParseRunService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->service = app(ParseRunService::class);
    }

    public function test_queue_creates_run_and_dispatches_job_once(): void
    {
        $organization = Organization::factory()->pending()->create();

        $first = $this->service->queue(new ParseRunTargetDTO($organization->id));
        $second = $this->service->queue(new ParseRunTargetDTO($organization->id));

        $this->assertSame($first->parseRun->id, $second->parseRun->id);
        $this->assertSame(ParseRunStatus::Pending, $first->parseRun->status);
        $this->assertSame(1, ParseRun::query()->count());

        Queue::assertPushed(ParseOrganizationJob::class, 1);
        Queue::assertPushed(ParseOrganizationJob::class, function (ParseOrganizationJob $job) use ($organization, $first): bool {
            return $job->organizationId === $organization->id
                && $job->parseRunId === $first->parseRun->id;
        });
    }

    public function test_mark_processing_sets_started_at_on_first_attempt_only(): void
    {
        $run = ParseRun::factory()->pending()->create([
            'started_at' => null,
            'attempt' => 0,
        ]);

        $this->service->markProcessing(new ParseRunAttemptDTO(
            parseRunId: $run->id,
            attempt: 1,
        ));

        $run->refresh();
        $this->assertSame(ParseRunStatus::Processing, $run->status);
        $this->assertSame(1, $run->attempt);
        $this->assertNotNull($run->started_at);
        $this->assertNull($run->error_code);

        $startedAt = $run->started_at;

        $this->travel(5)->seconds();

        $this->service->markProcessing(new ParseRunAttemptDTO(
            parseRunId: $run->id,
            attempt: 2,
        ));

        $run->refresh();
        $this->assertSame(2, $run->attempt);
        $this->assertTrue($startedAt->equalTo($run->started_at));
    }

    public function test_record_progress_and_mark_completed(): void
    {
        $run = ParseRun::factory()->processing()->create([
            'processed_reviews' => 0,
            'processed_pages' => 0,
            'total_reviews' => null,
        ]);

        $this->service->recordProgress(new ParseRunProgressDTO(
            parseRunId: $run->id,
            processedReviews: 50,
            processedPages: 1,
            totalReviews: 120,
        ));

        $run->refresh();
        $this->assertSame(50, $run->processed_reviews);
        $this->assertSame(1, $run->processed_pages);
        $this->assertSame(120, $run->total_reviews);

        $this->service->markCompleted(new ParseRunProgressDTO(
            parseRunId: $run->id,
            processedReviews: 120,
            processedPages: 3,
            totalReviews: 120,
        ));

        $run->refresh();
        $this->assertSame(ParseRunStatus::Completed, $run->status);
        $this->assertSame(120, $run->processed_reviews);
        $this->assertSame(3, $run->processed_pages);
        $this->assertNotNull($run->finished_at);
        $this->assertNull($run->error_code);
    }

    public function test_record_failure_non_terminal_returns_to_pending(): void
    {
        $organization = Organization::factory()->parsing()->create();
        $run = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
        ]);

        $this->service->recordFailure(new FailParseRunDTO(
            parseRunId: $run->id,
            errorCode: 'captcha_required',
            errorMessage: 'captcha',
            terminal: false,
        ));

        $run->refresh();
        $this->assertSame(ParseRunStatus::Pending, $run->status);
        $this->assertSame('captcha_required', $run->error_code);
        $this->assertSame('captcha', $run->error_message);
        $this->assertNull($run->finished_at);
        $this->assertSame(OrganizationStatus::Parsing, $organization->fresh()->status);
    }

    public function test_record_failure_terminal_fails_run_and_organization(): void
    {
        $organization = Organization::factory()->parsing()->create();
        $run = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
        ]);

        $this->service->recordFailure(new FailParseRunDTO(
            parseRunId: $run->id,
            errorCode: 'invalid_layout',
            errorMessage: 'broken',
            terminal: true,
        ));

        $run->refresh();
        $this->assertSame(ParseRunStatus::Failed, $run->status);
        $this->assertSame('invalid_layout', $run->error_code);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(OrganizationStatus::Failed, $organization->fresh()->status);
    }

    public function test_reap_stale_marks_stuck_processing_runs(): void
    {
        config(['yandex.queue.stale_run_minutes' => 30]);

        $stale = ParseRun::factory()->processing()->create([
            'started_at' => now()->subHour(),
        ]);

        $result = $this->service->reapStale();

        $this->assertSame(1, $result->count);
        $this->assertSame(ParseRunStatus::Failed, $stale->fresh()->status);
        $this->assertSame('stale_run', $stale->fresh()->error_code);
    }
}
