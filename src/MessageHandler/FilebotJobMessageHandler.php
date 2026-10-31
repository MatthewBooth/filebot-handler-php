<?php

namespace App\MessageHandler;

use App\Message\FilebotJobMessage;
use App\Service\FilebotJobHistoryStoreInterface;
use App\Service\FilebotRunnerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FilebotJobMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private FilebotRunnerInterface $filebotRunner,
        private ?FilebotJobHistoryStoreInterface $history = null,
    ) {
    }

    public function __invoke(FilebotJobMessage $job): void
    {
        $this->logger->info('Received FileBot job.', $job->toLogContext());
        $this->updateHistory($job, 'processing');

        try {
            $result = $this->filebotRunner->run($job);
        } catch (Throwable $exception) {
            $this->updateHistory($job, 'failed', $exception);
            throw $exception;
        }

        if ($result->status === 'skipped') {
            $this->updateHistory($job, 'skipped', reason: $result->reason);
            $this->logger->info('FileBot job skipped.', [...$job->toLogContext(), 'reason' => $result->reason]);
            return;
        }

        $this->updateHistory($job, 'completed');
        $this->logger->info('FileBot job completed.', $job->toLogContext());
    }

    private function updateHistory(FilebotJobMessage $job, string $status, ?Throwable $exception = null, ?string $reason = null): void
    {
        if (null === $this->history) {
            return;
        }

        try {
            match ($status) {
                'processing' => $this->history->markProcessing($job, gethostname() ?: 'unknown'),
                'completed' => $this->history->markCompleted($job),
                'skipped' => $this->history->markSkipped($job, $reason ?? 'Job was skipped.'),
                'failed' => $this->history->markFailed($job, $exception ?? new LogicException('A failed job requires an exception.')),
            };
        } catch (Throwable $historyException) {
            $this->logger->error('Failed to update FileBot job history.', [
                'job_id' => $job->jobId,
                'status' => $status,
                'exception' => $historyException,
            ]);
        }
    }
}
