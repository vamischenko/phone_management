<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UpdateNumberRequest implements ApiRequestInterface
{
    #[Assert\Choice(
        choices: ['active', 'blocked', 'archived'],
        message: 'status must be one of: active, blocked, archived'
    )]
    public readonly ?string $status;

    #[Assert\NotBlank(allowNull: true, message: 'tariff must not be blank')]
    #[Assert\Length(max: 100, maxMessage: 'tariff must not exceed 100 characters')]
    public readonly ?string $tariff;

    public function __construct(Request $request)
    {
        $data = \json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            throw new BadRequestHttpException('Request body must be valid JSON object');
        }

        $this->status = \array_key_exists('status', $data) && \is_string($data['status'])
            ? $data['status']
            : null;

        $this->tariff = \array_key_exists('tariff', $data) && \is_string($data['tariff'])
            ? \trim($data['tariff'])
            : null;
    }

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
