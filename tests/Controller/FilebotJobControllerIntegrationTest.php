<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FilebotJobControllerIntegrationTest extends WebTestCase
{
    public function testCreateQueuesFilebotJob(): void
    {
        $client = self::createClient();
        $path = tempnam(sys_get_temp_dir(), 'filebot-job-');
        self::assertNotFalse($path);

        try {
            $client->request(
                'POST',
                '/api/jobs',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'path' => $path,
                    'label' => 'tv',
                    'order' => 'Airdate',
                    'conflict' => 'auto',
                    'action' => 'test',
                    'mediaId' => '12345',
                    'strict' => true,
                    'artwork' => true,
                ], JSON_THROW_ON_ERROR)
            );
        } finally {
            unlink($path);
        }

        self::assertResponseStatusCodeSame(202);
        self::assertJson($client->getResponse()->getContent());

        self::assertSame(
            ['status' => 'queued'],
            json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)
        );
    }

    public function testCreateRejectsEmptyPath(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'path' => '',
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
        self::assertContains('path', array_column($response['errors'], 'field'));
    }

    public function testCreateRejectsMissingInputPath(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'path' => '/path/that/does/not/exist.mkv',
                'label' => 'movie',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(422);
        $response = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('error', $response['status']);
        self::assertSame('Request validation failed.', $response['message']);
        self::assertContains('path', array_column($response['errors'], 'field'));
    }

    public function testCreateRejectsInvalidLabel(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/jobs',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'path' => '/media/incoming/example.mkv',
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
