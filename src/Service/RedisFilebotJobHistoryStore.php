<?php

namespace App\Service;

use App\Message\FilebotJobMessage;
use DateTimeImmutable;
use DateTimeZone;
use Redis;
use RuntimeException;
use Throwable;

final readonly class RedisFilebotJobHistoryStore implements FilebotJobHistoryStoreInterface
{
    private const int RETENTION_SECONDS = 172800;
    private const string PREFIX = 'filebot:job-history';

    public function __construct(private RedisConnectionFactory $connectionFactory)
    {
    }

    public function recordQueued(FilebotJobMessage $job): void
    {
        $redis = $this->connectionFactory->connect();
        $now = $this->now();
        $key = $this->jobKey($job->jobId);

        try {
            if (!$redis->hMSet($key, [
                'id' => $job->jobId,
                'status' => 'queued',
                'message' => json_encode($job->toLogContext(), JSON_THROW_ON_ERROR),
                'createdAt' => $now,
                'updatedAt' => $now,
            ]) || !$redis->expire($key, self::RETENTION_SECONDS)) {
                throw new RuntimeException('Unable to write queued FileBot job history to Redis.');
            }

            $this->pruneTerminalIndex($redis, time() - self::RETENTION_SECONDS);
        } finally {
            $redis->close();
        }
    }

    public function attachTransportId(string $jobId, string $transportId): void
    {
        $this->update($jobId, ['transportId' => $transportId]);
    }

    public function markProcessing(FilebotJobMessage $job, string $worker): void
    {
        $this->update($job->jobId, [
            'status' => 'processing',
            'worker' => $worker,
            'startedAt' => $this->now(),
        ]);
    }

    public function markCompleted(FilebotJobMessage $job): void
    {
        $this->markTerminal($job->jobId, ['status' => 'completed']);
    }

    public function markFailed(FilebotJobMessage $job, Throwable $exception): void
    {
        $this->markTerminal($job->jobId, [
            'status' => 'failed',
            'error' => substr($exception->getMessage(), 0, 1000),
        ]);
    }

    public function markDeleted(string $jobId): void
    {
        $this->markTerminal($jobId, ['status' => 'deleted']);
    }

    public function recentTerminalJobs(int $limit): array
    {
        $limit = max(1, min($limit, 200));
        $redis = $this->connectionFactory->connect();

        try {
            $this->pruneTerminalIndex($redis, time() - self::RETENTION_SECONDS);
            $jobIds = $redis->zRevRange(self::terminalIndexKey(), 0, $limit - 1);
            if (!\is_array($jobIds)) {
                throw new RuntimeException('Redis did not return FileBot job history IDs.');
            }

            $history = [];
            foreach ($jobIds as $jobId) {
                if (!\is_string($jobId)) {
                    continue;
                }

                $record = $redis->hGetAll($this->jobKey($jobId));
                if (!\is_array($record) || [] === $record) {
                    $redis->zRem(self::terminalIndexKey(), $jobId);
                    continue;
                }

                $message = json_decode($record['message'] ?? '', true);
                if (!\is_array($message)) {
                    continue;
                }

                $history[] = [
                    'id' => $record['id'] ?? $jobId,
                    'transportId' => $record['transportId'] ?? null,
                    'status' => $record['status'] ?? 'unknown',
                    'message' => $message,
                    'createdAt' => $record['createdAt'] ?? '',
                    'finishedAt' => $record['finishedAt'] ?? '',
                    'worker' => $record['worker'] ?? null,
                    'error' => $record['error'] ?? null,
                ];
            }

            return $history;
        } finally {
            $redis->close();
        }
    }

    /** @param array<string, string> $fields */
    private function update(string $jobId, array $fields): void
    {
        $redis = $this->connectionFactory->connect();

        try {
            $key = $this->jobKey($jobId);
            if (!$redis->exists($key)) {
                return;
            }

            $fields['updatedAt'] = $this->now();
            if (!$redis->hMSet($key, $fields)) {
                throw new RuntimeException('Unable to update FileBot job history in Redis.');
            }

            if ('processing' === ($fields['status'] ?? null)) {
                $redis->hDel($key, 'finishedAt', 'error');
                $redis->zRem(self::terminalIndexKey(), $jobId);
            }
        } finally {
            $redis->close();
        }
    }

    /** @param array{status: string, error?: string} $fields */
    private function markTerminal(string $jobId, array $fields): void
    {
        $redis = $this->connectionFactory->connect();

        try {
            $key = $this->jobKey($jobId);
            if (!$redis->exists($key)) {
                return;
            }

            $now = $this->now();
            $fields['updatedAt'] = $now;
            $fields['finishedAt'] = $now;

            if (!$redis->hMSet($key, $fields) || !$redis->expire($key, self::RETENTION_SECONDS)) {
                throw new RuntimeException('Unable to finish FileBot job history in Redis.');
            }

            if (false === $redis->zAdd(self::terminalIndexKey(), time(), $jobId)) {
                throw new RuntimeException('Unable to index FileBot job history in Redis.');
            }

            $redis->expire(self::terminalIndexKey(), self::RETENTION_SECONDS);
        } finally {
            $redis->close();
        }
    }

    private function pruneTerminalIndex(Redis $redis, int $cutoff): void
    {
        $redis->zRemRangeByScore(self::terminalIndexKey(), '-inf', (string) $cutoff);
    }

    private function jobKey(string $jobId): string
    {
        return self::PREFIX.':'.$jobId;
    }

    private static function terminalIndexKey(): string
    {
        return self::PREFIX.':terminal';
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
