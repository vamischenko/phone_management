<?php

declare(strict_types=1);

namespace App\Request;

use App\Enum\NumberStatus;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

final class ListNumbersRequest implements ApiRequestInterface
{
    #[Assert\Choice(
        choices: ['active', 'blocked', 'archived'],
        message: 'status must be one of: active, blocked, archived',
    )]
    public readonly ?string $status;

    public readonly ?string $tariff;

    public readonly ?string $search;

    #[Assert\Choice(
        choices: ['created_at', 'updated_at'],
        message: 'sort_by must be one of: created_at, updated_at',
    )]
    public readonly string $sortBy;

    #[Assert\Choice(
        choices: ['asc', 'desc'],
        message: 'sort_order must be one of: asc, desc',
    )]
    public readonly string $sortOrder;

    #[Assert\Positive(message: 'page must be a positive integer')]
    public readonly int $page;

    #[Assert\Range(
        min: 1,
        max: 100,
        notInRangeMessage: 'limit must be between {{ min }} and {{ max }}',
    )]
    public readonly int $limit;

    public function __construct(Request $request)
    {
        $this->status    = $this->nullableString($request, 'status');
        $this->tariff    = $this->nullableString($request, 'tariff');
        $this->search    = $this->nullableString($request, 'search');
        $this->sortBy    = $request->query->getString('sort_by', 'created_at');
        $this->sortOrder = \strtolower($request->query->getString('sort_order', 'desc'));
        $this->page      = \max(1, $request->query->getInt('page', 1));
        $this->limit     = \min(100, \max(1, $request->query->getInt('limit', 20)));
    }

    public function getStatus(): ?NumberStatus
    {
        return $this->status === null ? null : NumberStatus::from($this->status);
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
