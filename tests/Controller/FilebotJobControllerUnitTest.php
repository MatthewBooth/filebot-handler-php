<?php

namespace App\Tests\Controller;

use App\Controller\FilebotJobController;
use App\Dto\CreateFilebotJobMessageDto;
use App\Message\FilebotJobMessage;
use App\Service\FilebotJobQueue;
use App\Tests\Support\InMemoryLogger;
use App\Tests\Support\ListableInMemoryTransport;
use Error;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

final class FilebotJobControllerUnitTest extends TestCase
{
    public function testCreateReturnsErrorWhenDispatchThrowsException(): void
    {
        $this->assertDispatchFailure(new RuntimeException('dispatch failed'));
    }

    public function testCreateReturnsErrorWhenDispatchThrowsMessengerException(): void
    {
        $this->assertDispatchFailure(new class('messenger dispatch failed') extends RuntimeException implements ExceptionInterface {
        });
    }

    public function testCreateReturnsErrorWhenDispatchThrowsError(): void
    {
        $this->assertDispatchFailure(new Error('dispatch error'));
    }

    public function testListReturnsQueuedJobDetails(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/example.mkv', 'movie'));
        $controller = $this->createController();

        $response = $controller->list(
            Request::create('/api/jobs?limit=10'),
            new FilebotJobQueue($transport),
            new InMemoryLogger(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('1-0', json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['jobs'][0]['id']);
    }

    public function testListRejectsOutOfRangeLimit(): void
    {
        $response = $this->createController()->list(
            Request::create('/api/jobs?limit=501'),
            new FilebotJobQueue(new ListableInMemoryTransport()),
            new InMemoryLogger(),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testShowReturnsNotFoundForUnknownJob(): void
    {
        $response = $this->createController()->show(
            'missing',
            new FilebotJobQueue(new ListableInMemoryTransport()),
            new InMemoryLogger(),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    public function testShowReturnsDetailsForExistingJob(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/example.mkv', 'movie'));

        $response = $this->createController()->show(
            '1-0',
            new FilebotJobQueue($transport),
            new InMemoryLogger(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('/media/example.mkv', json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['message']['path']);
    }

    public function testDeleteRemovesQueuedJob(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/example.mkv', 'movie'));

        $response = $this->createController()->delete(
            '1-0',
            new FilebotJobQueue($transport),
            new InMemoryLogger(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $transport->messageIds());
    }

    public function testDeleteReturnsNotFoundForUnknownJob(): void
    {
        $response = $this->createController()->delete(
            'missing',
            new FilebotJobQueue(new ListableInMemoryTransport()),
            new InMemoryLogger(),
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function createController(): FilebotJobController
    {
        $controller = new FilebotJobController();
        $controller->setContainer(new ContainerBuilder());

        return $controller;
    }

    private function assertDispatchFailure(Throwable $exception): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(FilebotJobMessage::class))
            ->willThrowException($exception);

        $controller = $this->createController();
        $logger = new InMemoryLogger();

        $response = $controller->create(
            new CreateFilebotJobMessageDto(path: '/media/example.mkv', label: 'movie'),
            $bus,
            $logger,
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame([
            'status' => 'error',
            'message' => 'Unable to queue FileBot job.',
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($exception, $logger->records[0]['context']['exception']);
    }
}
