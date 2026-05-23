<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Repository\NumberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
            throw new ConflictHttpException('number already exists');
        }

        $number = new Number();
        $number->setNumber($dto->number);
        $number->setTariff($dto->tariff);

        $this->entityManager->persist($number);
        $this->entityManager->flush();

        return $number;
    }

    public function update(Number $number, UpdateNumberDto $dto): Number
    {
        if ($number->getStatus() === NumberStatus::ARCHIVED) {
            throw new UnprocessableEntityHttpException('archived number cannot be modified');
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
