<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Yandex;

use App\Support\Yandex\YandexOrganizationUrlResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YandexOrganizationUrlResolverTest extends TestCase
{
    private YandexOrganizationUrlResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new YandexOrganizationUrlResolver;
    }

    #[DataProvider('organizationIdProvider')]
    public function test_extract_organization_id(string $url, ?string $expected): void
    {
        $this->assertSame($expected, $this->resolver->extractOrganizationId($url));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function organizationIdProvider(): array
    {
        return [
            'slug and id' => [
                'https://yandex.ru/maps/org/test-cafe/1234567890/reviews/',
                '1234567890',
            ],
            'bare org id' => [
                'https://yandex.ru/maps/org/9876543210/',
                '9876543210',
            ],
            'maps.yandex.ru host' => [
                'https://maps.yandex.ru/org/place/1112223334/',
                '1112223334',
            ],
            'short link has no id' => [
                'https://yandex.ru/maps/-/shortcode',
                null,
            ],
            'unrelated url' => [
                'https://yandex.ru/maps/',
                null,
            ],
        ];
    }

    #[DataProvider('shortLinkProvider')]
    public function test_is_short_link(string $url, bool $expected): void
    {
        $this->assertSame($expected, $this->resolver->isShortLink($url));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function shortLinkProvider(): array
    {
        return [
            'yandex.ru maps short' => ['https://yandex.ru/maps/-/CDqB', true],
            'www short' => ['https://www.yandex.ru/maps/-/CDqB', true],
            'maps.yandex.ru short' => ['https://maps.yandex.ru/-/CDqB', true],
            'canonical org url' => ['https://yandex.ru/maps/org/cafe/1/', false],
            'random url' => ['https://example.com/maps/-/x', false],
        ];
    }

    public function test_build_reviews_page_url(): void
    {
        $this->assertSame(
            'https://yandex.ru/maps/org/12345/reviews/?page=3',
            $this->resolver->buildReviewsPageUrl('12345', 3),
        );
    }
}
