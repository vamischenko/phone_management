<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiErrorResponse
{
    /**
     * @param array<string, string> $details
     *
     * @return array<int, array{error: string, details: array<string, string>}>
     */
    public static function validationError(array $details): array
    {
        return [['error' => 'validation_error', 'details' => $details]];
    }

    /**
     * @return array<int, array{error: string, message: string}>
     */
    public static function notFound(string $message = 'Number not found'): array
    {
        return [['error' => 'not_found', 'message' => $message]];
    }

    /**
     * @return array<int, array{error: string, details: array<string, string>}>
     */
    public static function fromViolations(ConstraintViolationListInterface $violations): array
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return self::validationError($details);
    }
}
