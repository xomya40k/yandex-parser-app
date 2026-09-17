<?php

declare(strict_types=1);

namespace App\Support\Yandex;

final class YandexOrganizationUrlResolver
{
    /**
     * Extract the numeric organization id from a Yandex Maps org URL.
     *
     * Supports both `/org/{slug}/{id}/` and bare `/org/{id}/` forms.
     * Returns null for short links and any URL that does not contain an org id.
     */
    public function extractOrganizationId(string $url): ?string
    {
        if (preg_match('#/org/[^/]+/(\d+)(?:/|$)#i', $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('#/org/(\d+)(?:/|$)#i', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Detect the /maps/-/... (or maps.yandex.* /-/...) short-link form.
     */
    public function isShortLink(string $url): bool
    {
        return preg_match(
            '#^https?://(?:www\.)?(?:maps\.)?yandex\.(?:ru|com|by|kz|ua)/(?:maps/)?-/#i',
            $url,
        ) === 1;
    }

    /**
     * Build the canonical server-rendered reviews page URL for a given org id and page.
     */
    public function buildReviewsPageUrl(string $orgId, int $page): string
    {
        return sprintf(
            'https://yandex.ru/maps/org/%s/reviews/?page=%d',
            rawurlencode($orgId),
            $page,
        );
    }
}
