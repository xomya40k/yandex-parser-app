<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\DTOs\Parsing\ParsedReviewDTO;
use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\DTOs\Parsing\YandexParseResultDTO;
use App\Enums\OrganizationStatus;
use App\Enums\ParseRunStatus;
use App\Events\Parsing\YandexReviewsPageParsed;
use App\Exceptions\Parsing\CaptchaRequiredException;
use App\Exceptions\Parsing\InvalidLayoutException;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Services\Organization\OrganizationSyncService;
use App\Services\Parsers\Contracts\YandexParserInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ParseOrganizationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_happy_path_completes_run_and_persists_reviews(): void
    {
        $organization = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ]);
        $run = ParseRun::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);

        $capturedAt = CarbonImmutable::parse('2026-03-26T12:00:00Z');

        $this->mock(YandexParserInterface::class, function (MockInterface $mock) use ($capturedAt): void {
            $mock->shouldReceive('parse')
                ->once()
                ->andReturnUsing(function (ParseYandexOrganizationDTO $dto) use ($capturedAt): YandexParseResultDTO {
                    event(new YandexReviewsPageParsed(
                        parseRunId: $dto->parseRunId,
                        page: 1,
                        reviewsOnPage: 2,
                        totalCollected: 2,
                        totalReviews: 2,
                    ));

                    return new YandexParseResultDTO(
                        name: 'Тестовое Кафе',
                        rating: '4.7',
                        totalRatings: 100,
                        totalReviews: 2,
                        reviews: [
                            new ParsedReviewDTO(
                                externalId: 'rev-1',
                                authorName: 'Иван',
                                rating: 5,
                                text: 'Отлично',
                                reviewDate: CarbonImmutable::parse('2026-01-01T10:00:00Z'),
                            ),
                            new ParsedReviewDTO(
                                externalId: 'rev-2',
                                authorName: 'Мария',
                                rating: 4,
                                text: 'Хорошо',
                                reviewDate: CarbonImmutable::parse('2026-01-02T10:00:00Z'),
                            ),
                        ],
                        capturedAt: $capturedAt,
                    );
                });
        });

        $job = (new ParseOrganizationJob($organization->id, $run->id))
            ->withFakeQueueInteractions();

        $job->handle(app(OrganizationSyncService::class));

        $job->assertNotFailed();

        $run->refresh();
        $organization->refresh();

        $this->assertSame(ParseRunStatus::Completed, $run->status);
        $this->assertSame(2, $run->processed_reviews);
        $this->assertSame(2, $run->total_reviews);
        $this->assertSame(1, $run->processed_pages);
        $this->assertNotNull($run->finished_at);

        $this->assertSame(OrganizationStatus::Ready, $organization->status);
        $this->assertSame('Тестовое Кафе', $organization->name);
        $this->assertSame(2, Review::query()->where('organization_id', $organization->id)->count());
        $this->assertDatabaseHas('reviews', [
            'organization_id' => $organization->id,
            'external_id' => 'rev-1',
            'author_name' => 'Иван',
            'rating' => 5,
        ]);
    }

    public function test_retryable_captcha_rethrows_without_failing_job(): void
    {
        $organization = Organization::factory()->pending()->create();
        $run = ParseRun::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);

        $this->mock(YandexParserInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new CaptchaRequiredException('captcha'));
        });

        $job = (new ParseOrganizationJob($organization->id, $run->id))
            ->withFakeQueueInteractions();

        try {
            $job->handle(app(OrganizationSyncService::class));
            $this->fail('Expected CaptchaRequiredException to be rethrown for queue retry.');
        } catch (CaptchaRequiredException) {
            // Queue worker will retry with backoff(); job itself does not call release().
        }

        $job->assertNotFailed();
        $job->assertNotReleased();

        $run->refresh();
        $this->assertSame(ParseRunStatus::Pending, $run->status);
        $this->assertSame('captcha_required', $run->error_code);
        $this->assertSame(OrganizationStatus::Parsing, $organization->fresh()->status);
    }

    public function test_invalid_layout_fails_job_without_retry(): void
    {
        $organization = Organization::factory()->pending()->create();
        $run = ParseRun::factory()->pending()->create([
            'organization_id' => $organization->id,
        ]);

        $this->mock(YandexParserInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->once()
                ->andThrow(new InvalidLayoutException('layout changed'));
        });

        $job = (new ParseOrganizationJob($organization->id, $run->id))
            ->withFakeQueueInteractions();

        $job->handle(app(OrganizationSyncService::class));

        $job->assertFailed();
        $job->assertFailedWith(InvalidLayoutException::class);
        $job->assertNotReleased();

        // FakeJob::fail() does not invoke the job's failed() hook — call it explicitly.
        $job->failed($job->job->failedWith);

        $run->refresh();
        $this->assertSame(ParseRunStatus::Failed, $run->status);
        $this->assertSame('invalid_layout', $run->error_code);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(OrganizationStatus::Failed, $organization->fresh()->status);
    }

    public function test_failed_hook_marks_run_and_organization_as_failed(): void
    {
        $organization = Organization::factory()->parsing()->create();
        $run = ParseRun::factory()->processing()->create([
            'organization_id' => $organization->id,
        ]);

        $job = new ParseOrganizationJob($organization->id, $run->id);
        $job->failed(new InvalidLayoutException('terminal'));

        $run->refresh();
        $this->assertSame(ParseRunStatus::Failed, $run->status);
        $this->assertSame('invalid_layout', $run->error_code);
        $this->assertSame(OrganizationStatus::Failed, $organization->fresh()->status);
    }
}
