<?php

namespace App\Service;

use LogicException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RedisFilebotJobPendingStatusReader implements FilebotJobPendingStatusReaderInterface
{
    public function __construct(
        #[Autowire(env: 'MESSENGER_TRANSPORT_DSN')]
        private string $transportDsn,
        private RedisConnectionFactory $connectionFactory,
    ) {
    }

    public function findPendingStatuses(array $messageIds): array
    {
        if ([] === $messageIds) {
            return [];
        }

        [$stream, $group] = $this->getStreamAndGroup();
        $wantedIds = array_fill_keys($messageIds, true);
        $redis = $this->connectionFactory->connect();

        try {
            $start = $messageIds[0];
            $end = $messageIds[array_key_last($messageIds)];

            // The requested IDs came from a bounded stream range. Read pending
            // entries from that same range so a detail lookup also finds IDs
            // later in the stream, and so collection lookups stay to one command.
            $pendingMessages = $redis->rawCommand(
                'XPENDING',
                $stream,
                $group,
                $start,
                $end,
                500,
            );

            if (!\is_array($pendingMessages)) {
                throw new RuntimeException('Redis did not return pending FileBot job details.');
            }

            $statuses = [];
            foreach ($pendingMessages as $pendingMessage) {
                if (!\is_array($pendingMessage) || 4 !== \count($pendingMessage)) {
                    continue;
                }

                [$id, $worker, $idleForMs, $deliveryCount] = $pendingMessage;
                if (!\is_string($id) || !isset($wantedIds[$id]) || !\is_string($worker)) {
                    continue;
                }

                $statuses[$id] = [
                    'worker' => $worker,
                    'idleForMs' => (int) $idleForMs,
                    'deliveryCount' => (int) $deliveryCount,
                ];
            }

            return $statuses;
        } finally {
            $redis->close();
        }
    }

    /** @return array{string, string} */
    private function getStreamAndGroup(): array
    {
        $parts = parse_url($this->transportDsn);
        if (false === $parts || !\in_array($parts['scheme'] ?? '', ['redis', 'rediss'], true)) {
            throw new LogicException('Job status inspection requires a Redis Messenger transport.');
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $pathParts = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/'))));

        return [
            $pathParts[0] ?? $query['stream'] ?? 'messages',
            $pathParts[1] ?? $query['group'] ?? 'symfony',
        ];
    }

}
