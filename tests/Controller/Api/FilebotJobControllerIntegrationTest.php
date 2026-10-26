<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilebotJobControllerIntegrationTest extends WebTestCase
{
    public function testCreateQueuesFilebotJob(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/v1/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'paths' => ['/media/incoming/example.mkv'],
                'label' => 'tv',
                'order' => 'Airdate',
                'conflict' => 'auto',
                'action' => 'test',
                'mediaId' => '12345',
                'strict' => true,
                'artwork' => true,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(202);
        self::assertJson($client->getResponse()->getContent());

        self::assertSame(
            ['status' => 'queued'],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateRejectsEmptyPaths(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/v1/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'paths' => [],
                'label' => 'tv',
                'action' => 'test',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $response['status']);
        self::assertSame('Request validation failed.', $response['message']);
        self::assertNotEmpty($response['errors']);
        self::assertContains('paths', array_column($response['errors'], 'field'));
    }

    public function testCreateRejectsInvalidLabel(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/v1/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'paths' => ['/media/incoming/example.mkv'],
                'label' => 'documentary',
                'action' => 'test',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        self::assertJson($client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $response['status']);
        self::assertNotEmpty($response['errors']);

        $labelErrors = array_filter($response['errors'], static fn (array $error): bool => $error['field'] === 'label');
        self::assertNotEmpty($labelErrors);
        self::assertNotEmpty(reset($labelErrors)['message']);
    }
}
