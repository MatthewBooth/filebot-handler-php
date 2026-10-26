<?php

namespace App\Service;

use App\Config\FilebotConfig;
use App\Model\FilebotJobModel;
use RuntimeException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class FilebotRunnerService implements FilebotRunnerInterface
{
    private const int NOOP_EXIT_CODE = 100;
    private const string MEDIA_FILE_PATTERN = '/\.(?:3gp|avi|flv|m2ts|m4v|mkv|mov|mp4|mpeg|mpg|mts|ogv|ts|webm|wmv)$/i';

    public function __construct(
        private LoggerInterface $logger,
        private FilebotConfig $config,
        private Filesystem $filesystem,
        private Finder $finder
    )
    {
    }

    public function run(FilebotJobModel $job): void
    {
        foreach ($job->paths as $path) {
            $this->logger->info('Processing path: ' . $path);

            if (!$this->validatePath($path)) {
                throw new FileNotFoundException('Path does not exist: ' . $path, Response::HTTP_BAD_REQUEST);
            }

            $command = $this->buildCommand($path, $job);

            $this->logger->debug('Prepared FileBot command.', [
                'path' => $path,
                'command' => $command
            ]);

            $process = new Process($command);
            $process->setTimeout(null);
            $outputBuffers = [Process::OUT => '', Process::ERR => ''];
            $logLine = function (string $type, string $line) use ($path): void {
                if (trim($line) === '') {
                    return;
                }

                $this->logger->log($type === Process::ERR ? LogLevel::WARNING : LogLevel::INFO, 'FileBot: ' . $line, [
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
            $isNoop = $exitCode === self::NOOP_EXIT_CODE || $this->isExactDuplicateNoop($process);

            if (!$process->isSuccessful() && !$isNoop) {
                throw new ProcessFailedException($process);
            }

            $this->logger->info($isNoop ? 'FileBot command completed without changes.' : 'FileBot command completed.', [
                'path' => $path,
                'exit_code' => $exitCode,
                'result' => $isNoop ? 'noop' : 'processed',
            ]);
        }
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

    private function validatePath(string $path): bool
    {
        return $this->filesystem->exists($this->resolvePath($path));
    }

    private function generateKind(string $path): string
    {
        $file = $this->resolvePath($path);

        if (is_file($file)) {
            return 'single';
        }

        if (is_dir($file)) {
            $mediaFiles = (clone $this->finder)
                ->files()
                ->in($file)
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
        $file = $this->resolvePath($path);
        $utKind = $this->generateKind($path);
        $utTitle = basename($file);

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

        $defs = [
            "unsorted=" . ($this->config->amcUnsorted ? 'y' : 'n'),
            "clean=" . ($this->config->amcClean ? 'y' : 'n'),
            "artwork=" . ($job->artwork ? 'y' : 'n'),
            "subtitles=" . $this->config->amcSubtitleLang,
            "ut_dir=" . $file,
            "ut_kind=" . $utKind,
            "ut_label=" . $job->label,
            "ut_title=" . $utTitle,
            "movieDB=" . $this->config->amcMovieDb,
            "seriesDB=" . $this->config->amcSeriesDb,
            "animeDB=" . $this->config->amcAnimeDb,
            "minLengthMS=" . $this->config->amcMinLength,
            "minFileSize=" . $this->config->amcMinFilesize,
            "movieFormat=" . $this->config->storageRoot . '/Films/' . $format,
            "seriesFormat=" . $this->config->storageRoot . '/TV/' . $format,
            "animeFormat=" . $this->config->storageRoot . '/TV/' . $format,
        ];

        if ($this->config->plexHost && $this->config->plexToken) {
            $defs[] = "plex=" . $this->config->plexHost . ":" . $this->config->plexToken;
        }

        foreach ($defs as $def) {
            $command[] = "--def";
            $command[] = $def;
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
