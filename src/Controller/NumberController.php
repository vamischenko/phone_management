<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ArchivedNumberException;
use App\Exception\DuplicateNumberException;
use App\Exception\NumberNotFoundException;
use App\Http\ApiErrorResponse;
use App\Normalizer\NumberNormalizer;
use App\Repository\NumberRepository;
use App\Request\CreateNumberRequest;
use App\Request\ListNumbersRequest;
use App\Request\UpdateNumberRequest;
use App\Service\NumberCacheService;
use App\Service\NumberService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/numbers', name: 'api_numbers_')]
#[OA\Tag(name: 'Numbers')]
class NumberController extends AbstractController
{
    public function __construct(
        private readonly NumberRepository $numberRepository,
        private readonly NumberService $numberService,
        private readonly NumberCacheService $numberCache,
        private readonly NumberNormalizer $normalizer,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/numbers',
        summary: 'Get list of numbers',
        parameters: [
            new OA\Parameter(
                name: 'status',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['active', 'blocked', 'archived']),
            ),
            new OA\Parameter(name: 'tariff', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(
                name: 'sort_by',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['created_at', 'updated_at'], default: 'created_at'),
            ),
            new OA\Parameter(
                name: 'sort_order',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], default: 'desc'),
            ),
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
        $violations = $this->validator->validate($request);
        if (\count($violations) > 0) {
            return $this->json(ApiErrorResponse::fromViolations($violations), Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->numberCache->getList($request));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => Requirement::UUID])]
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
        $data = $this->numberCache->getOne($id);
        if ($data === null) {
            throw new NumberNotFoundException();
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
            new OA\Response(response: 400, description: 'Invalid JSON body'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(CreateNumberRequest $request): JsonResponse
    {
        $violations = $this->validator->validate($request);
        if (\count($violations) > 0) {
            return $this->json(ApiErrorResponse::fromViolations($violations), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $number = $this->numberService->create($request);
        } catch (DuplicateNumberException) {
            return $this->json(
                ApiErrorResponse::validationError(['number' => 'already exists']),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->numberCache->invalidateList();

        return $this->json($this->normalizer->normalize($number), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'], requirements: ['id' => Requirement::UUID])]
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
        $number = $this->numberRepository->find($id);
        if ($number === null) {
            throw new NumberNotFoundException();
        }

        $violations = $this->validator->validate($request);
        if (\count($violations) > 0) {
            return $this->json(ApiErrorResponse::fromViolations($violations), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $number = $this->numberService->update($number, $request);
        } catch (ArchivedNumberException $e) {
            return $this->json(
                ApiErrorResponse::validationError(['status' => $e->getMessage()]),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $this->numberCache->invalidateOne($id);

        return $this->json($this->normalizer->normalize($number));
    }
}
