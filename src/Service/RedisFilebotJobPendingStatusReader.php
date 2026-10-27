<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class RedisFilebotJobPendingStatusReader implements FilebotJobPendingStatusReaderInterface
{
    public function __construct(
        #[Autowire(env: 'MESSENGER_TRANSPORT_DSN')]
        private string $transportDsn,
    ) {
    }

    public function findPendingStatuses(array $messageIds): array
    {
        if ([] === $messageIds) {
            return [];
        }

        [$stream, $group] = $this->getStreamAndGroup();
        $wantedIds = array_fill_keys($messageIds, true);
        $redis = $this->connect();

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
                throw new \RuntimeException('Redis did not return pending FileBot job details.');
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
            throw new \LogicException('Job status inspection requires a Redis Messenger transport.');
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $pathParts = array_values(array_filter(explode('/', trim($parts['path'] ?? '', '/'))));

        return [
            $pathParts[0] ?? $query['stream'] ?? 'messages',
            $pathParts[1] ?? $query['group'] ?? 'symfony',
        ];
    }

    private function connect(): \Redis
    {
        $parts = parse_url($this->transportDsn);
        if (false === $parts || !isset($parts['host'])) {
            throw new \LogicException('The Redis Messenger transport DSN must include a Redis host.');
        }

        $host = 'rediss' === ($parts['scheme'] ?? '') ? 'tls://'.$parts['host'] : $parts['host'];
        $redis = new \Redis();
        if (!$redis->connect($host, $parts['port'] ?? 6379, 2.5)) {
            throw new \RuntimeException('Unable to connect to Redis to inspect FileBot job status.');
        }

        if (isset($parts['pass'])) {
            $password = rawurldecode($parts['pass']);
            $username = isset($parts['user']) ? rawurldecode($parts['user']) : '';
            $auth = '' !== $username && 'default' !== $username
                ? [$username, $password]
                : $password;

            if (!$redis->auth($auth)) {
                throw new \RuntimeException('Unable to authenticate with Redis to inspect FileBot job status.');
            }
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $database = filter_var($query['dbindex'] ?? 0, FILTER_VALIDATE_INT);
        if (false === $database || !$redis->select($database)) {
            throw new \RuntimeException('Unable to select the Redis database for FileBot job status.');
        }

        return $redis;
    }
}
