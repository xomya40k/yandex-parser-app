<?php

declare(strict_types=1);

namespace App\Enums;

enum OrganizationStatus: string
{
    case Pending = 'pending';
    case Parsing = 'parsing';
    case Ready = 'ready';
    case Failed = 'failed';
}
