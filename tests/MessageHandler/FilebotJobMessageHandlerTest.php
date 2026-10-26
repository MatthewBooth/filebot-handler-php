<?php

namespace App\Tests\MessageHandler;

use App\MessageHandler\FilebotJobMessageHandler;
use App\Message\FilebotJobMessage;
use App\Service\FilebotRunnerInterface;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;

final class FilebotJobMessageHandlerTest extends TestCase
{
    public function testInvokeLogsMessageAndDelegatesToRunner(): void
    {
        $logger = new InMemoryLogger();

        $runner = new class implements FilebotRunnerInterface {
            public ?FilebotJobMessage $job = null;

            public bool $called = false;

            public function run(FilebotJobMessage $job): void
            {
                $this->called = true;
                $this->job = $job;
            }
        };

        $job = new FilebotJobMessage(
            path: '/media/incoming/example.mkv',
            label: 'tv',
            order: 'Airdate',
            conflict: 'auto',
            action: 'test',
            mediaId: '12345',
            strict: true,
            artwork: true,
        );

        $handler = new FilebotJobMessageHandler($logger, $runner);

        $handler($job);

        self::assertTrue($runner->called);
        self::assertSame($job, $runner->job);

        self::assertCount(2, $logger->records);
        self::assertSame('Received FileBot job.', $logger->records[0]['message']);
        self::assertSame('/media/incoming/example.mkv', $logger->records[0]['context']['path']);
        self::assertSame('tv', $logger->records[0]['context']['label']);
        self::assertSame('Airdate', $logger->records[0]['context']['order']);
        self::assertSame('auto', $logger->records[0]['context']['conflict']);
        self::assertSame('test', $logger->records[0]['context']['action']);
        self::assertSame('12345', $logger->records[0]['context']['mediaId']);
        self::assertTrue($logger->records[0]['context']['strict']);
        self::assertTrue($logger->records[0]['context']['artwork']);
        self::assertSame('FileBot job completed.', $logger->records[1]['message']);
    }
}
