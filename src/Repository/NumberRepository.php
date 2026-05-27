<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Number;
use App\Enum\NumberStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Number>
 */
class NumberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Number::class);
    }

    public function findByFilters(
        ?string $status,
        ?string $tariff,
        ?string $search,
        string $sortBy,
        string $sortOrder,
        int $page,
        int $limit
    ): array {
        $qb = $this->createQueryBuilder('n');

        if ($status !== null) {
            $qb->andWhere('n.status = :status')
                ->setParameter('status', $status);
        }

        if ($tariff !== null) {
            $qb->andWhere('n.tariff = :tariff')
                ->setParameter('tariff', $tariff);
        }

        if ($search !== null) {
            $qb->andWhere('n.number LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $sortFieldMap = [
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
        ];
        $sortField = $sortFieldMap[$sortBy] ?? 'createdAt';
        $sortDirection = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

        $countQb = (clone $qb)->select('COUNT(n.id)');
        $total = $countQb->getQuery()->getSingleScalarResult();

        $qb->orderBy('n.' . $sortField, $sortDirection);

        $items = $qb
            ->select('n')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }
}
