<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class UpdateNumberDto
{
    #[Assert\Choice(
        choices: ['active', 'blocked', 'archived'],
        message: 'status must be one of: active, blocked, archived'
    )]
    public ?string $status = null;

    #[Assert\NotBlank(allowNull: true, message: 'tariff must not be blank')]
    #[Assert\Length(max: 100, maxMessage: 'tariff must not exceed 100 characters')]
    public ?string $tariff = null;

    #[Assert\Callback]
    public function validateAtLeastOneField(ExecutionContextInterface $context): void
    {
        if ($this->status === null && $this->tariff === null) {
            $context->buildViolation('at least one of status or tariff must be provided')
                ->atPath('body')
                ->addViolation();
        }
    }
}
