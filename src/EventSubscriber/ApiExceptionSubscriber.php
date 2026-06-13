<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\NumberNotFoundException;
use App\Http\ApiErrorResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ApiExceptionSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof NumberNotFoundException) {
            $event->setResponse(new JsonResponse(
                ApiErrorResponse::notFound(),
                Response::HTTP_NOT_FOUND,
            ));

            return;
        }

        if ($exception instanceof BadRequestHttpException) {
            $event->setResponse(new JsonResponse(
                ApiErrorResponse::validationError($this->parseBadRequestDetails($exception->getMessage())),
                Response::HTTP_BAD_REQUEST,
            ));

            return;
        }

        if ($exception instanceof NotFoundHttpException && $this->isNumbersApiPath($event)) {
            $event->setResponse(new JsonResponse(
                ApiErrorResponse::notFound(),
                Response::HTTP_NOT_FOUND,
            ));
        }
    }

    private function isNumbersApiPath(ExceptionEvent $event): bool
    {
        return \str_starts_with($event->getRequest()->getPathInfo(), '/api/numbers');
    }

    /**
     * @return array<string, string>
     */
    private function parseBadRequestDetails(string $message): array
    {
        if (\preg_match('/^([\w]+) must be/', $message, $matches) === 1) {
            return [$matches[1] => $message];
        }

        if (\str_contains($message, 'JSON')) {
            return ['body' => $message];
        }

        return ['_error' => $message];
    }
}
