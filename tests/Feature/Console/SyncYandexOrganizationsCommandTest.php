<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\DTOs\Parsing\ParsedReviewDTO;
use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\DTOs\Parsing\YandexParseResultDTO;
use App\Enums\OrganizationStatus;
use App\Exceptions\Parsing\CaptchaRequiredException;
use App\Exceptions\Parsing\InvalidLayoutException;
use App\Models\Organization;
use App\Models\OrganizationSnapshot;
use App\Models\Review;
use App\Services\Parsers\Contracts\YandexParserInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;

class SyncYandexOrganizationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_when_nothing_is_due(): void
    {
        Organization::factory()->ready()->create([
            'last_parsed_at' => now(),
        ]);

        $this->artisan('yandex:sync-organizations')
            ->expectsOutputToContain('No organizations due for sync.')
            ->assertSuccessful();
    }

    public function test_happy_path_syncs_multiple_organizations_idempotently(): void
    {
        $first = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/cafe-a/1111111111/',
        ]);
        $second = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/cafe-b/2222222222/',
        ]);

        $this->mock(YandexParserInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->twice()
                ->andReturnUsing(function (ParseYandexOrganizationDTO $dto): YandexParseResultDTO {
                    $suffix = str_contains($dto->yandexMapsUrl, '1111111111') ? 'a' : 'b';

                    return new YandexParseResultDTO(
                        name: "Cafe {$suffix}",
                        rating: '4.5',
                        totalRatings: 10,
                        totalReviews: 1,
                        reviews: [
                            new ParsedReviewDTO(
                                externalId: "rev-{$suffix}",
                                authorName: "Author {$suffix}",
                                rating: 5,
                                text: "Text {$suffix}",
                                reviewDate: CarbonImmutable::parse('2026-01-10T12:00:00Z'),
                            ),
                        ],
                        capturedAt: CarbonImmutable::parse('2026-03-26T10:00:00Z'),
                    );
                });
        });

        $this->artisan('yandex:sync-organizations')
            ->expectsOutputToContain('Synced: 2, failed: 0, skipped: 0.')
            ->assertSuccessful();

        $this->assertSame(OrganizationStatus::Ready, $first->fresh()->status);
        $this->assertSame('Cafe a', $first->fresh()->name);
        $this->assertSame(OrganizationStatus::Ready, $second->fresh()->status);
        $this->assertSame(2, Review::query()->count());
        $this->assertSame(2, OrganizationSnapshot::query()->count());

        $first->forceFill([
            'status' => OrganizationStatus::Pending,
            'last_parsed_at' => null,
        ])->save();
        $second->forceFill([
            'status' => OrganizationStatus::Ready,
            'last_parsed_at' => now()->subDays(2),
        ])->save();

        $this->mock(YandexParserInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->twice()
                ->andReturnUsing(function (ParseYandexOrganizationDTO $dto): YandexParseResultDTO {
                    $suffix = str_contains($dto->yandexMapsUrl, '1111111111') ? 'a' : 'b';

                    return new YandexParseResultDTO(
                        name: "Cafe {$suffix}",
                        rating: '4.6',
                        totalRatings: 12,
                        totalReviews: 1,
                        reviews: [
                            new ParsedReviewDTO(
                                externalId: "rev-{$suffix}",
                                authorName: "Author {$suffix}",
                                rating: 5,
                                text: "Updated {$suffix}",
                                reviewDate: CarbonImmutable::parse('2026-01-10T12:00:00Z'),
                            ),
                        ],
                        capturedAt: CarbonImmutable::parse('2026-03-26T11:00:00Z'),
                    );
                });
        });

        $this->artisan('yandex:sync-organizations')
            ->expectsOutputToContain('Synced: 2, failed: 0, skipped: 0.')
            ->assertSuccessful();

        $this->assertSame(2, Review::query()->count());
        $this->assertSame('Updated a', Review::query()->where('external_id', 'rev-a')->value('text'));
        $this->assertSame(4, OrganizationSnapshot::query()->count());
        $this->assertSame('4.60', (string) $first->fresh()->rating);
    }

    public function test_per_organization_failures_are_logged_and_do_not_abort_batch(): void
    {
        $ok = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/ok/3333333333/',
        ]);
        $captcha = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/captcha/4444444444/',
        ]);
        $layout = Organization::factory()->pending()->create([
            'yandex_maps_url' => 'https://yandex.ru/maps/org/layout/5555555555/',
        ]);

        Log::spy();

        $this->mock(YandexParserInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parse')
                ->times(3)
                ->andReturnUsing(function (ParseYandexOrganizationDTO $dto): YandexParseResultDTO {
                    return match (true) {
                        str_contains($dto->yandexMapsUrl, '3333333333') => new YandexParseResultDTO(
                            name: 'OK Cafe',
                            rating: '5.0',
                            totalRatings: 1,
                            totalReviews: 1,
                            reviews: [
                                new ParsedReviewDTO(
                                    externalId: 'rev-ok',
                                    authorName: 'Ok',
                                    rating: 5,
                                    text: 'Fine',
                                    reviewDate: CarbonImmutable::parse('2026-02-01T00:00:00Z'),
                                ),
                            ],
                            capturedAt: CarbonImmutable::now(),
                        ),
                        str_contains($dto->yandexMapsUrl, '4444444444') => throw new CaptchaRequiredException('captcha'),
                        str_contains($dto->yandexMapsUrl, '5555555555') => throw new InvalidLayoutException('layout'),
                        default => throw new \RuntimeException('unexpected url: '.$dto->yandexMapsUrl),
                    };
                });
        });

        $this->artisan('yandex:sync-organizations')
            ->expectsOutputToContain('Synced: 1, failed: 2, skipped: 0.')
            ->assertSuccessful();

        $this->assertSame(OrganizationStatus::Ready, $ok->fresh()->status);
        // Non-terminal failures leave the card in Parsing; Failed is applied only on
        // terminal handling (ParseOrganizationJob::failed → ParseRunService).
        $this->assertSame(OrganizationStatus::Parsing, $captcha->fresh()->status);
        $this->assertSame(OrganizationStatus::Parsing, $layout->fresh()->status);

        Log::shouldHaveReceived('error')->atLeast()->twice();
    }
}
