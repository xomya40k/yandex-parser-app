<?php

declare(strict_types=1);

namespace App\Exceptions\Parsing;

final class OrganizationUnavailableException extends YandexParsingException
{
    public function httpStatus(): int
    {
        return 502;
    }

    public function errorCode(): string
    {
        return 'organization_unavailable';
    }
}
