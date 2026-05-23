<?php

declare(strict_types=1);

namespace App\Enum;

enum NumberStatus: string
{
    case ACTIVE = 'active';
    case BLOCKED = 'blocked';
    case ARCHIVED = 'archived';
}
