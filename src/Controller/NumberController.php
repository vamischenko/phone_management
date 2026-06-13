<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Repository\NumberRepository;
use App\Request\CreateNumberRequest;
use App\Request\ListNumbersRequest;
use App\Request\UpdateNumberRequest;
use App\Service\NumberService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function list(ListNumbersRequest $request): JsonResponse
    {
        $cacheKey = 'numbers_list_' . \md5(\serialize([
            $request->status?->value,
            $request->tariff,
            $request->search,
            $request->sortBy,
            $request->sortOrder,
            $request->page,
            $request->limit,
        ]));

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($request): array {
            $item->expiresAfter(60);
            $item->tag('numbers_list');

            $data = $this->numberRepository->findByFilters(
                $request->status,
                $request->tariff,
                $request->search,
                $request->sortBy,
                $request->sortOrder,
                $request->page,
                $request->limit,
            );

            $data['items'] = \array_map(
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

        $number = $this->numberRepository->find($id);
        if ($number === null) {
            return $this->notFoundResponse();
        }

        $data = $this->cache->get("number_{$id}", function (ItemInterface $item) use ($number, $id): array {
            $item->expiresAfter(300);
            $item->tag(['numbers_list', 'number_' . $id]);

            return $this->serializeNumber($number);
        });

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
            new OA\Response(response: 400, description: 'Invalid JSON body'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(CreateNumberRequest $request): JsonResponse
    {
        $dto = new CreateNumberDto();
        $dto->number = $request->number;
        $dto->tariff = $request->tariff;

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
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
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['active', 'blocked', 'archived']),
                    new OA\Property(property: 'tariff', type: 'string', example: 'premium'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Number updated'),
            new OA\Response(response: 400, description: 'Invalid JSON body'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error or archived number'),
        ]
    )]
    public function update(string $id, UpdateNumberRequest $request): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return $this->notFoundResponse();
        }

        $number = $this->numberRepository->find($id);
        if ($number === null) {
            return $this->notFoundResponse();
        }

        $dto = new UpdateNumberDto();
        $dto->status = $request->status;
        $dto->tariff = $request->tariff;

        $violations = $this->validator->validate($dto);
        if (\count($violations) > 0) {
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

    private function notFoundResponse(): JsonResponse
    {
        return $this->json(
            [['error' => 'not_found', 'message' => 'Number not found']],
            Response::HTTP_NOT_FOUND
        );
    }
}
