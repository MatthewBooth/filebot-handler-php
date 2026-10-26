<?php

namespace App\Logging;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class FilebotJobLog
{
    private bool $writable;

    public function __construct(
        private readonly ?string $file,
        private readonly LoggerInterface $logger,
    ) {
        $this->writable = $file !== null;
    }

    public function write(string $level, string $message): void
    {
        if ($this->file === null || !$this->writable) {
            return;
        }

        $line = sprintf("[%s] %s %s\n", new DateTimeImmutable()->format(DATE_ATOM), strtoupper($level), $message);

        if (@file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX) === false) {
            $this->writable = false;
            $this->logger->warning('Unable to append to per-torrent FileBot log file.', [
                'log_file' => $this->file,
            ]);
        }
    }
}
