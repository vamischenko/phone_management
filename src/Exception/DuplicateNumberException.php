<?php

declare(strict_types=1);

namespace App\Exception;

class DuplicateNumberException extends \RuntimeException
{
    public function __construct(string $number)
    {
        parent::__construct(sprintf('Number "%s" already exists', $number));
    }
}
