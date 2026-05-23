<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Number;
// @group functional
use App\Enum\NumberStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class NumberControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM App\Entity\Number n')->execute();
    }

    private function createNumber(string $number = '46700000001', string $tariff = 'business', NumberStatus $status = NumberStatus::ACTIVE): Number
    {
        $entity = new Number();
        $entity->setNumber($number);
        $entity->setTariff($tariff);
        $entity->setStatus($status);
        $entity->onPrePersist();

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

    public function testGetListWithFilters(): void
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
    }

    public function testGetSingleNumberNotFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/numbers/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
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

    public function testCreateNumberWithInvalidFormat(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => 'abc123',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(422);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertSame('validation_error', $data[0]['error']);
    }

    public function testCreateNumberWithTooManyDigits(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/numbers', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'number' => '1234567890123456',
            'tariff' => 'business',
        ]));

        $this->assertResponseStatusCodeSame(422);
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

    public function testUpdateNumberNotFound(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/api/numbers/00000000-0000-0000-0000-000000000000', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'tariff' => 'premium',
        ]));

        $this->assertResponseStatusCodeSame(404);
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
}
