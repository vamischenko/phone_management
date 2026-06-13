<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiErrorResponse
{
    public static function validationError(array $details): array
    {
        return [['error' => 'validation_error', 'details' => $details]];
    }

    public static function notFound(string $message = 'Number not found'): array
    {
        return [['error' => 'not_found', 'message' => $message]];
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return self::validationError($details);
    }
}
