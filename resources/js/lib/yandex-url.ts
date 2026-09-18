const YANDEX_ORG_URL_PATTERN =
    /^https?:\/\/(?:www\.)?(?:maps\.)?yandex\.(?:ru|com|by|kz|ua)\/(?:maps\/)?(?:org\/|-\/)/i;

export function isValidYandexMapsOrganizationUrl(value: string): boolean {
    if (!value.trim()) {
        return false;
    }

    try {
        void new URL(value);
    } catch {
        return false;
    }

    return YANDEX_ORG_URL_PATTERN.test(value);
}
