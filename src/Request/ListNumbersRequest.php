<?php

declare(strict_types=1);

namespace App\Request;

use App\Enum\NumberStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class ListNumbersRequest
{
    public readonly ?NumberStatus $status;
    public readonly ?string $tariff;
    public readonly ?string $search;
    public readonly string $sortBy;
    public readonly string $sortOrder;
    public readonly int $page;
    public readonly int $limit;

    private const ALLOWED_SORT_FIELDS = ['created_at', 'updated_at'];
    private const ALLOWED_SORT_ORDERS = ['asc', 'desc'];

    public function __construct(Request $request)
    {
        $statusParam = $this->nullableString($request, 'status');
        if ($statusParam !== null) {
            $status = NumberStatus::tryFrom($statusParam);
            if ($status === null) {
                throw new BadRequestHttpException(
                    \sprintf('status must be one of: %s', \implode(', ', \array_column(NumberStatus::cases(), 'value')))
                );
            }
            $this->status = $status;
        } else {
            $this->status = null;
        }

        $sortBy = $request->query->getString('sort_by', 'created_at');
        if (!\in_array($sortBy, self::ALLOWED_SORT_FIELDS, true)) {
            throw new BadRequestHttpException(
                \sprintf('sort_by must be one of: %s', \implode(', ', self::ALLOWED_SORT_FIELDS))
            );
        }

        $sortOrder = \strtolower($request->query->getString('sort_order', 'desc'));
        if (!\in_array($sortOrder, self::ALLOWED_SORT_ORDERS, true)) {
            throw new BadRequestHttpException(
                \sprintf('sort_order must be one of: %s', \implode(', ', self::ALLOWED_SORT_ORDERS))
            );
        }

        $this->tariff    = $this->nullableString($request, 'tariff');
        $this->search    = $this->nullableString($request, 'search');
        $this->sortBy    = $sortBy;
        $this->sortOrder = $sortOrder;
        $this->page      = \max(1, $request->query->getInt('page', 1));
        $this->limit     = \min(100, \max(1, $request->query->getInt('limit', 20)));
    }

    private function nullableString(Request $request, string $key): ?string
    {
        if (!$request->query->has($key)) {
            return null;
        }

        $value = \trim($request->query->getString($key));

        return $value === '' ? null : $value;
    }
}
