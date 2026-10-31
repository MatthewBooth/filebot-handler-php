<?php

namespace App\Process;

final class FilebotProcessOutputClassifier
{
    private const int NOOP_EXIT_CODE = 100;
    private const int FILEBOT_FAILURE_EXIT_CODE = 3;
    private const string EXACT_COPY_PATTERN = '/^Failed to process \[.*\] because \[.*\] is an exact copy and already exists(?: \[Last-Modified: .*\])?$/';
    private const string UNSUPPORTED_SUBTITLE_PATTERN = '/^Failed to detect subtitle language: .*: Subtitle format not supported$/';

    private bool $noMatchOutputIsValid = true;
    private bool $duplicateOutputIsValid = true;
    private bool $stderrIsEmpty = true;
    private bool $duplicateStderrIsValid = true;
    private bool $foundNoMatchFailure = false;
    private bool $finishedWithoutProcessing = false;
    private int $exactCopyFailureCount = 0;
    /** @var list<string> */
    private array $failureLines = [];

    public function addLine(string $stream, string $line): void
    {
        if (trim($line) === '') {
            return;
        }

        if (preg_match('/^(?:Failed|Error|Exception)\b/i', $line) === 1) {
            $this->failureLines[] = $line;
        }

        if ($stream === 'err') {
            $this->stderrIsEmpty = false;
            if (preg_match(self::UNSUPPORTED_SUBTITLE_PATTERN, $line) !== 1) {
                $this->duplicateStderrIsValid = false;
            }

            return;
        }

        if ($line === 'No missing subtitles' || $line === 'Finished without processing any files') {
            if ($line === 'Finished without processing any files') {
                $this->finishedWithoutProcessing = true;
            }

            return;
        }

        if ($line === 'Failed to identify or process any files') {
            $this->foundNoMatchFailure = true;
            $this->duplicateOutputIsValid = false;

            return;
        }

        if (preg_match(self::EXACT_COPY_PATTERN, $line) === 1) {
            ++$this->exactCopyFailureCount;
            $this->noMatchOutputIsValid = false;

            return;
        }

        $this->noMatchOutputIsValid = false;
        $this->duplicateOutputIsValid = false;
    }

    public function classify(int $exitCode): ?string
    {
        if ($exitCode === self::NOOP_EXIT_CODE) {
            return 'noop';
        }

        if ($exitCode !== self::FILEBOT_FAILURE_EXIT_CODE) {
            return null;
        }

        if (
            $this->noMatchOutputIsValid
            && $this->stderrIsEmpty
            && $this->foundNoMatchFailure
            && $this->finishedWithoutProcessing
        ) {
            return 'no_match';
        }

        if (
            $this->duplicateOutputIsValid
            && $this->duplicateStderrIsValid
            && $this->exactCopyFailureCount > 0
            && $this->finishedWithoutProcessing
        ) {
            return 'noop';
        }

        return null;
    }

    public function failureReason(): ?string
    {
        foreach ($this->failureLines as $line) {
            if (preg_match(self::EXACT_COPY_PATTERN, $line) !== 1) {
                return substr($line, 0, 500);
            }
        }

        return null;
    }
}
