<?php

declare(strict_types=1);

namespace Tests\Fixtures;

final class YandexFixture
{
    public static function path(string $filename): string
    {
        return __DIR__.DIRECTORY_SEPARATOR.'yandex'.DIRECTORY_SEPARATOR.$filename;
    }

    public static function load(string $filename): string
    {
        $path = self::path($filename);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("Unable to read Yandex fixture [{$filename}].");
        }

        return $contents;
    }

    /**
     * Build a minimal reviews-page HTML with an embedded state-view JSON payload.
     *
     * @param  list<array<string, mixed>>  $reviews
     */
    public static function reviewsPageHtml(
        string $name = 'Тестовое Кафе',
        string|float $rating = 4.7,
        int $totalRatings = 1234,
        int $totalReviews = 2,
        array $reviews = [],
    ): string {
        if ($reviews === []) {
            $reviews = [
                [
                    'reviewId' => 'rev-1',
                    'author' => ['name' => 'Иван П.'],
                    'text' => 'Отлично.',
                    'rating' => 5,
                    'updatedTime' => '2026-05-01T10:00:00.000Z',
                ],
                [
                    'reviewId' => 'rev-2',
                    'author' => ['name' => 'Мария С.'],
                    'text' => null,
                    'rating' => 4,
                    'updatedTime' => '2026-04-15T09:30:00.000Z',
                ],
            ];
        }

        $state = json_encode([
            'stack' => [[
                'results' => [
                    'items' => [[
                        'name' => $name,
                        'title' => $name,
                        'ratingData' => [
                            'ratingCount' => $totalRatings,
                            'ratingValue' => $rating,
                            'reviewCount' => $totalReviews,
                        ],
                        'reviewResults' => [
                            'reviews' => $reviews,
                        ],
                    ]],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<html><body><script class="state-view" type="application/json">'.$state.'</script></body></html>';
    }
}
