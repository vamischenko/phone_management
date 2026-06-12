<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Number;
use App\Enum\NumberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class NumberControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->em->createQuery('DELETE FROM App\Entity\Number n')->execute();

        /** @var TagAwareCacheInterface $cache */
        $cache = static::getContainer()->get('numbers.cache');
        $cache->invalidateTags(['numbers_list']);
    }

    private function createNumber(
        string $number = '46700000001',
        string $tariff = 'business',
        NumberStatus $status = NumberStatus::ACTIVE,
    ): Number {
        $entity = new Number();
        $entity->setNumber($number);
        $entity->setTariff($tariff);
        $entity->setStatus($status);

        $this->em->persist($entity);
        $this->em->flush();

        return $entity;
    }

    public function testGetListReturnsEmptyPagination(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/numbers');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('pages', $data);
        $this->assertSame(0, $data['total']);
    }

    public function testGetListWithStatusFilter(): void
    {
        $this->createNumber('46700000001', 'business', NumberStatus::ACTIVE);
        $this->createNumber('46700000002', 'premium', NumberStatus::BLOCKED);

        $client = static::createClient();
        $client->request('GET', '/api/numbers?status=active');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(1, $data['total']);
        $this->assertSame('active', $data['items'][0]['status']);
    }

    public function testGetListWithTariffFilter(): void
    {
        $this->createNumber('46700000001', 'business');
        $this->createNumber('46700000002', 'premium');

        $client = static::createClient();
        $client->request('GET', '/api/numbers?tariff=premium');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(1, $data['total']);
        $this->assertSame('premium', $data['items'][0]['tariff']);
    }

    public function testGetListSortByCreatedAtAsc(): void
    {
        $this->createNumber('46700000001');
        sleep(1); // TIMESTAMP(0) has second precision — ensure distinct timestamps
        $this->createNumber('46700000002');

        $client = static::createClient();
        $client->request('GET', '/api/numbers?sort_by=created_at&sort_order=asc');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertSame('46700000001', $data['items'][0]['number']);
        $this->assertSame('46700000002', $data['items'][1]['number']);
    }

    public function testGetListSortByCreatedAtDesc(): void
    {
        $this->createNumber('46700000001');
        sleep(1); // TIMESTAMP(0) has second precision — ensure distinct timestamps
        $this->createNumber('46700000002');

        $client = static::createClient();
        $client->request('GET', '/api/numbers?sort_by=created_at&sort_order=desc');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['items']);
        $this->assertSame('46700000002', $data['items'][0]['number']);
        $this->assertSame('46700000001', $data['items'][1]['number']);
    }

    public function testGetSingleNumber(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('GET', '/api/numbers/' . $id);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame($id, $data['id']);
        $this->assertSame('46700000001', $data['number']);
        $this->assertSame('active', $data['status']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function testGetSingleNumberNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/numbers/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('not_found', $data[0]['error']);
    }

    public function testCreateNumber(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => '46700000001',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('46700000001', $data['number']);
        $this->assertSame('active', $data['status']);
        $this->assertSame('business', $data['tariff']);
    }

    public function testCreateNumberWithNonDigits(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => 'abc123',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
        $this->assertArrayHasKey('number', $data[0]['details']);
    }

    public function testCreateNumberWithTooManyDigits(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => '1234567890123456',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
    }

    public function testCreateMissingFields(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('number', $data[0]['details']);
        $this->assertArrayHasKey('tariff', $data[0]['details']);
    }

    public function testCreateDuplicateNumber(): void
    {
        $this->createNumber('46700000001');

        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => '46700000001',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
        $this->assertArrayHasKey('number', $data[0]['details']);
    }

    public function testCreateInvalidatesListCache(): void
    {
        $client = static::createClient();

        // Первый запрос — кэшируем пустой список
        $client->request('GET', '/api/numbers');
        $before = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(0, $before['total']);

        // Создаём номер — кэш списка должен инвалидироваться
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => '46700000001',
            'tariff' => 'business',
        ]));
        $this->assertResponseStatusCodeSame(201);

        // Второй запрос к списку — должен вернуть свежие данные
        $client->request('GET', '/api/numbers');
        $after = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(1, $after['total']);
    }

    public function testUpdateInvalidatesItemAndListCache(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();

        // Кэшируем отдельный номер и список
        $client->request('GET', '/api/numbers/' . $id);
        $this->assertSame('active', json_decode($client->getResponse()->getContent(), true)['status']);

        $client->request('GET', '/api/numbers');
        $this->assertSame('active', json_decode($client->getResponse()->getContent(), true)['items'][0]['status']);

        // Обновляем — кэш должен инвалидироваться
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'status' => 'blocked',
        ]));
        $this->assertResponseIsSuccessful();

        // Список и отдельный элемент теперь должны возвращать blocked
        $client->request('GET', '/api/numbers/' . $id);
        $this->assertSame('blocked', json_decode($client->getResponse()->getContent(), true)['status']);

        $client->request('GET', '/api/numbers');
        $this->assertSame('blocked', json_decode($client->getResponse()->getContent(), true)['items'][0]['status']);
    }

    public function testUpdateNumberStatus(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'status' => 'blocked',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('blocked', $data['status']);
    }

    public function testUpdateNumberTariff(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'tariff' => 'premium',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('premium', $data['tariff']);
    }

    public function testUpdateArchivedNumberFails(): void
    {
        $number = $this->createNumber('46700000001', 'business', NumberStatus::ARCHIVED);
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'status' => 'active',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
    }

    public function testUpdateWithInvalidStatus(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'status' => 'unknown_status',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
    }

    public function testUpdateNumberNotFound(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/00000000-0000-0000-0000-000000000000', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'tariff' => 'premium',
        ]));

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('not_found', $data[0]['error']);
    }

    public function testListPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createNumber('4670000000' . $i);
        }

        $client = static::createClient();
        $client->request('GET', '/api/numbers?page=1&limit=2');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(5, $data['total']);
        $this->assertCount(2, $data['items']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(3, $data['pages']);
        $this->assertSame(2, $data['limit']);
    }

    public function testListSearchByNumber(): void
    {
        $this->createNumber('46700000001');
        $this->createNumber('99900000001');

        $client = static::createClient();
        $client->request('GET', '/api/numbers?search=467');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame(1, $data['total']);
        $this->assertStringContainsString('467', $data['items'][0]['number']);
    }

    public function testListWithInvalidStatusFilter(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/numbers?status=unknown');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
        $this->assertArrayHasKey('status', $data[0]['details']);
    }

    public function testUpdateWithEmptyBodyFails(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
        $this->assertArrayHasKey('body', $data[0]['details']);
    }

    public function testGetSingleNumberWithInvalidUuid(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/numbers/not-a-uuid');

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('not_found', $data[0]['error']);
    }

    public function testUpdateWithBlankTariffFails(): void
    {
        $number = $this->createNumber();
        $id = (string) $number->getId();

        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/' . $id, [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'tariff' => '',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('validation_error', $data[0]['error']);
        $this->assertArrayHasKey('tariff', $data[0]['details']);
    }
}
