<?php

declare(strict_types=1);

namespace Tests\Unit\Parsers;

use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\Exceptions\Parsing\CaptchaRequiredException;
use App\Exceptions\Parsing\EmptyYandexResponseException;
use App\Exceptions\Parsing\InvalidLayoutException;
use App\Integrations\Yandex\YandexMapsIntegrationClient;
use App\Services\Parsers\YandexMapParserService;
use App\Support\Yandex\YandexOrganizationUrlResolver;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\YandexFixture;
use Tests\TestCase;

class YandexMapParserServiceTest extends TestCase
{
    private YandexMapParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'yandex.request_delay_ms' => 0,
            'yandex.timeout' => 5,
            'yandex.proxy' => null,
            'yandex.max_pages' => 12,
            'yandex.user_agent' => 'YandexParserTest/1.0',
        ]);

        $this->parser = new YandexMapParserService(
            new YandexMapsIntegrationClient(new YandexOrganizationUrlResolver),
        );
    }

    public function test_parse_walks_pages_and_accumulates_reviews(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::load('reviews-page-normal.html'),
                200,
            ),
            'https://yandex.ru/maps/org/1234567890/reviews/?page=2' => Http::response(
                YandexFixture::load('reviews-page-2.html'),
                200,
            ),
            'https://yandex.ru/maps/org/1234567890/reviews/?page=3' => Http::response(
                YandexFixture::load('reviews-page-empty.html'),
                200,
            ),
        ]);

        $result = $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));

        $this->assertSame('Тестовое Кафе', $result->name);
        $this->assertSame('4.7', $result->rating);
        $this->assertSame(1234, $result->totalRatings);
        $this->assertSame(3, $result->totalReviews);
        $this->assertCount(3, $result->reviews);
        $this->assertSame('rev-1', $result->reviews[0]->externalId);
        $this->assertSame('Иван П.', $result->reviews[0]->authorName);
        $this->assertSame(5, $result->reviews[0]->rating);
        $this->assertSame('Отлично.', $result->reviews[0]->text);
        $this->assertSame('rev-3', $result->reviews[2]->externalId);

        Http::assertSentCount(2);
    }

    public function test_parse_stops_when_accumulated_reviews_reach_reported_total(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::reviewsPageHtml(
                    totalReviews: 2,
                    reviews: [
                        [
                            'reviewId' => 'only-1',
                            'author' => ['name' => 'A'],
                            'text' => 'one',
                            'rating' => 5,
                            'updatedTime' => '2026-01-01T00:00:00.000Z',
                        ],
                        [
                            'reviewId' => 'only-2',
                            'author' => ['name' => 'B'],
                            'text' => 'two',
                            'rating' => 4,
                            'updatedTime' => '2026-01-02T00:00:00.000Z',
                        ],
                    ],
                ),
                200,
            ),
        ]);

        $result = $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));

        $this->assertCount(2, $result->reviews);
        Http::assertSentCount(1);
    }

    public function test_parse_throws_captcha_required(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::load('reviews-page-captcha.html'),
                200,
            ),
        ]);

        $this->expectException(CaptchaRequiredException::class);

        $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));
    }

    public function test_parse_throws_empty_yandex_response_on_page_one(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::load('reviews-page-suspiciously-empty.html'),
                200,
            ),
        ]);

        $this->expectException(EmptyYandexResponseException::class);

        $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));
    }

    public function test_parse_throws_invalid_layout_when_shape_is_broken(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::load('reviews-page-invalid-layout.html'),
                200,
            ),
        ]);

        $this->expectException(InvalidLayoutException::class);

        $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));
    }

    public function test_parse_throws_invalid_layout_when_review_rating_is_missing(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/1234567890/reviews/?page=1' => Http::response(
                YandexFixture::reviewsPageHtml(
                    totalReviews: 1,
                    reviews: [[
                        'reviewId' => 'broken',
                        'author' => ['name' => 'X'],
                        'text' => 'no rating',
                        'updatedTime' => '2026-01-01T00:00:00.000Z',
                    ]],
                ),
                200,
            ),
        ]);

        $this->expectException(InvalidLayoutException::class);
        $this->expectExceptionMessage('missing a numeric rating');

        $this->parser->parse(new ParseYandexOrganizationDTO(
            'https://yandex.ru/maps/org/test-cafe/1234567890/',
        ));
    }
}
