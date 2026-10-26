<?php

namespace App\Tests\Service;

use App\Config\FilebotConfig;
use App\Model\FilebotJobModel;
use App\Service\FilebotRunnerService;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

final class FilebotRunnerServiceTest extends TestCase
{
    private static string $binaryDirectory;
    private static string $invocationLog;
    private string $temporaryRoot;
    private string $originalPath;

    public static function setUpBeforeClass(): void
    {
        self::$binaryDirectory = sys_get_temp_dir() . '/filebot-runner-bin-' . getmypid();
        (new Filesystem())->mkdir(self::$binaryDirectory);
        self::$invocationLog = self::$binaryDirectory . '/invocations.log';

        $fakeFilebot = self::$binaryDirectory . '/filebot';
        $script = <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> __INVOCATION_LOG__
case "$*" in
    *ut_label=process-failure*) printf 'stdout test line\n'; printf 'stderr test line\n' >&2; exit 23 ;;
    *ut_label=process-noop*) printf 'Finished without processing any files\n'; exit 100 ;;
    *ut_label=no-match-noop*)
        printf 'No missing subtitles\n'
        printf 'Failed to identify or process any files\n'
        printf 'Finished without processing any files\n'
        exit 3
        ;;
    *ut_label=duplicate-noop*)
        printf 'No missing subtitles\n'
        printf 'Failed to process [/input/episode.mkv] because [/output/episode.mkv] is an exact copy and already exists [Last-Modified: Mon Oct 05 22:23:17 BST 2026]\n'
        printf 'Finished without processing any files\n'
        printf 'Failed to detect subtitle language: /input/episode.idx: Subtitle format not supported\n' >&2
        exit 3
        ;;
    *ut_label=mixed-failure*)
        printf 'Failed to process [/input/episode.mkv] because [/output/episode.mkv] is an exact copy and already exists\n'
        printf 'Failed to process [/input/other.mkv] because of a permissions error\n'
        printf 'Finished without processing any files\n'
        exit 3
        ;;
