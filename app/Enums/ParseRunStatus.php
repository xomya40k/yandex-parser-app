<?php

declare(strict_types=1);

namespace App\Enums;

enum ParseRunStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
