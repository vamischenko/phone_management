<?php

declare(strict_types=1);

namespace App\Normalizer;

use App\Entity\Number;

final class NumberNormalizer
{
    public function normalize(Number $number): array
    {
        return [
            'id'         => (string) $number->getId(),
            'number'     => $number->getNumber(),
            'status'     => $number->getStatus()->value,
            'tariff'     => $number->getTariff(),
            'created_at' => $number->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updated_at' => $number->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @param Number[] $numbers
     *
     * @return array<int, array<string, mixed>>
     */
    public function normalizeList(array $numbers): array
    {
        return \array_map($this->normalize(...), $numbers);
    }
}
