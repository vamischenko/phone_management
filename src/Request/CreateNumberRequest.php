<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CreateNumberRequest
{
    public readonly string $number;
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
