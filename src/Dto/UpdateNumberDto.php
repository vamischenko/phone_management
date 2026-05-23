<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enum\NumberStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateNumberDto
{
    #[Assert\Choice(
        choices: ['active', 'blocked', 'archived'],
        message: 'status must be one of: active, blocked, archived'
    )]
    public ?string $status = null;

    #[Assert\Length(max: 100, maxMessage: 'tariff must not exceed 100 characters')]
    public ?string $tariff = null;
}
