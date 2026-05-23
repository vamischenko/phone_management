<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Number;
use App\Enum\NumberStatus;
use PHPUnit\Framework\TestCase;

class NumberTest extends TestCase
{
    public function testDefaultStatusIsActive(): void
    {
        $number = new Number();
        $this->assertSame(NumberStatus::ACTIVE, $number->getStatus());
    }

    public function testSetAndGetNumber(): void
    {
        $number = new Number();
        $number->setNumber('46700000001');
        $this->assertSame('46700000001', $number->getNumber());
    }

    public function testSetAndGetTariff(): void
    {
        $number = new Number();
        $number->setTariff('business');
        $this->assertSame('business', $number->getTariff());
    }

    public function testSetAndGetStatus(): void
    {
        $number = new Number();
        $number->setStatus(NumberStatus::BLOCKED);
        $this->assertSame(NumberStatus::BLOCKED, $number->getStatus());
    }

    public function testPrePersistSetsTimestamps(): void
    {
        $number = new Number();
        $number->onPrePersist();

        $this->assertInstanceOf(\DateTimeImmutable::class, $number->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $number->getUpdatedAt());
    }

    public function testPreUpdateChangesUpdatedAt(): void
    {
        $number = new Number();
        $number->onPrePersist();

        $createdAt = $number->getCreatedAt();
        $number->onPreUpdate();

        $this->assertSame($createdAt, $number->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $number->getUpdatedAt());
    }
}
