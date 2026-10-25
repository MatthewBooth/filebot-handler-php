<?php

namespace App\Tests\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilebotJobControllerTest extends WebTestCase
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
                'mediaId' => '12345',
                'strict' => true,
                'artwork' => true,
                'dryRun' => true,
                'overwrite' => false,
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
                'dryRun' => true,
                'overwrite' => false,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
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
                'dryRun' => true,
                'overwrite' => false,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
    }
}
