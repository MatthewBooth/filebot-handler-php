<?php

namespace App\Service;

use App\Config\FilebotConfig;
use App\Model\FilebotJobModel;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

final readonly class FilebotRunnerService implements FilebotRunnerInterface
{
    private const int NOOP_EXIT_CODE = 100;
    private const string MEDIA_FILE_PATTERN = '/\.(?:3gp|avi|flv|m2ts|m4v|mkv|mov|mp4|mpeg|mpg|mts|ogv|ts|webm|wmv)$/i';

    public function __construct(
        private LoggerInterface $logger,
        private FilebotConfig $config,
        private Filesystem $filesystem,
        private Finder $finder,
        #[Autowire('%kernel.logs_dir%')]
        private string $logsDirectory,
    ) {
    }

    public function run(FilebotJobModel $job): void
    {
        $jobLogFile = $this->createJobLogFile($job);
        $logFileWritable = $jobLogFile !== null;

        if ($jobLogFile !== null) {
            $this->writeJobLog($jobLogFile, $logFileWritable, LogLevel::INFO, 'Received FileBot job: ' . json_encode(
                $job->toLogContext(),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
            ));
        }

        foreach ($job->paths as $path) {
            $this->logger->info('Processing path: ' . $path);
            $this->writeJobLog($jobLogFile, $logFileWritable, 'INFO', 'Processing path: ' . $path);

            $resolvedPath = $this->resolvePath($path);

            if (!$this->filesystem->exists($resolvedPath)) {
                throw new FileNotFoundException('Path does not exist: ' . $path, Response::HTTP_BAD_REQUEST);
            }

            $command = $this->buildCommand($resolvedPath, $job);

            $this->logger->debug('Prepared FileBot command.', [
                'path' => $path,
                'command' => $this->redactSensitiveArguments($command),
            ]);

            $process = new Process($command);
            $process->setTimeout(null);
            $outputBuffers = [Process::OUT => '', Process::ERR => ''];
            $logLine = function (string $type, string $line) use ($path, $jobLogFile, &$logFileWritable): void {
                if (trim($line) === '') {
                    return;
                }

                $level = $type === Process::ERR ? LogLevel::WARNING : LogLevel::INFO;
                $message = 'FileBot: ' . $line;
                $this->writeJobLog($jobLogFile, $logFileWritable, $level, $message);
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
            $isNoMatch = $this->isNoMatchNoop($process);
            $isNoop = $exitCode === self::NOOP_EXIT_CODE || $this->isExactDuplicateNoop($process) || $isNoMatch;

            if (!$process->isSuccessful() && !$isNoop) {
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
            $result = match (true) {
                $isNoMatch => 'no_match',
                $isNoop => 'noop',
                default => 'processed',
            };

            $this->writeJobLog($jobLogFile, $logFileWritable, LogLevel::INFO, $message);
            $this->logger->info($message, [
                'path' => $path,
                'exit_code' => $exitCode,
                'result' => $result,
            ]);
        }
    }

    private function createJobLogFile(FilebotJobModel $job): ?string
    {
        if ($job->paths === []) {
            return null;
        }

        $firstPath = $this->resolvePath($job->paths[0]);
        $torrentName = is_dir($firstPath) ? basename($firstPath) : pathinfo($firstPath, PATHINFO_FILENAME);
        $torrentName = trim($torrentName, " .");
        $torrentName = preg_replace('/[\\\\\/<>:"|?*\x00-\x1F\x7F]+/', '_', $torrentName) ?? '';
        $torrentName = substr($torrentName, 0, 240);

        if ($torrentName === '' || $torrentName === '.' || $torrentName === '..') {
            $torrentName = 'filebot-job';
        }

        $logDirectory = $this->logsDirectory . '/filebot';

        try {
            $this->filesystem->mkdir($logDirectory);
        } catch (IOExceptionInterface $exception) {
            $this->logger->warning('Unable to create per-torrent FileBot log directory.', [
                'log_directory' => $logDirectory,
                'exception' => $exception,
            ]);

            return null;
        }

        $logFile = $logDirectory . '/' . $torrentName . '.log';

        if (@file_put_contents($logFile, sprintf("\n[%s] Starting FileBot job.\n", new DateTimeImmutable()->format(DATE_ATOM)), FILE_APPEND | LOCK_EX) === false) {
            $this->logger->warning('Unable to write per-torrent FileBot log file.', [
                'log_file' => $logFile,
            ]);

            return null;
        }

        return $logFile;
    }

    private function writeJobLog(?string $logFile, bool &$logFileWritable, string $level, string $message): void
    {
        if ($logFile === null || !$logFileWritable) {
            return;
        }

        $line = sprintf("[%s] %s %s\n", new DateTimeImmutable()->format(DATE_ATOM), strtoupper($level), $message);

        if (@file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX) === false) {
            $logFileWritable = false;
            $this->logger->warning('Unable to append to per-torrent FileBot log file.', [
                'log_file' => $logFile,
            ]);
        }
    }

    private function isNoMatchNoop(Process $process): bool
    {
        if ($process->getExitCode() !== 3 || $process->getErrorOutput() !== '') {
            return false;
        }

        $stdout = preg_split('/\R/', trim($process->getOutput()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $expectedLines = [
            'No missing subtitles',
            'Failed to identify or process any files',
            'Finished without processing any files',
        ];

        foreach ($stdout as $line) {
            if (!in_array($line, $expectedLines, true)) {
                return false;
            }
        }

        return in_array('Failed to identify or process any files', $stdout, true)
            && in_array('Finished without processing any files', $stdout, true);
    }

    private function isExactDuplicateNoop(Process $process): bool
    {
        if ($process->getExitCode() !== 3) {
            return false;
        }

        $stdout = preg_split('/\R/', trim($process->getOutput()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stderr = preg_split('/\R/', trim($process->getErrorOutput()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $exactCopyFailureCount = 0;
        $finishedWithoutProcessing = false;

        foreach ($stdout as $line) {
            if ($line === 'Finished without processing any files') {
                $finishedWithoutProcessing = true;
                continue;
            }

            if (preg_match('/^Failed to process \[.*\] because \[.*\] is an exact copy and already exists(?: \[Last-Modified: .*\])?$/', $line) === 1) {
                ++$exactCopyFailureCount;
                continue;
            }

            if ($line === 'No missing subtitles') {
                continue;
            }

            return false;
        }

        if (array_any($stderr, fn($line) => preg_match('/^Failed to detect subtitle language: .*: Subtitle format not supported$/', $line) !== 1)) {
            return false;
        }

        return $exactCopyFailureCount > 0 && $finishedWithoutProcessing;
    }

    private function generateKind(string $path): string
    {
        if (is_file($path)) {
            return 'single';
        }

        if (is_dir($path)) {
            $mediaFiles = (clone $this->finder)
                ->files()
                ->in($path)
                ->name(self::MEDIA_FILE_PATTERN);
            $mediaFileCount = count($mediaFiles);

            if ($mediaFileCount === 1) {
                return 'single';
            }

            if ($mediaFileCount > 1) {
                return 'multi';
            }
        }

        throw new RuntimeException('Path does not contain any media files: ' . $path, Response::HTTP_BAD_REQUEST);
    }

    /**
     * @return list<string>
     */
    private function buildCommand(string $path, FilebotJobModel $job): array
    {
        $utKind = $this->generateKind($path);
        $utTitle = basename($path);

        $command = [
            'filebot',
            '-script',
            'fn:amc',
            '--output',
            $this->config->storageRoot,
            '--action',
            $job->action,
            '--conflict',
            $job->conflict,
            '--lang',
            $this->config->filebotLang,
            '--log',
            $this->config->filebotLogLevel,
            '--encoding',
            'utf8',
            '-no-xattr',
            '--order',
            $job->order
        ];

        if (!$job->strict) {
            $command[] = '-non-strict';
        }

        if ($job->mediaId !== '') {
            $command[] = '--filter';
            $command[] = 'id in [' . $job->mediaId . ']';
        }

        $format = <<<'GROOVY'
            {plex.derive{
                def tags = [
                    any{vf}{null},
                    any{vc}{null},
                    any{hdr}{SDR},
                    any{ac}{null}
                ].findAll{ it }

                tags ? " [${tags.join(' ')}]" : ''
            }.tail}
        GROOVY;
        $format = ltrim($format);

        $defs = [
            'unsorted=' . ($this->config->amcUnsorted ? 'y' : 'n'),
            'clean=' . ($this->config->amcClean ? 'y' : 'n'),
            'artwork=' . ($job->artwork ? 'y' : 'n'),
            'subtitles=' . $this->config->amcSubtitleLang,
            'ut_dir=' . $path,
            'ut_kind=' . $utKind,
            'ut_label=' . $job->label,
            'ut_title=' . $utTitle,
            'movieDB=' . $this->config->amcMovieDb,
            'seriesDB=' . $this->config->amcSeriesDb,
            'animeDB=' . $this->config->amcAnimeDb,
            'minLengthMS=' . $this->config->amcMinLength,
            'minFileSize=' . $this->config->amcMinFilesize,
            'movieFormat=' . $this->config->storageRoot . '/Films/' . $format,
            'seriesFormat=' . $this->config->storageRoot . '/TV/' . $format,
            'animeFormat=' . $this->config->storageRoot . '/TV/' . $format,
        ];

        if ($this->config->plexHost && $this->config->plexToken) {
            $defs[] = 'plex=' . $this->config->plexHost . ':' . $this->config->plexToken;
        }

        foreach ($defs as $def) {
            $command[] = '--def';
            $command[] = $def;
        }

        return $command;
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

    private function resolvePath(string $path): string
    {
        if (!Path::isAbsolute($path)) {
            throw new RuntimeException('Path must be absolute: ' . $path, Response::HTTP_BAD_REQUEST);
        }

        return Path::canonicalize($path);
    }
}