esac
printf 'stdout test line\n'
printf 'stderr test line\n' >&2
exit 0
SH;
        $script = str_replace('__INVOCATION_LOG__', escapeshellarg(self::$invocationLog), $script);
        file_put_contents($fakeFilebot, $script . "\n");
        chmod($fakeFilebot, 0755);
    }

    public static function tearDownAfterClass(): void
    {
        (new Filesystem())->remove(self::$binaryDirectory);
    }

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . '/filebot-runner-test-' . bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot);

        $this->originalPath = (string) getenv('PATH');
        putenv('PATH=' . self::$binaryDirectory . PATH_SEPARATOR . $this->originalPath);
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . $this->originalPath);
        (new Filesystem())->remove($this->temporaryRoot);
    }

    public function testRunUsesActionFromJob(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'tv',
            action: 'test',
        ));

        self::assertCount(5, $logger->records);

        self::assertSame('Processing path: ' . $path, $logger->records[0]['message']);
        self::assertSame('Prepared FileBot command.', $logger->records[1]['message']);
        self::assertSame($path, $logger->records[1]['context']['path']);

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'test');
        self::assertCommandContainsOptionValue($command, '--conflict', 'skip');
        self::assertCommandContainsOptionValue($command, '--output', '/media/output');
        self::assertCommandContainsOptionValue($command, '--lang', 'en');
        self::assertCommandContainsOptionValue($command, '--log', 'info');
        self::assertCommandContainsOptionValue($command, '--order', 'Airdate');

        self::assertContains('filebot', $command);
        self::assertContains('-script', $command);
        self::assertContains('fn:amc', $command);
        self::assertContains('-no-xattr', $command);

        self::assertContainsDef($command, 'ut_dir=' . $path);
        self::assertContainsDef($command, 'ut_kind=single');
        self::assertContainsDef($command, 'ut_label=tv');
        self::assertContainsDef($command, 'ut_title=example.mkv');
        self::assertContainsDef($command, 'artwork=y');
        self::assertFileExists(self::$invocationLog);
        self::assertStringContainsString('--action test', file_get_contents(self::$invocationLog));
        self::assertContains('FileBot: stdout test line', array_column($logger->records, 'message'));
        self::assertContains('FileBot: stderr test line', array_column($logger->records, 'message'));
    }

    public function testRunWritesOutputToTorrentNamedLogAndKeepsMainLoggerOutput(): void
    {
        $torrentName = 'Reacher S04E04';
        $torrentPath = $this->temporaryRoot . '/' . $torrentName;
        mkdir($torrentPath);
        touch($torrentPath . '/episode.mkv');

        $logger = new InMemoryLogger();
        $this->createService($logger)->run(new FilebotJobModel(
            paths: [$torrentPath],
            label: 'tv',
        ));

        $logFile = $this->temporaryRoot . '/logs/filebot/' . $torrentName . '.log';
        self::assertFileExists($logFile);
        $content = file_get_contents($logFile);
        self::assertIsString($content);
        self::assertStringContainsString('Processing path: ' . $torrentPath, $content);
        self::assertStringContainsString('FileBot: stdout test line', $content);
        self::assertStringContainsString('FileBot: stderr test line', $content);
        self::assertContains('FileBot: stdout test line', array_column($logger->records, 'message'));
        self::assertContains('FileBot: stderr test line', array_column($logger->records, 'message'));
    }

    public function testRunPassesConflictPolicyDirectly(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'tv',
            conflict: 'replace',
            action: 'move',
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'move');
        self::assertCommandContainsOptionValue($command, '--conflict', 'replace');
    }

    public function testRunFiltersByProvidedMediaId(): void
    {
        $logger = new InMemoryLogger();
        $path = $this->createFile('example.mkv');

        $this->createService($logger)->run(new FilebotJobModel(
            paths: [$path],
            label: 'movie',
            mediaId: '123456',
        ));

        $command = $logger->records[1]['context']['command'];
        self::assertCommandContainsOptionValue($command, '--filter', 'id in [123456]');
        self::assertContainsDef($command, 'movieDB=TheMovieDB');
    }

    public function testRunUsesCopyActionAndConflictPolicyByDefault(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'tv',
            conflict: 'skip',
            action: 'copy',
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'copy');
        self::assertCommandContainsOptionValue($command, '--conflict', 'skip');
    }

    public function testRunAddsNonStrictFlagWhenJobIsNotStrict(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'tv',
        ));

        self::assertContains('-non-strict', $logger->records[1]['context']['command']);
    }

    public function testRunUsesAbsolutePathWithoutAddingTorrentsRoot(): void
    {
        $folderName = 'Futurama.S11E04.Lords.of.the.Ring';
        $folderPath = $this->temporaryRoot . '/' . $folderName;
        mkdir($folderPath);
        touch($folderPath . '/episode-one.mkv');
        touch($folderPath . '/episode-two.mp4');

        $logger = new InMemoryLogger();
        $service = $this->createService($logger);

        $service->run(new FilebotJobModel(
            paths: [$folderPath],
            label: 'tv',
        ));

        self::assertContainsDef($logger->records[1]['context']['command'], 'ut_dir=' . $folderPath);
        self::assertContainsDef($logger->records[1]['context']['command'], 'ut_kind=multi');
    }

    public function testRunRejectsRelativePath(): void
    {
        $service = $this->createService(new InMemoryLogger());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(400);

        $service->run(new FilebotJobModel(
            paths: ['incoming/example.mkv'],
            label: 'tv',
        ));
    }

    public function testRunThrowsWhenFilebotExitsWithFailure(): void
    {
        $path = $this->createFile('example.mkv');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FileBot command failed with exit code 23.');

        $this->createService(new InMemoryLogger())->run(new FilebotJobModel(
            paths: [$path],
            label: 'process-failure',
            action: 'test',
        ));
    }

    public function testRunTreatsFilebotNoopExitCodeAsSuccessful(): void
    {
        $logger = new InMemoryLogger();
        $path = $this->createFile('example.mkv');

        $this->createService($logger)->run(new FilebotJobModel(
            paths: [$path],
            label: 'process-noop',
        ));

        self::assertContains('FileBot: Finished without processing any files', array_column($logger->records, 'message'));
        $completionRecords = array_filter($logger->records, static fn (array $record): bool => $record['message'] === 'FileBot command completed without changes.');
        self::assertCount(1, $completionRecords);
        $completionRecord = reset($completionRecords);
        self::assertSame(100, $completionRecord['context']['exit_code']);
        self::assertSame('noop', $completionRecord['context']['result']);
    }

    public function testRunTreatsNoMatchingMediaAsSuccessfulWithoutRetryableFailure(): void
    {
        $logger = new InMemoryLogger();
        $path = $this->createFile('example.mkv');

        $this->createService($logger)->run(new FilebotJobModel(
            paths: [$path],
            label: 'no-match-noop',
            mediaId: '73871',
            action: 'copy',
        ));

        self::assertContains('FileBot found no matching media; no files were processed.', array_column($logger->records, 'message'));
        $completionRecords = array_filter(
            $logger->records,
            static fn (array $record): bool => $record['message'] === 'FileBot found no matching media; no files were processed.',
        );
        self::assertCount(1, $completionRecords);
        $completionRecord = reset($completionRecords);
        self::assertSame(3, $completionRecord['context']['exit_code']);
        self::assertSame('no_match', $completionRecord['context']['result']);
    }

    public function testRunTreatsExactDuplicateOnlyFailureAsSuccessfulNoop(): void
    {
        $logger = new InMemoryLogger();
        $path = $this->createFile('example.mkv');

        $this->createService($logger)->run(new FilebotJobModel(
            paths: [$path],
            label: 'duplicate-noop',
        ));

        self::assertContains(
            'FileBot: Failed to process [/input/episode.mkv] because [/output/episode.mkv] is an exact copy and already exists [Last-Modified: Mon Oct 05 22:23:17 BST 2026]',
            array_column($logger->records, 'message'),
        );
        $completionRecords = array_filter($logger->records, static fn (array $record): bool => $record['message'] === 'FileBot command completed without changes.');
        self::assertCount(1, $completionRecords);
        $completionRecord = reset($completionRecords);
        self::assertSame(3, $completionRecord['context']['exit_code']);
        self::assertSame('noop', $completionRecord['context']['result']);
    }

    public function testRunStillThrowsWhenDuplicateAndAnotherFailureAreReported(): void
    {
        $path = $this->createFile('example.mkv');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FileBot command failed with exit code 3.');

        $this->createService(new InMemoryLogger())->run(new FilebotJobModel(
            paths: [$path],
            label: 'mixed-failure',
        ));
    }

    public function testRunClassifiesFolderWithOneMediaFileAndSidecarsAsSingle(): void
    {
        $folderName = 'single-episode';
        $folderPath = $this->temporaryRoot . '/' . $folderName;
        mkdir($folderPath);
        touch($folderPath . '/episode.mkv');
        touch($folderPath . '/episode.nfo');
        touch($folderPath . '/episode.eng.srt');
        mkdir($folderPath . '/Screens');
        touch($folderPath . '/Screens/screen0001.jpg');

        $logger = new InMemoryLogger();
        $service = $this->createService($logger);

        $service->run(new FilebotJobModel(
            paths: [$folderPath],
            label: 'tv',
        ));

        self::assertContainsDef($logger->records[1]['context']['command'], 'ut_kind=single');
    }

    public function testRunDoesNotAddArtworkDefinitionWhenArtworkIsDisabled(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'tv',
            artwork: false,
        ));

        self::assertContainsDef($logger->records[1]['context']['command'], 'artwork=n');
    }

    public function testRunIncludesConfiguredAmcDefinitions(): void
    {
        $logger = new InMemoryLogger();
        $service = $this->createService($logger);
        $path = $this->createFile('example.mkv');

        $service->run(new FilebotJobModel(
            paths: [$path],
            label: 'movie',
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertContainsDef($command, 'unsorted=n');
        self::assertContainsDef($command, 'clean=n');
        self::assertContainsDef($command, 'subtitles=en');
        self::assertContainsDef($command, 'movieDB=TheMovieDB');
        self::assertContainsDef($command, 'seriesDB=TheTVDB');
        self::assertContainsDef($command, 'animeDB=AniDB');
        self::assertContainsDef($command, 'minLengthMS=300000');
        self::assertContainsDef($command, 'minFileSize=100000000');
        self::assertFormatDefinition($command, 'movieFormat=/media/output/Films/');
        self::assertFormatDefinition($command, 'seriesFormat=/media/output/TV/');
        self::assertFormatDefinition($command, 'animeFormat=/media/output/TV/');
        self::assertContainsDef($command, 'plex=***');
    }

    private function createConfig(): FilebotConfig
    {
        return new FilebotConfig(
            storageRoot: '/media/output',
            filebotLang: 'en',
            filebotLogLevel: 'info',
            plexHost: 'http://plex.local:32400',
            plexToken: 'secret-token',
            amcUnsorted: false,
            amcClean: false,
            amcSubtitleLang: 'en',
            amcMovieDb: 'TheMovieDB',
            amcSeriesDb: 'TheTVDB',
            amcAnimeDb: 'AniDB',
            amcMinLength: 300000,
            amcMinFilesize: 100000000,
        );
    }

    private function createService(InMemoryLogger $logger): FilebotRunnerService
    {
        return new FilebotRunnerService($logger, $this->createConfig(), new Filesystem(), new Finder(), $this->temporaryRoot . '/logs');
    }

    private function createFile(string $name): string
    {
        $path = $this->temporaryRoot . '/' . $name;
        touch($path);

        return $path;
    }

    /**
     * @param list<string> $command
     */
    private static function assertCommandContainsOptionValue(array $command, string $option, string $expectedValue): void
    {
        $optionIndex = array_search($option, $command, true);

        self::assertIsInt($optionIndex, sprintf('Command does not contain option "%s".', $option));
        self::assertArrayHasKey($optionIndex + 1, $command);
        self::assertSame($expectedValue, $command[$optionIndex + 1]);
    }

    /**
     * @param list<string> $command
     */
    private static function assertContainsDef(array $command, string $expectedDefinition): void
    {
        $definitions = self::extractDefinitions($command);

        self::assertContains($expectedDefinition, $definitions);
    }

    /**
     * @param list<string> $command
     */
    private static function assertFormatDefinition(array $command, string $prefix): void
    {
        $definitions = self::extractDefinitions($command);
        $formatDefinitions = array_values(array_filter(
            $definitions,
            static fn (string $definition): bool => str_starts_with($definition, $prefix),
        ));

        self::assertCount(1, $formatDefinitions);
        self::assertStringContainsString('/{plex.derive{', $formatDefinitions[0]);
        self::assertStringContainsString('{plex.derive{', $formatDefinitions[0]);
        self::assertStringContainsString('any{vf}{null}', $formatDefinitions[0]);
        self::assertStringContainsString('any{vc}{null}', $formatDefinitions[0]);
        self::assertStringContainsString('any{hdr}{SDR}', $formatDefinitions[0]);
        self::assertStringContainsString('any{ac}{null}', $formatDefinitions[0]);
        self::assertStringContainsString('tags.join', $formatDefinitions[0]);
        self::assertStringContainsString('}.tail}', $formatDefinitions[0]);
    }

    /**
     * @param list<string> $command
     */
    private static function assertNotContainsDef(array $command, string $unexpectedDefinition): void
    {
        $definitions = self::extractDefinitions($command);

        self::assertNotContains($unexpectedDefinition, $definitions);
    }

    /**
     * @param list<string> $command
     *
     * @return list<string>
     */
    private static function extractDefinitions(array $command): array
    {
        $definitions = [];

        foreach ($command as $index => $part) {
            if ($part === '--def' && isset($command[$index + 1])) {
                $definitions[] = $command[$index + 1];
            }
        }

        return $definitions;
    }

    public function testRunBuildsSeparateCommandsForMultipleFileAndFolderPaths(): void
    {
        $folderPath = $this->temporaryRoot . '/incoming-folder';
        $filePath = $this->temporaryRoot . '/example.mkv';
        mkdir($folderPath);
        touch($folderPath . '/episode-one.mkv');
        touch($folderPath . '/episode-two.mkv');
        touch($filePath);

        try {
            $logger = new InMemoryLogger();
            $service = $this->createService($logger);

            $service->run(new FilebotJobModel(
                paths: [$folderPath, $filePath],
                label: 'tv',
                action: 'test',
            ));

            self::assertCount(10, $logger->records);

            self::assertSame('Processing path: ' . $folderPath, $logger->records[0]['message']);
            self::assertSame('Prepared FileBot command.', $logger->records[1]['message']);
            self::assertSame($folderPath, $logger->records[1]['context']['path']);

            $folderCommand = $logger->records[1]['context']['command'];

            self::assertCommandContainsOptionValue($folderCommand, '--action', 'test');
            self::assertContainsDef($folderCommand, 'ut_dir=' . $folderPath);
            self::assertContainsDef($folderCommand, 'ut_kind=multi');
            self::assertContainsDef($folderCommand, 'ut_label=tv');
            self::assertContainsDef($folderCommand, 'ut_title=incoming-folder');

            self::assertSame('Processing path: ' . $filePath, $logger->records[5]['message']);
            self::assertSame('Prepared FileBot command.', $logger->records[6]['message']);
            self::assertSame($filePath, $logger->records[6]['context']['path']);

            $fileCommand = $logger->records[6]['context']['command'];

            self::assertCommandContainsOptionValue($fileCommand, '--action', 'test');
            self::assertContainsDef($fileCommand, 'ut_dir=' . $filePath);
            self::assertContainsDef($fileCommand, 'ut_kind=single');
            self::assertContainsDef($fileCommand, 'ut_label=tv');
            self::assertContainsDef($fileCommand, 'ut_title=example.mkv');
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }

        }
    }
}
