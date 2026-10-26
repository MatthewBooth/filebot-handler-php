<?php

namespace App\Tests\Controller;

use App\Controller\FilebotJobController;
use App\Dto\CreateFilebotJobMessageDto;
use App\Message\FilebotJobMessage;
use App\Tests\Support\InMemoryLogger;
use Error;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    private function assertDispatchFailure(Throwable $exception): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(FilebotJobMessage::class))
            ->willThrowException($exception);

        $controller = new FilebotJobController();
        $controller->setContainer(new ContainerBuilder());
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
