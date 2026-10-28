<?php

namespace App\Tests\Support;

use App\Message\FilebotJobMessage;
use App\Service\FilebotJobHistoryStoreInterface;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class InMemoryFilebotJobHistoryStore implements FilebotJobHistoryStoreInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $jobs = [];

    public function recordQueued(FilebotJobMessage $job): void
    {
        $now = $this->now();
        $this->jobs[$job->jobId] = [
            'id' => $job->jobId,
            'status' => 'queued',
            'message' => $job->toLogContext(),
            'createdAt' => $now,
            'updatedAt' => $now,
        ];
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
        $jobs = array_filter(
            $this->jobs,
            static fn (array $job): bool => \in_array($job['status'] ?? null, ['completed', 'failed', 'deleted'], true),
        );
        usort($jobs, static fn (array $first, array $second): int => strcmp($second['finishedAt'], $first['finishedAt']));

        return array_slice($jobs, 0, $limit);
    }

    /** @param array<string, string> $fields */
    private function update(string $jobId, array $fields): void
    {
        if (!isset($this->jobs[$jobId])) {
            return;
        }

        $this->jobs[$jobId] = [...$this->jobs[$jobId], ...$fields, 'updatedAt' => $this->now()];
    }

    /** @param array{status: string, error?: string} $fields */
    private function markTerminal(string $jobId, array $fields): void
    {
        $now = $this->now();
        $this->update($jobId, [...$fields, 'finishedAt' => $now]);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
