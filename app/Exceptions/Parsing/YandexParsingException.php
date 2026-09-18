<?php

declare(strict_types=1);

namespace App\Exceptions\Parsing;

use RuntimeException;

abstract class YandexParsingException extends RuntimeException
{
    abstract public function httpStatus(): int;

    abstract public function errorCode(): string;

    abstract public function isRetryable(): bool;
}
