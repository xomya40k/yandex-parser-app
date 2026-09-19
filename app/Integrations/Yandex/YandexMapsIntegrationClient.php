<?php

declare(strict_types=1);

namespace App\Integrations\Yandex;

use App\Exceptions\Parsing\OrganizationUnavailableException;
use App\Support\Yandex\YandexOrganizationUrlResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class YandexMapsIntegrationClient
{
    /**
     * HTTP statuses that mean the organization/page is unambiguously unavailable
     * (not a captcha / soft block — those stay on 200/403/429 for the parser).
     *
     * @var list<int>
     */
    private const UNAVAILABLE_STATUSES = [404, 410, 500, 502, 503, 504];

    public function __construct(
        private readonly YandexOrganizationUrlResolver $urlResolver,
    ) {}

    /**
     * Fetch one server-rendered reviews page.
     *
     * @return array{status: int, body: string}
     *
     * @throws OrganizationUnavailableException
     */
    public function fetchReviewsPage(string $orgId, int $page): array
    {
        $this->throttle();

        $url = $this->urlResolver->buildReviewsPageUrl($orgId, $page);

        try {
            $response = $this->sendGet($url);
        } catch (ConnectionException $exception) {
            throw new OrganizationUnavailableException(
                "Failed to fetch Yandex reviews page for organization {$orgId}: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $status = $response->status();

        if ($this->isUnavailableStatus($status)) {
            throw new OrganizationUnavailableException(
                "Yandex reviews page unavailable (HTTP {$status}) for organization {$orgId}.",
            );
        }

        return [
            'status' => $status,
            'body' => $response->body(),
        ];
    }

    /**
     * Resolve a numeric organization id from a Yandex Maps URL.
     *
     * Short links (`/maps/-/...`) are followed over HTTP; canonical `/org/...`
     * URLs are parsed locally without a network call.
     *
     * @throws OrganizationUnavailableException
     */
    public function resolveOrganizationId(string $yandexMapsUrl): string
    {
        if (!$this->urlResolver->isShortLink($yandexMapsUrl)) {
            $organizationId = $this->urlResolver->extractOrganizationId($yandexMapsUrl);

            if (!is_null($organizationId)) {
                return $organizationId;
            }

            throw new OrganizationUnavailableException(
                'Could not extract a Yandex organization id from the given URL.',
            );
        }

        $this->throttle();

        try {
            $response = $this->sendGet($yandexMapsUrl);
        } catch (ConnectionException $exception) {
            throw new OrganizationUnavailableException(
                "Failed to resolve Yandex short link: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $status = $response->status();

        if ($this->isUnavailableStatus($status)) {
            throw new OrganizationUnavailableException(
                "Yandex short link unavailable (HTTP {$status}).",
            );
        }

        $finalUrl = $this->resolveFinalUrl($response, $yandexMapsUrl);
        $organizationId = $this->urlResolver->extractOrganizationId($finalUrl);

        if (is_null($organizationId)) {
            throw new OrganizationUnavailableException(
                'Could not extract a Yandex organization id after following the short link.',
            );
        }

        return $organizationId;
    }

    /**
     * Apply a config-driven randomized delay before an outbound request.
     */
    public function throttle(): void
    {
        $baseMs = (int) config('yandex.request_delay_ms', 500);

        if ($baseMs <= 0) {
            return;
        }

        $minMs = (int) max(0, (int) round($baseMs * 0.6));
        $maxMs = (int) max($minMs, (int) round($baseMs * 1.4));

        usleep(random_int($minMs, $maxMs) * 1000);
    }

    /**
     * @throws ConnectionException
     */
    private function sendGet(string $url): Response
    {
        return $this->pendingRequest()
            ->retry(
                times: 2,
                sleepMilliseconds: 200,
                when: static fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            )
            ->get($url);
    }

    private function pendingRequest(): PendingRequest
    {
        $request = Http::withHeaders([
            'User-Agent' => (string) config(
                'yandex.user_agent',
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
        ])
            ->timeout((int) config('yandex.timeout', 20))
            ->withOptions([
                'allow_redirects' => true,
            ]);

        $proxy = config('yandex.proxy');

        if (is_string($proxy) && !empty($proxy)) {
            $request = $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    /**
     * Prefer the post-redirect effective URI; fall back to Location (useful under Http::fake).
     */
    private function resolveFinalUrl(Response $response, string $requestUrl): string
    {
        $effectiveUri = $response->effectiveUri();

        if (!is_null($effectiveUri)) {
            return (string) $effectiveUri;
        }

        $location = $response->header('Location');

        if (!empty($location)) {
            return $this->absolutizeUrl($location, $requestUrl);
        }

        return $requestUrl;
    }

    private function absolutizeUrl(string $location, string $baseUrl): string
    {
        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'yandex.ru';

        if (str_starts_with($location, '/')) {
            return "{$scheme}://{$host}{$location}";
        }

        return "{$scheme}://{$host}/{$location}";
    }

    private function isUnavailableStatus(int $status): bool
    {
        return in_array($status, self::UNAVAILABLE_STATUSES, true);
    }
}
