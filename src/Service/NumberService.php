<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Repository\NumberRepository;
use App\Request\CreateNumberRequest;
use App\Request\UpdateNumberRequest;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class NumberService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NumberRepository $numberRepository,
    ) {
    }

    public function create(CreateNumberRequest $request): Number
    {
        $existing = $this->numberRepository->findOneBy(['number' => $request->number]);
        if ($existing !== null) {
            throw new DuplicateNumberException($request->number);
        }

        $number = new Number();
        $number->setNumber($request->number);
        $number->setTariff($request->tariff);

        $this->entityManager->persist($number);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateNumberException($request->number);
        }

        return $number;
    }

    public function update(Number $number, UpdateNumberRequest $request): Number
    {
        if ($number->getStatus() === NumberStatus::ARCHIVED) {
            throw new ArchivedNumberException();
        }

        if ($request->status !== null) {
            $number->setStatus(NumberStatus::from($request->status));
        }

        if ($request->tariff !== null) {
            $number->setTariff($request->tariff);
        }

        $this->entityManager->flush();

        return $number;
    }
}
