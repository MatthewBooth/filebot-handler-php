<?php

namespace App\Service;

use App\Command\FilebotCommandBuilder;
use App\Logging\FilebotJobLogWriter;
use App\Message\FilebotJobMessage;
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
        private FilebotJobLogWriter $jobLogWriter,
    ) {
    }

    public function run(FilebotJobMessage $job): void
    {
        $path = $job->path;
        $jobLog = $this->jobLogWriter->open($path);

        $this->logger->info('Processing path: ' . $path);
        $command = $this->commandBuilder->build($path, $job);

        $loggedCommand = json_encode(
            $this->redactSensitiveArguments($command),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
        $jobLog->write(LogLevel::INFO, 'Command: ' . $loggedCommand);

        $this->logger->debug('Prepared FileBot command.', [
            'path' => $path,
            'command' => $this->redactSensitiveArguments($command),
        ]);

        $process = new Process($command);
        $process->setTimeout(null);
        $process->disableOutput();

        $outputBuffers = [Process::OUT => '', Process::ERR => ''];
        $classifier = new FilebotProcessOutputClassifier();
        $logLine = function (string $type, string $line) use ($path, $jobLog, $classifier): void {
            if (trim($line) === '') {
                return;
            }

            $classifier->addLine($type, $line);
            $level = $type === Process::ERR ? LogLevel::WARNING : LogLevel::INFO;
            $message = 'FileBot: ' . $line;
            $jobLog->write($level, $message);
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

            throw new RuntimeException(
                sprintf('FileBot command failed with exit code %d.', $exitCode ?? 1),
                $exitCode ?? 1,
            );
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
