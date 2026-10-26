<?php

namespace App\MessageHandler;

use App\Message\FilebotJobMessage;
use App\Service\FilebotRunnerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class FilebotJobMessageHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private FilebotRunnerInterface $filebotRunner,
    ) {
    }

    public function __invoke(FilebotJobMessage $job): void
    {
        $this->logger->info('Received FileBot job.', $job->toLogContext());
        $this->filebotRunner->run($job);
        $this->logger->info('FileBot job completed.', $job->toLogContext());
    }
}
