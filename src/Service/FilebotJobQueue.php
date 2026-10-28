<?php

namespace App\Service;

use App\Message\FilebotJobMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final readonly class FilebotJobQueue
{
    public function __construct(
        #[Autowire(service: 'messenger.transport.async')]
        private TransportInterface $transport,
        private FilebotJobPendingStatusReaderInterface $pendingStatusReader,
    ) {
    }

    /**
     * @return list<array{id: string, status: 'queued'|'processing', message: array<string, string|bool>}|array{id: string, status: 'queued'|'processing', message: array<string, string|bool>, worker: string, idleForMs: int, deliveryCount: int}>
     */
    public function list(int $limit = 100): array
    {
        $receiver = $this->getListableReceiver();
        $envelopes = [];
        $messageIds = [];

        foreach ($receiver->all($limit) as $envelope) {
            if (!($envelope->getMessage() instanceof FilebotJobMessage)) {
                continue;
            }

            $id = $this->getMessageId($envelope);
            if (null === $id) {
                continue;
            }

            $envelopes[] = $envelope;
            $messageIds[] = $id;
        }

        $pendingStatuses = $this->pendingStatusReader->findPendingStatuses($messageIds);
        $jobs = [];

        foreach ($envelopes as $envelope) {
            $id = $this->getMessageId($envelope);
            $jobs[] = $this->formatJob($envelope, $pendingStatuses[$id] ?? null);
        }

        return $jobs;
    }

    /**
     * @return array{id: string, status: 'queued'|'processing', message: array<string, string|bool>}|array{id: string, status: 'queued'|'processing', message: array<string, string|bool>, worker: string, idleForMs: int, deliveryCount: int}|null
     */
    public function find(string $id): ?array
    {
        $envelope = $this->getListableReceiver()->find($id);

        if (null === $envelope || !($envelope->getMessage() instanceof FilebotJobMessage)) {
            return null;
        }

        $pendingStatuses = $this->pendingStatusReader->findPendingStatuses([$id]);

        return $this->formatJob($envelope, $pendingStatuses[$id] ?? null);
    }

    public function delete(string $id): bool
    {
        $receiver = $this->getListableReceiver();
        $envelope = $receiver->find($id);

        if (null === $envelope || !($envelope->getMessage() instanceof FilebotJobMessage)) {
            return false;
        }

        // Redis Messenger removes an unconsumed entry when it is acknowledged,
        // and delete_after_ack is enabled on the transport.
        $receiver->ack($envelope);

        return true;
    }

    private function getListableReceiver(): ListableReceiverInterface
    {
        if (!$this->transport instanceof ListableReceiverInterface) {
            throw new \LogicException('The configured Messenger transport does not support listing messages.');
        }

        return $this->transport;
    }

    private function getMessageId(Envelope $envelope): ?string
    {
        $messageId = $envelope->last(TransportMessageIdStamp::class)?->getId();

        return \is_string($messageId) ? $messageId : null;
    }

    /**
     * @param array{worker: string, idleForMs: int, deliveryCount: int}|null $pendingStatus
     *
     * @return array{id: string, status: 'queued'|'processing', message: array<string, string|bool>}|array{id: string, status: 'queued'|'processing', message: array<string, string|bool>, worker: string, idleForMs: int, deliveryCount: int}
     */
    private function formatJob(Envelope $envelope, ?array $pendingStatus): array
    {
        $message = $envelope->getMessage();
        $id = $this->getMessageId($envelope);

        if (!$message instanceof FilebotJobMessage || null === $id) {
            throw new \LogicException('A queued FileBot job must have a Messenger transport ID.');
        }

        $job = [
            'id' => $id,
            'status' => null === $pendingStatus ? 'queued' : 'processing',
            'message' => $message->toLogContext(),
        ];

        return null === $pendingStatus ? $job : [...$job, ...$pendingStatus];
    }
}
