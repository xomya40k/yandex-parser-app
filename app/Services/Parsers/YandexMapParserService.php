<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\Parsing\ParsedReviewDTO;
use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\DTOs\Parsing\YandexParseResultDTO;
use App\Exceptions\Parsing\CaptchaRequiredException;
use App\Exceptions\Parsing\EmptyYandexResponseException;
use App\Exceptions\Parsing\InvalidLayoutException;
use App\Integrations\Yandex\YandexMapsIntegrationClient;
use App\Services\Parsers\Contracts\YandexParserInterface;
use App\Services\Parsers\Traits\DetectsYandexAntiBotTrait;
use App\ValueObjects\Yandex\YandexReviewsPageState;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

final class YandexMapParserService implements YandexParserInterface
{
    use DetectsYandexAntiBotTrait;

    public function __construct(
        private readonly YandexMapsIntegrationClient $client,
    ) {}

    public function parse(ParseYandexOrganizationDTO $dto): YandexParseResultDTO
    {
        $organizationId = $this->client->resolveOrganizationId($dto->yandexMapsUrl);
        $maxPages = max(1, (int) config('yandex.max_pages', 12));
        $capturedAt = CarbonImmutable::now();

        $name = null;
        $rating = null;
        $totalRatings = 0;
        $totalReviews = 0;
        /** @var list<ParsedReviewDTO> $reviews */
        $reviews = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->client->fetchReviewsPage($organizationId, $page);
            $status = $response['status'];
            $body = $response['body'];

            if ($this->looksLikeCaptcha($status, $body)) {
                throw new CaptchaRequiredException(
                    "Yandex presented a captcha challenge while fetching reviews page {$page} for organization {$organizationId}.",
                );
            }

            $state = $this->extractEmbeddedState($body);

            if ($page === 1 && $this->isSuspiciouslyEmpty($state)) {
                throw new EmptyYandexResponseException(
                    "Yandex reviews page 1 for organization {$organizationId} has no reviews and no rating counters.",
                );
            }

            $pageState = $this->assertKnownReviewsShape($state);

            if ($page === 1) {
                $name = $pageState->name;
                $rating = $pageState->rating;
                $totalRatings = $pageState->totalRatings;
                $totalReviews = $pageState->totalReviews;
            }

            $pageReviews = $this->mapReviews($pageState);

            if (empty($pageReviews)) {
                break;
            }

            foreach ($pageReviews as $review) {
                $reviews[] = $review;
            }

            if ($totalReviews > 0 && count($reviews) >= $totalReviews) {
                break;
            }
        }

        return new YandexParseResultDTO(
            name: $name,
            rating: $rating,
            totalRatings: $totalRatings,
            totalReviews: $totalReviews,
            reviews: $reviews,
            capturedAt: $capturedAt,
        );
    }

    /**
     * @return list<ParsedReviewDTO>
     *
     * @throws InvalidLayoutException
     */
    private function mapReviews(YandexReviewsPageState $pageState): array
    {
        $mapped = [];

        foreach ($pageState->reviews as $index => $review) {
            $mapped[] = $this->mapReview($review, $index);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $review
     *
     * @throws InvalidLayoutException
     */
    private function mapReview(array $review, int $index): ParsedReviewDTO
    {
        $ratingValue = $review['rating'] ?? null;

        if (!is_numeric($ratingValue)) {
            throw new InvalidLayoutException(
                "Yandex review at index [{$index}] is missing a numeric rating.",
            );
        }

        $reviewDate = $this->resolveReviewDate($review, $index);
        $authorName = $this->resolveAuthorName($review);
        $text = $this->resolveReviewText($review);
        $externalId = $this->resolveExternalId($review, $authorName, $ratingValue, $text, $reviewDate);

        return new ParsedReviewDTO(
            externalId: $externalId,
            authorName: $authorName,
            rating: (int) $ratingValue,
            text: $text,
            reviewDate: $reviewDate,
        );
    }

    /**
     * @param  array<string, mixed>  $review
     */
    private function resolveAuthorName(array $review): string
    {
        $author = $review['author'] ?? null;

        if (is_array($author)) {
            $name = $author['name'] ?? null;

            if (is_string($name) && !empty($name)) {
                return $name;
            }
        }

        if (isset($review['authorName']) && is_string($review['authorName']) && !empty($review['authorName'])) {
            return $review['authorName'];
        }

        return 'Аноним';
    }

    /**
     * @param  array<string, mixed>  $review
     */
    private function resolveReviewText(array $review): ?string
    {
        $text = $review['text'] ?? null;

        if (!is_string($text)) {
            return null;
        }

        $trimmed = trim($text);

        return empty($trimmed) ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $review
     *
     * @throws InvalidLayoutException
     */
    private function resolveReviewDate(array $review, int $index): DateTimeInterface
    {
        $raw = $review['updatedTime']
            ?? $review['timeCreated']
            ?? $review['createdTime']
            ?? null;

        if (! is_string($raw) || empty($raw)) {
            throw new InvalidLayoutException(
                "Yandex review at index [{$index}] is missing a review date.",
            );
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (Throwable $exception) {
            throw new InvalidLayoutException(
                "Yandex review at index [{$index}] has an invalid review date.",
                previous: $exception,
            );
        }
    }

    /**
     * Prefer the platform review id; fall back to a stable content hash when absent.
     *
     * @param  array<string, mixed>  $review
     */
    private function resolveExternalId(
        array $review,
        string $authorName,
        mixed $ratingValue,
        ?string $text,
        DateTimeInterface $reviewDate,
    ): string {
        $reviewId = $review['reviewId'] ?? null;

        if (is_string($reviewId) && !empty($reviewId)) {
            return $reviewId;
        }

        if (is_int($reviewId) || is_float($reviewId)) {
            return (string) $reviewId;
        }

        $normalized = implode('|', [
            mb_strtolower(trim($authorName)),
            $reviewDate->format('Y-m-d\TH:i:s'),
            $text ?? '',
            (string) (int) $ratingValue,
        ]);

        return hash('sha256', $normalized);
    }
}
