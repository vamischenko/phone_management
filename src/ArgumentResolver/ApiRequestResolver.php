<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\CreateNumberRequest;
use App\Request\ListNumbersRequest;
use App\Request\UpdateNumberRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ApiRequestResolver implements ValueResolverInterface
{
    private const SUPPORTED = [
        ListNumbersRequest::class,
        CreateNumberRequest::class,
        UpdateNumberRequest::class,
    ];

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if (!\in_array($type, self::SUPPORTED, true)) {
            return [];
        }

        yield new $type($request);
    }
}
