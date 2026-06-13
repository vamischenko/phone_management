<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Request\CreateNumberRequest;
use App\Request\UpdateNumberRequest;
use App\Repository\NumberRepository;
use App\Service\NumberService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

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
        $request = $this->createCreateRequest('46700000001', 'business');

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['number' => '46700000001'])
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $number = $this->service->create($request);

        $this->assertSame('46700000001', $number->getNumber());
        $this->assertSame('business', $number->getTariff());
        $this->assertSame(NumberStatus::ACTIVE, $number->getStatus());
    }

    public function testCreateThrowsOnDuplicateNumber(): void
    {
        $request = $this->createCreateRequest('46700000001', 'business');

        $existing = new Number();
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existing);

        $this->expectException(DuplicateNumberException::class);
        $this->service->create($request);
    }

    public function testCreateThrowsOnUniqueConstraintViolation(): void
    {
        $request = $this->createCreateRequest('46700000001', 'business');

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $this->expectException(DuplicateNumberException::class);
        $this->service->create($request);
    }

    public function testUpdateStatusSuccessfully(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ACTIVE);

        $request = $this->createUpdateRequest(['status' => 'blocked']);

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->service->update($number, $request);
        $this->assertSame(NumberStatus::BLOCKED, $updated->getStatus());
    }

    public function testUpdateTariffSuccessfully(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ACTIVE);

        $request = $this->createUpdateRequest(['tariff' => 'premium']);

        $this->entityManager->expects($this->once())->method('flush');

        $updated = $this->service->update($number, $request);
        $this->assertSame('premium', $updated->getTariff());
    }

    public function testUpdateThrowsOnArchivedNumber(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $number->setTariff('business');
        $number->setStatus(NumberStatus::ARCHIVED);

        $request = $this->createUpdateRequest(['status' => 'active']);

        $this->expectException(ArchivedNumberException::class);
        $this->service->update($number, $request);
    }

    private function createCreateRequest(string $number, string $tariff): CreateNumberRequest
    {
        return new CreateNumberRequest($this->jsonRequest([
            'number' => $number,
            'tariff' => $tariff,
        ]));
    }

    private function createUpdateRequest(array $data): UpdateNumberRequest
    {
        return new UpdateNumberRequest($this->jsonRequest($data));
    }

    private function jsonRequest(array $data): Request
    {
        return Request::create(
            '/api/numbers',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) \json_encode($data),
        );
    }
}
