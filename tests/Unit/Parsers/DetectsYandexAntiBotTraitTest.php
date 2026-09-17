<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\Exceptions\Parsing\InvalidLayoutException;
use App\Services\Parsers\Traits\DetectsYandexAntiBotTrait;
use App\ValueObjects\Yandex\YandexReviewsPageState;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DetectsYandexAntiBotTraitTest extends TestCase
{
    private DetectsYandexAntiBotHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host = new DetectsYandexAntiBotHost;
    }

    public function test_looks_like_captcha_on_forbidden_and_too_many_requests(): void
    {
        $this->assertTrue($this->host->looksLikeCaptcha(403, '<html></html>'));
        $this->assertTrue($this->host->looksLikeCaptcha(429, '<html></html>'));
    }

    public function test_looks_like_captcha_on_challenge_page_without_reviews(): void
    {
        $body = '<html><div id="smartcaptcha">checkcaptcha</div></html>';

        $this->assertTrue($this->host->looksLikeCaptcha(200, $body));
    }

    public function test_does_not_treat_review_payload_as_captcha(): void
    {
        $body = '<html><div id="smartcaptcha"></div><script>"reviewId":"rev-1"</script></html>';

        $this->assertFalse($this->host->looksLikeCaptcha(200, $body));
    }

    public function test_extract_embedded_state_from_state_view_script(): void
    {
        $html = $this->reviewsPageHtml();

        $state = $this->host->extractEmbeddedState($html);

        $this->assertSame('Тестовое Кафе', data_get($state, 'stack.0.results.items.0.name'));
        $this->assertSame(1234, data_get($state, 'stack.0.results.items.0.ratingData.ratingCount'));
    }

    public function test_extract_embedded_state_prefers_state_view_over_config_view(): void
    {
        $html = <<<'HTML'
<html>
<script class="config-view" type="application/json">{"from":"config"}</script>
<script class="state-view" type="application/json">{"from":"state"}</script>
</html>
HTML;

        $this->assertSame(['from' => 'state'], $this->host->extractEmbeddedState($html));
    }

    public function test_extract_embedded_state_throws_when_script_is_missing(): void
    {
        $this->expectException(InvalidLayoutException::class);

        $this->host->extractEmbeddedState('<html><body>no state</body></html>');
    }

    public function test_extract_embedded_state_throws_when_json_is_invalid(): void
    {
        $this->expectException(InvalidLayoutException::class);

        $this->host->extractEmbeddedState(
            '<script class="state-view" type="application/json">{not-json}</script>',
        );
    }

    public function test_assert_known_reviews_shape_extracts_org_meta_and_reviews(): void
    {
        $shape = $this->host->assertKnownReviewsShape(
            $this->host->extractEmbeddedState($this->reviewsPageHtml()),
        );

        $this->assertInstanceOf(YandexReviewsPageState::class, $shape);
        $this->assertSame('Тестовое Кафе', $shape->name);
        $this->assertSame('4.7', $shape->rating);
        $this->assertSame(1234, $shape->totalRatings);
        $this->assertSame(567, $shape->totalReviews);
        $this->assertCount(2, $shape->reviews);
        $this->assertSame('rev-1', $shape->reviews[0]['reviewId']);
    }

    #[DataProvider('missingShapePathsProvider')]
    public function test_assert_known_reviews_shape_throws_when_required_path_is_missing(array $state): void
    {
        $this->expectException(InvalidLayoutException::class);

        $this->host->assertKnownReviewsShape($state);
    }

    /**
     * @return array<string, array{0: array<int|string, mixed>}>
     */
    public static function missingShapePathsProvider(): array
    {
        return [
            'no stack' => [[]],
            'reviews is not an array' => [[
                'stack' => [[
                    'results' => [
                        'items' => [[
                            'ratingData' => [
                                'ratingCount' => 1,
                                'reviewCount' => 1,
                            ],
                            'reviewResults' => [
                                'reviews' => 'broken',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    public function test_is_suspiciously_empty_when_page_has_no_reviews_or_counters(): void
    {
        $state = [
            'stack' => [[
                'results' => [
                    'items' => [[
                        'ratingData' => [
                            'ratingCount' => 0,
                            'reviewCount' => 0,
                        ],
                        'reviewResults' => [
                            'reviews' => [],
                        ],
                    ]],
                ],
            ]],
        ];

        $this->assertTrue($this->host->isSuspiciouslyEmpty($state));
    }

    public function test_is_not_suspiciously_empty_when_counters_exist_without_reviews(): void
    {
        $state = [
            'stack' => [[
                'results' => [
                    'items' => [[
                        'ratingData' => [
                            'ratingCount' => 10,
                            'reviewCount' => 0,
                            'ratingValue' => 4.2,
                        ],
                        'reviewResults' => [
                            'reviews' => [],
                        ],
                    ]],
                ],
            ]],
        ];

        $this->assertFalse($this->host->isSuspiciouslyEmpty($state));
    }

    private function reviewsPageHtml(): string
    {
        $state = json_encode([
            'stack' => [[
                'results' => [
                    'items' => [[
                        'name' => 'Тестовое Кафе',
                        'title' => 'Тестовое Кафе',
                        'ratingData' => [
                            'ratingCount' => 1234,
                            'ratingValue' => 4.7,
                            'reviewCount' => 567,
                        ],
                        'reviewResults' => [
                            'reviews' => [
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
                            ],
                        ],
                    ]],
                ],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return '<html><body><script class="state-view" type="application/json">'.$state.'</script></body></html>';
    }
}

final class DetectsYandexAntiBotHost
{
    use DetectsYandexAntiBotTrait {
        looksLikeCaptcha as public;
        extractEmbeddedState as public;
        assertKnownReviewsShape as public;
        isSuspiciouslyEmpty as public;
    }
}
