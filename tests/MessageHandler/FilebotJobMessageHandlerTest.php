<?php

namespace App\Tests\MessageHandler;

use App\Message\FilebotJobMessage;
use App\MessageHandler\FilebotJobMessageHandler;
use App\Model\FilebotJobModel;
use App\Service\FilebotRunnerInterface;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;

final class FilebotJobMessageHandlerTest extends TestCase
{
    public function testInvokeLogsMessageAndDelegatesToRunner(): void
    {
        $logger = new InMemoryLogger();

        $runner = new class implements FilebotRunnerInterface {
            public ?FilebotJobModel $job = null;

            public bool $called = false;

            public function run(FilebotJobModel $job): void
            {
                $this->called = true;
                $this->job = $job;
            }
        };

        $job = new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            order: 'Airdate',
            conflict: 'auto',
            mediaId: '12345',
            strict: true,
            artwork: true,
            overwrite: false,
            dryRun: true,
        );

        $handler = new FilebotJobMessageHandler($logger, $runner);

        $handler(new FilebotJobMessage($job));

        self::assertTrue($runner->called);
        self::assertSame($job, $runner->job);

        self::assertCount(1, $logger->records);
        self::assertSame('Received FileBot job.', $logger->records[0]['message']);
        self::assertSame(['/media/incoming/example.mkv'], $logger->records[0]['context']['paths']);
        self::assertSame('tv', $logger->records[0]['context']['label']);
        self::assertSame('Airdate', $logger->records[0]['context']['order']);
        self::assertSame('auto', $logger->records[0]['context']['conflict']);
        self::assertSame('12345', $logger->records[0]['context']['mediaId']);
        self::assertTrue($logger->records[0]['context']['strict']);
        self::assertTrue($logger->records[0]['context']['artwork']);
        self::assertFalse($logger->records[0]['context']['overwrite']);
        self::assertTrue($logger->records[0]['context']['dryRun']);
    }
}
