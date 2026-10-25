<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\FilebotJobController;
use App\Dto\CreateFilebotJobRequestDto;
use App\Message\FilebotJobMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

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

    private function assertDispatchFailure(Throwable $exception): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(FilebotJobMessage::class))
            ->willThrowException($exception);

        $controller = new FilebotJobController();
        $controller->setContainer(new ContainerBuilder());

        $response = $controller->create(
            new CreateFilebotJobRequestDto(paths: ['/media/example.mkv'], label: 'movie'),
            $bus,
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame([
            'status' => 'error',
            'message' => $exception->getMessage(),
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }
}
