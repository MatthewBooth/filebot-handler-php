<?php

namespace App\Service;

use App\Message\FilebotJobMessage;
use Throwable;

interface FilebotJobHistoryStoreInterface
{
    public function recordQueued(FilebotJobMessage $job): void;

    public function attachTransportId(string $jobId, string $transportId): void;

    public function markProcessing(FilebotJobMessage $job, string $worker): void;

    public function markCompleted(FilebotJobMessage $job): void;

    public function markSkipped(FilebotJobMessage $job, string $reason): void;

    public function markFailed(FilebotJobMessage $job, Throwable $exception): void;

    public function markDeleted(string $jobId): void;

    /** @return list<array<string, mixed>> */
    public function recentTerminalJobs(int $limit, int $offset = 0): array;

    public function countRecentTerminalJobs(): int;
}
