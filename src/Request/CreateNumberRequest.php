<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateNumberRequest implements ApiRequestInterface
{
    #[Assert\NotBlank(message: 'number is required')]
    #[Assert\Regex(pattern: '/^\d+$/', message: 'number must contain only digits')]
    #[Assert\Length(min: 7, max: 15, minMessage: 'number must be at least 7 digits', maxMessage: 'number must not exceed 15 digits')]
    public readonly string $number;

    #[Assert\NotBlank(message: 'tariff is required')]
    #[Assert\Length(max: 100, maxMessage: 'tariff must not exceed 100 characters')]
    public readonly string $tariff;

    public function __construct(Request $request)
    {
        $data = \json_decode($request->getContent(), true);

        if (!\is_array($data)) {
            throw new BadRequestHttpException('Request body must be valid JSON object');
        }

        $this->number = \trim((string) ($data['number'] ?? ''));
        $this->tariff = \trim((string) ($data['tariff'] ?? ''));
    }
}
