<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateNumberDto;
use App\Dto\UpdateNumberDto;
use App\Entity\Number;
use App\Repository\NumberRepository;
use App\Service\NumberService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/api/numbers', name: 'api_numbers_')]
#[OA\Tag(name: 'Numbers')]
class NumberController extends AbstractController
{
    public function __construct(
        private readonly NumberRepository $numberRepository,
        private readonly NumberService $numberService,
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer,
        private readonly CacheInterface $cache,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/numbers',
        summary: 'Get list of numbers',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'blocked', 'archived'])),
            new OA\Parameter(name: 'tariff', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['createdAt', 'updatedAt'], default: 'createdAt')),
            new OA\Parameter(name: 'sort_order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], default: 'DESC')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of numbers'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $tariff = $request->query->get('tariff');
        $search = $request->query->get('search');
        $sortBy = $request->query->get('sort_by', 'createdAt');
        $sortOrder = $request->query->get('sort_order', 'DESC');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $cacheKey = sprintf(
            'numbers_list_%s_%s_%s_%s_%s_%d_%d',
            $status ?? 'all',
            $tariff ?? 'all',
            $search ?? 'all',
            $sortBy,
            $sortOrder,
            $page,
            $limit
        );

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use (
            $status, $tariff, $search, $sortBy, $sortOrder, $page, $limit
        ) {
            $item->expiresAfter(60);

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
        $cacheKey = 'number_' . $id;

        $data = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
            $item->expiresAfter(300);

            $number = $this->numberRepository->find($id);
            if ($number === null) {
                return null;
            }

            return $this->serializeNumber($number);
        });

        if ($data === null) {
            return $this->json(['error' => 'not_found', 'message' => 'Number not found'], Response::HTTP_NOT_FOUND);
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
        } catch (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $e) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['number' => 'already exists']]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->cache->delete('numbers_list_*');

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
        $number = $this->numberRepository->find($id);
        if ($number === null) {
            return $this->json(['error' => 'not_found', 'message' => 'Number not found'], Response::HTTP_NOT_FOUND);
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
            $dto->status = $data['status'];
        }

        if (array_key_exists('tariff', $data)) {
            $dto->tariff = $data['tariff'];
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json($this->formatViolations($violations), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $number = $this->numberService->update($number, $dto);
        } catch (\Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException $e) {
            return $this->json(
                [['error' => 'validation_error', 'details' => ['status' => $e->getMessage()]]],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->cache->delete('number_' . $id);

        return $this->json($this->serializeNumber($number));
    }

    private function serializeNumber(Number $number): array
    {
        return [
            'id' => (string) $number->getId(),
            'number' => $number->getNumber(),
            'status' => $number->getStatus()->value,
            'tariff' => $number->getTariff(),
            'created_at' => $number->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updated_at' => $number->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }

    private function formatViolations(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): array
    {
        $details = [];
        foreach ($violations as $violation) {
            $details[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return [['error' => 'validation_error', 'details' => $details]];
    }
}
