<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Enum\NumberStatus;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Repository\NumberRepository;
use App\Service\NumberService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Exception\NumberNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/numbers', name: 'api_numbers_')]
#[OA\Tag(name: 'Numbers')]
class NumberController extends AbstractController
{
    public function __construct(
        private readonly NumberRepository $numberRepository,
        private readonly NumberService $numberService,
        private readonly ValidatorInterface $validator,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/numbers',
        summary: 'Get list of numbers',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'blocked', 'archived'])),
            new OA\Parameter(name: 'tariff', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at'], default: 'created_at')),
            new OA\Parameter(name: 'sort_order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of numbers'),
            new OA\Response(response: 400, description: 'Invalid filter parameters'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $statusParam = $this->nullableQueryString($request, 'status');
        $status = null;
        if ($statusParam !== null) {
            $status = NumberStatus::tryFrom($statusParam);
            if ($status === null) {
                return $this->json(
                    [['error' => 'validation_error', 'details' => ['status' => 'status must be one of: active, blocked, archived']]],
                    Response::HTTP_BAD_REQUEST
                );
            }
        }

        $tariff = $this->nullableQueryString($request, 'tariff');
        $search = $this->nullableQueryString($request, 'search');
        $sortBy = $request->query->get('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query->get('sort_order', 'desc'));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        if (!in_array($sortBy, ['created_at', 'updated_at'], true)) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['sort_by' => 'sort_by must be one of: created_at, updated_at']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($sortOrder, ['asc', 'desc'], true)) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['sort_order' => 'sort_order must be one of: asc, desc']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $cacheKey = 'numbers_list_' . md5(serialize([$status?->value, $tariff, $search, $sortBy, $sortOrder, $page, $limit]));

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $status, $tariff, $search, $sortBy, $sortOrder, $page, $limit
        ) {
            $item->expiresAfter(60);
            $item->tag('numbers_list');

            $data = $this->numberRepository->findByFilters(
                $status, $tariff, $search, $sortBy, $sortOrder, $page, $limit
            );

            $data['items'] = array_map(
                fn(Number $n) => $this->serializeNumber($n),
                $data['items']
            );

            return $data;
        });

        return $this->json($result);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[OA\Get(
        path: '/api/numbers/{id}',
        summary: 'Get single number by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Number details'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->notFoundResponse();
        }

        $cacheKey = 'number_' . $id;

        try {
            $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
                $item->expiresAfter(300);
                $item->tag(['numbers_list', 'number_' . $id]);

                $number = $this->numberRepository->find($id);
                if ($number === null) {
                    throw new NumberNotFoundException();
                }

                return $this->serializeNumber($number);
            });
        } catch (NumberNotFoundException) {
            return $this->notFoundResponse();
        }

        return $this->json($data);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/numbers',
        summary: 'Create new number',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['number', 'tariff'],
                properties: [
                    new OA\Property(property: 'number', type: 'string', example: '46700000001'),
                    new OA\Property(property: 'tariff', type: 'string', example: 'business'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Number created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['body' => 'invalid JSON']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $dto = new CreateNumberDto();
        $dto->number = trim((string) ($data['number'] ?? ''));
        $dto->tariff = trim((string) ($data['tariff'] ?? ''));

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json($this->formatViolations($violations), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $number = $this->numberService->create($dto);
        } catch (DuplicateNumberException) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['number' => 'already exists']]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->cache->invalidateTags(['numbers_list']);

        return $this->json($this->serializeNumber($number), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/numbers/{id}',
        summary: 'Update number status or tariff',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'blocked', 'archived']),
                    new OA\Property(property: 'tariff', type: 'string', example: 'premium'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Number updated'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error or archived number'),
        ]
    )]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->notFoundResponse();
        }

        $number = $this->numberRepository->find($id);
        if ($number === null) {
            return $this->notFoundResponse();
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['body' => 'invalid JSON']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $dto = new UpdateNumberDto();

        if (array_key_exists('status', $data)) {
            $dto->status = is_string($data['status']) ? $data['status'] : null;
        }
        if (array_key_exists('tariff', $data)) {
            $dto->tariff = is_string($data['tariff']) ? trim($data['tariff']) : null;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json($this->formatViolations($violations), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $number = $this->numberService->update($number, $dto);
        } catch (ArchivedNumberException $e) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['status' => $e->getMessage()]]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->cache->invalidateTags(['numbers_list', 'number_' . $id]);

        return $this->json($this->serializeNumber($number));
    }

    private function serializeNumber(Number $number): array
    {
        return [
            'id'         => (string) $number->getId(),
            'number'     => $number->getNumber(),
            'status'     => $number->getStatus()->value,
            'tariff'     => $number->getTariff(),
            'created_at' => $number->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updated_at' => $number->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }

    private function formatViolations(ConstraintViolationListInterface $violations): array
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return [['error' => 'validation_error', 'details' => $details]];
    }

    private function nullableQueryString(Request $request, string $key): ?string
    {
        if (!$request->query->has($key)) {
            return null;
        }

        $value = $request->query->get($key);
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function notFoundResponse(): JsonResponse
    {
        return $this->json(
            [['error' => 'not_found', 'message' => 'Number not found']],
            Response::HTTP_NOT_FOUND
        );
    }
}
