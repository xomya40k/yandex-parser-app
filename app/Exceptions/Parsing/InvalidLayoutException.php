<?php

declare(strict_types=1);

namespace App\Exceptions\Parsing;

final class InvalidLayoutException extends YandexParsingException
{
    public function httpStatus(): int
    {
        return 502;
    }

    public function errorCode(): string
    {
        return 'invalid_layout';
    }
}
