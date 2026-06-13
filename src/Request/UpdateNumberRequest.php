<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UpdateNumberRequest
{
    public readonly ?string $status;
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
}
