<?php

declare(strict_types=1);

namespace App\Services\Parsers\Contracts;

use App\DTOs\Parsing\ParseYandexOrganizationDTO;
use App\DTOs\Parsing\YandexParseResultDTO;
use App\Exceptions\Parsing\YandexParsingException;

interface YandexParserInterface
{
    /**
     * Parse organization meta and all available reviews from Yandex Maps.
     *
     * @throws YandexParsingException
     */
    public function parse(ParseYandexOrganizationDTO $dto): YandexParseResultDTO;
}
