<?php

declare(strict_types=1);

namespace App\Service;

use App\Normalizer\NumberNormalizer;
use App\Repository\NumberRepository;
use App\Request\ListNumbersRequest;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class NumberCacheService
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly NumberRepository $numberRepository,
        private readonly NumberNormalizer $normalizer,
    ) {
    }

    /**
     * @return array{
     *     items: list<array{
     *         id: string,
     *         number: string,
     *         status: string,
     *         tariff: string,
     *         created_at: string,
     *         updated_at: string
     *     }>,
     *     total: int,
     *     page: int,
     *     limit: int,
     *     pages: int
     * }
     */
    public function getList(ListNumbersRequest $request): array
    {
        $cacheKey = 'numbers_list_' . \md5(\serialize([
            $request->getStatus()?->value,
            $request->tariff,
            $request->search,
            $request->sortBy,
            $request->sortOrder,
            $request->page,
            $request->limit,
        ]));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($request): array {
            $item->expiresAfter(60);
            $item->tag('numbers_list');

            $data = $this->numberRepository->findByFilters(
                $request->getStatus(),
                $request->tariff,
                $request->search,
                $request->sortBy,
                $request->sortOrder,
                $request->page,
                $request->limit,
            );

            $data['items'] = $this->normalizer->normalizeList($data['items']);

            return $data;
        });
    }

    /**
     * @return array{id: string, number: string, status: string, tariff: string, created_at: string, updated_at: string}|null
     */
    public function getOne(string $id): ?array
    {
        return $this->cache->get("number_{$id}", function (ItemInterface $item) use ($id): ?array {
            $item->expiresAfter(300);
            $item->tag(['numbers_list', 'number_' . $id]);

            $number = $this->numberRepository->find($id);
            if ($number === null) {
                $item->expiresAfter(0);

                return null;
            }

            return $this->normalizer->normalize($number);
        });
    }

    public function invalidateList(): void
    {
        $this->cache->invalidateTags(['numbers_list']);
    }

    public function invalidateOne(string $id): void
    {
        $this->cache->invalidateTags(['numbers_list', 'number_' . $id]);
    }
}
