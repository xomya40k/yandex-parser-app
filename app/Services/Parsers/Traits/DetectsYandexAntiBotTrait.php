<?php

declare(strict_types=1);

namespace App\Services\Parsers\Traits;

use App\Exceptions\Parsing\InvalidLayoutException;
use App\ValueObjects\Yandex\YandexReviewsPageState;
use stdClass;

/**
 * Shared anti-bot and embedded-state helpers for Yandex Maps parser implementations.
 *
 * JSON-path constants are the single point of change if Yandex moves keys around.
 */
trait DetectsYandexAntiBotTrait
{
    /** @var list<string> */
    private const STATE_SCRIPT_CLASSES = ['state-view', 'config-view'];

    /** @var list<int> */
    private const CAPTCHA_HTTP_STATUSES = [403, 429];

    private const CAPTCHA_BODY_PATTERN = '/showcaptcha|checkcaptcha|smartcaptcha|captcha\.yandex/i';

    private const PATH_ORG_NAME = 'stack.0.results.items.0.name';

    private const PATH_ORG_TITLE = 'stack.0.results.items.0.title';

    private const PATH_RATING_VALUE = 'stack.0.results.items.0.ratingData.ratingValue';

    private const PATH_RATINGS_COUNT = 'stack.0.results.items.0.ratingData.ratingCount';

    private const PATH_REVIEWS_COUNT = 'stack.0.results.items.0.ratingData.reviewCount';

    private const PATH_REVIEWS = 'stack.0.results.items.0.reviewResults.reviews';

    protected function looksLikeCaptcha(int $status, string $body): bool
    {
        if (in_array($status, self::CAPTCHA_HTTP_STATUSES, true)) {
            return true;
        }

        $hasChallenge = preg_match(self::CAPTCHA_BODY_PATTERN, $body) === 1;
        $hasReviewPayload = str_contains($body, '"reviewId"');

        return $hasChallenge && !$hasReviewPayload;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws InvalidLayoutException
     */
    protected function extractEmbeddedState(string $html): array
    {
        $json = $this->extractStateScriptPayload($html);

        if (is_null($json)) {
            throw new InvalidLayoutException(
                'Yandex page has no state-view/config-view script tag with embedded JSON.',
            );
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            $decoded = json_decode(html_entity_decode($json, ENT_QUOTES | ENT_HTML5), true);
        }

        if (!is_array($decoded)) {
            throw new InvalidLayoutException('Yandex embedded state JSON is missing or invalid.');
        }

        return $decoded;
    }

    /**
     * @param  array<int|string, mixed>  $state
     *
     * @throws InvalidLayoutException
     */
    protected function assertKnownReviewsShape(array $state): YandexReviewsPageState
    {
        $reviews = $this->requirePath($state, self::PATH_REVIEWS);

        if (!is_array($reviews)) {
            throw new InvalidLayoutException(
                'Yandex embedded state path ['.self::PATH_REVIEWS.'] is not an array.',
            );
        }

        $normalizedReviews = [];

        foreach ($reviews as $index => $review) {
            if (!is_array($review)) {
                throw new InvalidLayoutException(
                    "Yandex review at index [{$index}] is not an object.",
                );
            }

            /** @var array<string, mixed> $review */
            $normalizedReviews[] = $review;
        }

        $name = $this->optionalString($state, self::PATH_ORG_NAME)
            ?? $this->optionalString($state, self::PATH_ORG_TITLE);

        $ratingValue = data_get($state, self::PATH_RATING_VALUE);
        $rating = is_numeric($ratingValue) ? (string) $ratingValue : null;

        return new YandexReviewsPageState(
            name: $name,
            rating: $rating,
            totalRatings: $this->requireInt($state, self::PATH_RATINGS_COUNT),
            totalReviews: $this->requireInt($state, self::PATH_REVIEWS_COUNT),
            reviews: $normalizedReviews,
        );
    }

    /**
     * @param  array<int|string, mixed>  $state
     */
    protected function isSuspiciouslyEmpty(array $state): bool
    {
        $reviews = data_get($state, self::PATH_REVIEWS);
        $hasReviews = is_array($reviews) && ! empty($reviews);

        $rating = data_get($state, self::PATH_RATING_VALUE);
        $ratingsCount = data_get($state, self::PATH_RATINGS_COUNT);
        $reviewsCount = data_get($state, self::PATH_REVIEWS_COUNT);

        $hasCounters = (is_numeric($rating) && (float) $rating > 0.0)
            || (is_numeric($ratingsCount) && (int) $ratingsCount > 0)
            || (is_numeric($reviewsCount) && (int) $reviewsCount > 0);

        return ! $hasReviews && ! $hasCounters;
    }

    private function extractStateScriptPayload(string $html): ?string
    {
        foreach (self::STATE_SCRIPT_CLASSES as $class) {
            $pattern = sprintf(
                '/<script\b(?=[^>]*\bclass=(["\'])[^"\']*\b%s\b[^"\']*\1)[^>]*>\s*(.*?)\s*<\/script>/is',
                preg_quote($class, '/'),
            );

            if (preg_match($pattern, $html, $matches) === 1 && ! empty($matches[2])) {
                return $matches[2];
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $state
     *
     * @throws InvalidLayoutException
     */
    private function requirePath(array $state, string $path): mixed
    {
        $sentinel = new stdClass;
        $value = data_get($state, $path, $sentinel);

        if ($value === $sentinel) {
            throw new InvalidLayoutException(
                "Yandex embedded state is missing expected path [{$path}].",
            );
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $state
     *
     * @throws InvalidLayoutException
     */
    private function requireInt(array $state, string $path): int
    {
        $value = $this->requirePath($state, $path);

        if (!is_numeric($value)) {
            throw new InvalidLayoutException(
                "Yandex embedded state path [{$path}] is not numeric.",
            );
        }

        return (int) $value;
    }

    /**
     * @param  array<int|string, mixed>  $state
     */
    private function optionalString(array $state, string $path): ?string
    {
        $value = data_get($state, $path);

        if (!is_string($value) || empty($value)) {
            return null;
        }

        return $value;
    }
}
