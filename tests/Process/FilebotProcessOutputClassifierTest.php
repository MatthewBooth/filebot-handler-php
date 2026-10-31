<?php

namespace App\Tests\Process;

use App\Process\FilebotProcessOutputClassifier;
use PHPUnit\Framework\TestCase;

final class FilebotProcessOutputClassifierTest extends TestCase
{
    public function testRecognizesNoMatchOutput(): void
    {
        $classifier = new FilebotProcessOutputClassifier();
        $classifier->addLine('out', 'No missing subtitles');
        $classifier->addLine('out', 'Failed to identify or process any files');
        $classifier->addLine('out', 'Finished without processing any files');

        self::assertSame('no_match', $classifier->classify(3));
    }

    public function testRecognizesExactDuplicateOutputAndSubtitleWarnings(): void
    {
        $classifier = new FilebotProcessOutputClassifier();
        $classifier->addLine('out', 'No missing subtitles');
        $classifier->addLine('out', 'Failed to process [input] because [output] is an exact copy and already exists');
        $classifier->addLine('err', 'Failed to detect subtitle language: sample.srt: Subtitle format not supported');
        $classifier->addLine('out', 'Finished without processing any files');

        self::assertSame('noop', $classifier->classify(3));
    }

    public function testDoesNotTreatUnexpectedOutputAsNoop(): void
    {
        $classifier = new FilebotProcessOutputClassifier();
        $classifier->addLine('out', 'Failed to identify or process any files');
        $classifier->addLine('out', 'Unexpected FileBot error');
        $classifier->addLine('out', 'Finished without processing any files');

        self::assertNull($classifier->classify(3));
    }

    public function testExtractsSpecificReasonFromCodeThreeFailure(): void
    {
        $classifier = new FilebotProcessOutputClassifier();
        $classifier->addLine('out', 'Failed to process [/input/episode.mkv] because of a permissions error');
        $classifier->addLine('out', 'Finished without processing any files');

        self::assertNull($classifier->classify(3));
        self::assertSame(
            'Failed to process [/input/episode.mkv] because of a permissions error',
            $classifier->failureReason(),
        );
    }

    public function testRecognizesFilebotNoopExitCode(): void
    {
        self::assertSame('noop', (new FilebotProcessOutputClassifier())->classify(100));
    }
}
