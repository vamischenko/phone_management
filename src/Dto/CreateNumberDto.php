<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateNumberDto
{
    #[Assert\NotBlank(message: 'number is required')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'number must contain only digits')]
    #[Assert\Length(max: 15, maxMessage: 'number must not exceed 15 digits')]
    public string $number = '';

    #[Assert\NotBlank(message: 'tariff is required')]
    #[Assert\Length(max: 100, maxMessage: 'tariff must not exceed 100 characters')]
    public string $tariff = '';
}
