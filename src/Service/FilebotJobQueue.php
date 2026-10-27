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
    ) {
    }

    /**
     * @return list<array{id: string, message: array<string, string|bool>}>
     */
    public function list(int $limit = 100): array
    {
        $receiver = $this->getListableReceiver();
        $jobs = [];

        foreach ($receiver->all($limit) as $envelope) {
            if (null !== $job = $this->formatJob($envelope)) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @return array{id: string, message: array<string, string|bool>}|null
     */
    public function find(string $id): ?array
    {
        $envelope = $this->getListableReceiver()->find($id);

        return null === $envelope ? null : $this->formatJob($envelope);
    }

    public function delete(string $id): bool
    {
        $receiver = $this->getListableReceiver();
        $envelope = $receiver->find($id);

        if (null === $envelope || !($envelope->getMessage() instanceof FilebotJobMessage)) {
            return false;
        }

        // Redis Messenger removes an unconsumed entry when it is acknowledged
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

    /**
     * @return array{id: string, message: array<string, string|bool>}|null
     */
    private function formatJob(Envelope $envelope): ?array
    {
        $message = $envelope->getMessage();
        $messageId = $envelope->last(TransportMessageIdStamp::class)?->getId();

        if (!$message instanceof FilebotJobMessage || !is_string($messageId)) {
            return null;
        }

        return [
            'id' => $messageId,
            'message' => $message->toLogContext(),
        ];
    }
}
