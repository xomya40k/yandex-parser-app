<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\Yandex;

use App\Exceptions\Parsing\OrganizationUnavailableException;
use App\Integrations\Yandex\YandexMapsIntegrationClient;
use App\Support\Yandex\YandexOrganizationUrlResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YandexMapsIntegrationClientTest extends TestCase
{
    private YandexMapsIntegrationClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'yandex.request_delay_ms' => 0,
            'yandex.timeout' => 5,
            'yandex.proxy' => null,
            'yandex.user_agent' => 'YandexParserTest/1.0',
        ]);

        $this->client = new YandexMapsIntegrationClient(new YandexOrganizationUrlResolver);
    }

    public function test_fetch_reviews_page_returns_status_and_body(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/12345/reviews/?page=2' => Http::response('<html>ok</html>', 200),
        ]);

        $result = $this->client->fetchReviewsPage('12345', 2);

        $this->assertSame(200, $result['status']);
        $this->assertSame('<html>ok</html>', $result['body']);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://yandex.ru/maps/org/12345/reviews/?page=2'
                && $request->hasHeader('User-Agent', 'YandexParserTest/1.0')
                && $request->hasHeader('Accept-Language', 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7');
        });
    }

    public function test_fetch_reviews_page_leaves_forbidden_and_too_many_requests_for_parser(): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/12345/reviews/?page=1' => Http::response('captcha', 403),
        ]);

        $result = $this->client->fetchReviewsPage('12345', 1);

        $this->assertSame(403, $result['status']);
        $this->assertSame('captcha', $result['body']);
    }

    #[DataProvider('unavailableStatusesProvider')]
    public function test_fetch_reviews_page_throws_on_unavailable_status(int $status): void
    {
        Http::fake([
            'https://yandex.ru/maps/org/12345/reviews/?page=1' => Http::response('gone', $status),
        ]);

        $this->expectException(OrganizationUnavailableException::class);

        $this->client->fetchReviewsPage('12345', 1);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function unavailableStatusesProvider(): array
    {
        return [
            '404' => [404],
            '410' => [410],
            '500' => [500],
            '502' => [502],
            '503' => [503],
            '504' => [504],
        ];
    }

    public function test_fetch_reviews_page_throws_on_connection_error(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('connection timed out');
        });

        $this->expectException(OrganizationUnavailableException::class);
        $this->expectExceptionMessage('Failed to fetch Yandex reviews page');

        $this->client->fetchReviewsPage('12345', 1);
    }

    public function test_resolve_organization_id_from_canonical_url_without_http(): void
    {
        Http::fake();

        $id = $this->client->resolveOrganizationId(
            'https://yandex.ru/maps/org/test-cafe/1234567890/reviews/',
        );

        $this->assertSame('1234567890', $id);
        Http::assertNothingSent();
    }

    public function test_resolve_organization_id_follows_short_link_location(): void
    {
        Http::fake([
            'https://yandex.ru/maps/-/shortcode' => Http::response('', 302, [
                'Location' => 'https://yandex.ru/maps/org/test-cafe/9876543210/',
            ]),
        ]);

        $id = $this->client->resolveOrganizationId('https://yandex.ru/maps/-/shortcode');

        $this->assertSame('9876543210', $id);
    }

    public function test_resolve_organization_id_throws_when_short_link_has_no_org_target(): void
    {
        Http::fake([
            'https://yandex.ru/maps/-/shortcode' => Http::response('captcha', 403),
        ]);

        $this->expectException(OrganizationUnavailableException::class);
        $this->expectExceptionMessage('Could not extract a Yandex organization id after following the short link.');

        $this->client->resolveOrganizationId('https://yandex.ru/maps/-/shortcode');
    }

    public function test_resolve_organization_id_throws_on_short_link_not_found(): void
    {
        Http::fake([
            'https://yandex.ru/maps/-/missing' => Http::response('missing', 404),
        ]);

        $this->expectException(OrganizationUnavailableException::class);
        $this->expectExceptionMessage('Yandex short link unavailable (HTTP 404).');

        $this->client->resolveOrganizationId('https://yandex.ru/maps/-/missing');
    }

    public function test_resolve_organization_id_throws_for_non_org_url(): void
    {
        Http::fake();

        $this->expectException(OrganizationUnavailableException::class);

        $this->client->resolveOrganizationId('https://yandex.ru/maps/');
    }

    public function test_pending_request_applies_proxy_when_configured(): void
    {
        config(['yandex.proxy' => 'http://127.0.0.1:8888']);

        Http::fake([
            'https://yandex.ru/maps/org/1/reviews/?page=1' => Http::response('ok', 200),
        ]);

        $this->client->fetchReviewsPage('1', 1);

        Http::assertSentCount(1);
    }
}
