<?php

namespace App\Tests\Support;

use App\Service\FilebotJobPendingStatusReaderInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class ListableInMemoryTransport implements TransportInterface, ListableReceiverInterface, FilebotJobPendingStatusReaderInterface
{
    /** @var array<string, Envelope> */
    private array $messages = [];

    /** @var array<string, array{worker: string, idleForMs: int, deliveryCount: int}> */
    private array $pendingStatuses = [];

    public function enqueue(string $id, object $message): void
    {
        $this->messages[$id] = new Envelope($message, [new TransportMessageIdStamp($id)]);
    }

    public function markPending(string $id, string $worker, int $idleForMs, int $deliveryCount): void
    {
        $this->pendingStatuses[$id] = [
            'worker' => $worker,
            'idleForMs' => $idleForMs,
            'deliveryCount' => $deliveryCount,
        ];
    }

    public function findPendingStatuses(array $messageIds): array
    {
        return array_intersect_key($this->pendingStatuses, array_fill_keys($messageIds, true));
    }

    public function get(): iterable
    {
        return [];
    }

    public function ack(Envelope $envelope): void
    {
        $id = $envelope->last(TransportMessageIdStamp::class)?->getId();
        if (is_string($id)) {
            unset($this->messages[$id]);
            unset($this->pendingStatuses[$id]);
        }
    }

    public function reject(Envelope $envelope): void
    {
        $this->ack($envelope);
    }

    public function send(Envelope $envelope): Envelope
    {
        $id = uniqid('test-', true);
        $envelope = $envelope->with(new TransportMessageIdStamp($id));
        $this->messages[$id] = $envelope;

        return $envelope;
    }

    public function all(?int $limit = null): iterable
    {
        return null === $limit
            ? array_values($this->messages)
            : array_slice(array_values($this->messages), 0, $limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return is_string($id) ? ($this->messages[$id] ?? null) : null;
    }

    /** @return list<string> */
    public function messageIds(): array
    {
        return array_keys($this->messages);
    }
}
