<?php

namespace App\Service;

use App\Command\FilebotCommandBuilder;
use App\Message\FilebotJobMessage;
use App\Model\FilebotRunResult;
use App\Process\FilebotProcessOutputClassifier;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Process\Process;

final readonly class FilebotRunnerService implements FilebotRunnerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private FilebotCommandBuilder $commandBuilder,
    ) {
    }

    public function run(FilebotJobMessage $job): FilebotRunResult
    {
        $path = $job->path;

        $this->logger->info('Processing path: ' . $path);
        $command = $this->commandBuilder->build($path, $job);

        $this->logger->debug('Prepared FileBot command.', [
            'path' => $path,
            'command' => $this->redactSensitiveArguments($command),
        ]);

        $process = new Process($command);
        $process->setTimeout(null);
        $process->disableOutput();

        $outputBuffers = [Process::OUT => '', Process::ERR => ''];
        $classifier = new FilebotProcessOutputClassifier();
        $logLine = function (string $type, string $line) use ($path, $classifier): void {
            if (trim($line) === '') {
                return;
            }

            $classifier->addLine($type, $line);
            $level = $type === Process::ERR ? LogLevel::WARNING : LogLevel::INFO;
            $message = 'FileBot: ' . $line;
            $this->logger->log($level, $message, [
                'path' => $path,
                'stream' => $type === Process::ERR ? 'stderr' : 'stdout',
            ]);
        };

        $process->run(function (string $type, string $chunk) use (&$outputBuffers, $logLine): void {
            $outputBuffers[$type] .= str_replace(["\r\n", "\r"], "\n", $chunk);

            while (($newline = strpos($outputBuffers[$type], "\n")) !== false) {
                $line = substr($outputBuffers[$type], 0, $newline);
                $outputBuffers[$type] = substr($outputBuffers[$type], $newline + 1);
                $logLine($type, $line);
            }
        });

        foreach ($outputBuffers as $type => $buffer) {
            $logLine($type, $buffer);
        }

        $exitCode = $process->getExitCode();
        $result = $classifier->classify($exitCode ?? 1);
        $isNoMatch = $result === 'no_match';
        $isNoop = $result === 'noop';

        if (!$process->isSuccessful() && $result === null) {
            $this->logger->error('FileBot command failed.', [
                'path' => $path,
                'exit_code' => $exitCode,
            ]);

            $failureReason = $classifier->failureReason();
            $message = sprintf('FileBot command failed with exit code %d.', $exitCode ?? 1);
            if (null !== $failureReason) {
                $message .= ' ' . $failureReason;
            }

            throw new RuntimeException($message, $exitCode ?? 1);
        }

        $message = match (true) {
            $isNoMatch => 'FileBot found no matching media; no files were processed.',
            $isNoop => 'FileBot command completed without changes.',
            default => 'FileBot command completed.',
        };
        $this->logger->info($message, [
            'path' => $path,
            'exit_code' => $exitCode,
            'result' => $result ?? 'processed',
        ]);

        if ($job->action === 'test') {
            return FilebotRunResult::skipped('Test mode; no files were changed.');
        }

        if ($isNoMatch) {
            return FilebotRunResult::skipped('No matching media was found.');
        }

        if ($isNoop) {
            return FilebotRunResult::skipped('Destination files already existed; no changes were made.');
        }

        return FilebotRunResult::completed();
    }

    /**
     * @param list<string> $command
     * @return list<string>
     */
    private function redactSensitiveArguments(array $command): array
    {
        foreach ($command as $index => $argument) {
            if (
                $argument === '--def'
                && isset($command[$index + 1])
                && str_starts_with($command[$index + 1], 'plex=')
            ) {
                $command[$index + 1] = 'plex=***';
            }
        }

        return $command;
    }
}
