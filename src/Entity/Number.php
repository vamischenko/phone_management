<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NumberStatus;
use App\Repository\NumberRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NumberRepository::class)]
#[ORM\Table(name: 'numbers')]
#[ORM\HasLifecycleCallbacks]
class Number
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 15, unique: true)]
    private string $number;

    #[ORM\Column(type: 'string', length: 20, enumType: NumberStatus::class)]
    private NumberStatus $status = NumberStatus::ACTIVE;

    #[ORM\Column(type: 'string', length: 100)]
    private string $tariff;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): self
    {
        $this->number = $number;
        return $this;
    }

    public function getStatus(): NumberStatus
    {
        return $this->status;
    }

    public function setStatus(NumberStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTariff(): string
    {
        return $this->tariff;
    }

    public function setTariff(string $tariff): self
    {
        $this->tariff = $tariff;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
