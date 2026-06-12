<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Repository\NumberRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class NumberService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NumberRepository $numberRepository,
    ) {}

    public function create(CreateNumberDto $dto): Number
    {
        $existing = $this->numberRepository->findOneBy(['number' => $dto->number]);
        if ($existing !== null) {
            throw new DuplicateNumberException($dto->number);
        }

        $number = new Number();
        $number->setNumber($dto->number);
        $number->setTariff($dto->tariff);

        $this->entityManager->persist($number);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateNumberException($dto->number);
        }

        return $number;
    }

    public function update(Number $number, UpdateNumberDto $dto): Number
    {
        if ($number->getStatus() === NumberStatus::ARCHIVED) {
            throw new ArchivedNumberException();
        }

        if ($dto->status !== null) {
            $number->setStatus(NumberStatus::from($dto->status));
        }

        if ($dto->tariff !== null) {
            $number->setTariff($dto->tariff);
        }

        $this->entityManager->flush();

        return $number;
    }
}
