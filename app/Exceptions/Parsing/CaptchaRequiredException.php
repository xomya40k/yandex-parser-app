<?php

declare(strict_types=1);

namespace App\Exceptions\Parsing;

final class CaptchaRequiredException extends YandexParsingException
{
    public function httpStatus(): int
    {
        return 503;
    }

    public function errorCode(): string
    {
        return 'captcha_required';
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
