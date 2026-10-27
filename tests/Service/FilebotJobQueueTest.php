<?php

namespace App\Tests\Service;

use App\Message\FilebotJobMessage;
use App\Service\FilebotJobQueue;
use App\Tests\Support\ListableInMemoryTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class FilebotJobQueueTest extends TestCase
{
    public function testListReturnsFilebotMessagesWithTheirTransportIdsAndHonorsLimit(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/first.mkv', 'movie'));
        $transport->enqueue('2-0', new FilebotJobMessage('/media/second.mkv', 'tv'));
        $transport->enqueue('3-0', new \stdClass());

        $jobs = (new FilebotJobQueue($transport))->list(1);

        self::assertSame([
            [
                'id' => '1-0',
                'message' => [
                    'path' => '/media/first.mkv',
                    'label' => 'movie',
                    'order' => 'Airdate',
                    'conflict' => 'skip',
                    'action' => 'copy',
                    'mediaId' => '',
                    'strict' => false,
                    'artwork' => true,
                ],
            ],
        ], $jobs);
    }

    public function testFindReturnsJobDetailsAndNullForMissingOrOtherMessages(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/example.mkv', 'tv'));
        $transport->enqueue('2-0', new \stdClass());
        $queue = new FilebotJobQueue($transport);

        self::assertSame('1-0', $queue->find('1-0')['id']);
        self::assertNull($queue->find('2-0'));
        self::assertNull($queue->find('missing'));
    }

    public function testDeleteAcknowledgesOnlyExistingFilebotJobs(): void
    {
        $transport = new ListableInMemoryTransport();
        $transport->enqueue('1-0', new FilebotJobMessage('/media/example.mkv', 'tv'));
        $transport->enqueue('2-0', new \stdClass());
        $queue = new FilebotJobQueue($transport);

        self::assertTrue($queue->delete('1-0'));
        self::assertFalse($queue->delete('2-0'));
        self::assertFalse($queue->delete('missing'));
        self::assertSame(['2-0'], $transport->messageIds());
    }

    public function testListRequiresAListableTransport(): void
    {
        $queue = new FilebotJobQueue(new InMemoryTransport());

        $this->expectException(\LogicException::class);
        $queue->list();
    }
}
