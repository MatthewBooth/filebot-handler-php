<?php

namespace App\Tests\Controller;

use App\Service\FilebotJobPendingStatusReaderInterface;
use App\Tests\Support\ListableInMemoryTransport;
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

    public function testCanListViewAndDeleteQueuedFilebotJob(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $transport = new ListableInMemoryTransport();
        self::getContainer()->set('messenger.transport.async', $transport);
        self::getContainer()->set(FilebotJobPendingStatusReaderInterface::class, $transport);

        $path = tempnam(sys_get_temp_dir(), 'filebot-job-');
        self::assertNotFalse($path);

        try {
            $client->request(
                'POST',
                '/api/jobs',
                server: ['CONTENT_TYPE' => 'application/json'],
                content: json_encode([
                    'path' => $path,
                    'label' => 'movie',
                    'action' => 'test',
                ], JSON_THROW_ON_ERROR)
            );
            self::assertResponseStatusCodeSame(202);

            $client->request('GET', '/api/jobs');
            self::assertResponseStatusCodeSame(200);
            $list = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount(1, $list['jobs']);
            $jobId = $list['jobs'][0]['id'];
            self::assertSame($path, $list['jobs'][0]['message']['path']);
            self::assertSame('queued', $list['jobs'][0]['status']);

            $transport->markPending($jobId, 'worker-1', 1200, 1);

            $client->request('GET', '/api/jobs/'.$jobId);
            self::assertResponseStatusCodeSame(200);
            $job = json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame($jobId, $job['id']);
            self::assertSame('movie', $job['message']['label']);
            self::assertSame('processing', $job['status']);
            self::assertSame('worker-1', $job['worker']);
            self::assertSame(1200, $job['idleForMs']);
            self::assertSame(1, $job['deliveryCount']);

            $client->request('DELETE', '/api/jobs/'.$jobId);
            self::assertResponseStatusCodeSame(200);
            self::assertSame(
                ['status' => 'deleted', 'id' => $jobId],
                json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)
            );

            $client->request('GET', '/api/jobs/'.$jobId);
            self::assertResponseStatusCodeSame(404);
        } finally {
            unlink($path);
        }
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
