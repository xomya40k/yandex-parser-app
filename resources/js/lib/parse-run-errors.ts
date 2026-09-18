const ERROR_MESSAGES: Record<string, string> = {
    invalid_layout:
        'Разметка Яндекс.Карт изменилась — парсер не смог разобрать страницу. Требуется обновление парсера.',
    captcha_required:
        'Яндекс показал капчу или временно ограничил доступ. Попробуйте позже.',
    empty_yandex_response:
        'Яндекс вернул пустой ответ без рейтинга и отзывов. Попробуйте ещё раз.',
    organization_unavailable:
        'Карточка организации недоступна (сеть, таймаут или страница удалена).',
    stale_run: 'Парсинг завис и был прерван. Запустите повторный сбор отзывов.',
    unexpected_error:
        'Произошла непредвиденная ошибка при парсинге. Попробуйте ещё раз.',
};

export function describeParseRunError(code: string | null): string {
    if (!code) {
        return 'Парсинг завершился с ошибкой.';
    }

    return ERROR_MESSAGES[code] ?? `Ошибка парсинга (${code}).`;
}
