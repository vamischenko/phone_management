<?php

declare(strict_types=1);

namespace App\ArgumentResolver;

use App\Request\ApiRequestInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

final class ApiRequestResolver implements ValueResolverInterface
{
    /**
     * @return iterable<int, ApiRequestInterface>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if ($type === null || !\is_a($type, ApiRequestInterface::class, true)) {
            return [];
        }

        yield new $type($request);
    }
}
