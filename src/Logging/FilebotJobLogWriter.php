<?php

namespace App\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

final readonly class FilebotJobLogWriter
{
    public function __construct(
        private Filesystem $filesystem,
        private LoggerInterface $logger,
        #[Autowire('%kernel.logs_dir%')]
        private string $logsDirectory,
    ) {
    }

    public function open(string $path): FilebotJobLog
    {
        $file = $this->createLogFile($path);

        return new FilebotJobLog($file, $this->logger);
    }

    private function createLogFile(string $path): ?string
    {
        $torrentName = is_dir($path) ? basename($path) : pathinfo($path, PATHINFO_FILENAME);
        $torrentName = trim($torrentName, " .");
        $torrentName = preg_replace('~[\\\\/<>:"|?*\x00-\x1F\x7F]+~', '_', $torrentName) ?? '';
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

        if (@file_put_contents($logFile, '', FILE_APPEND | LOCK_EX) === false) {
            $this->logger->warning('Unable to write per-torrent FileBot log file.', [
                'log_file' => $logFile,
            ]);

            return null;
        }

        return $logFile;
    }
}
