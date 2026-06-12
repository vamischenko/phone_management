<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Repository\NumberRepository;
use App\Service\NumberService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NumberServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private NumberRepository&MockObject $repository;
    private NumberService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(NumberRepository::class);
        $this->service = new NumberService($this->entityManager, $this->repository);
    }

    public function testCreateSuccessfully(): void
    {
        $dto = new CreateNumberDto();
        $dto->number = '46700000001';
        $dto->tariff = 'business';

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['number' => '46700000001'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $number = $this->service->create($dto);

        $this->assertSame('46700000001', $number->getNumber());
        $this->assertSame('business', $number->getTariff());
        $this->assertSame(NumberStatus::ACTIVE, $number->getStatus());
    }

    public function testCreateThrowsOnDuplicateNumber(): void
    {
        $dto = new CreateNumberDto();
        $dto->number = '46700000001';
        $dto->tariff = 'business';

        $existing = new Number();
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existing);

        $this->expectException(DuplicateNumberException::class);
        $this->service->create($dto);
    }

    public function testCreateThrowsOnUniqueConstraintViolation(): void
    {
        $dto = new CreateNumberDto();
        $dto->number = '46700000001';
        $dto->tariff = 'business';

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $this->expectException(DuplicateNumberException::class);
        $this->service->create($dto);
    }

    public function testUpdateStatusSuccessfully(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ACTIVE);

        $dto = new UpdateNumberDto();
        $dto->status = 'blocked';

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->service->update($number, $dto);
        $this->assertSame(NumberStatus::BLOCKED, $updated->getStatus());
    }

    public function testUpdateTariffSuccessfully(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ACTIVE);

        $dto = new UpdateNumberDto();
        $dto->tariff = 'premium';

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->service->update($number, $dto);
        $this->assertSame('premium', $updated->getTariff());
    }

    public function testUpdateThrowsOnArchivedNumber(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ARCHIVED);

        $dto = new UpdateNumberDto();
        $dto->status = 'active';

        $this->expectException(ArchivedNumberException::class);
        $this->service->update($number, $dto);
    }
}
