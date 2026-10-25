<?php

namespace App\Tests\Service;

use App\Config\FilebotConfig;
use App\Model\FilebotJobModel;
use App\Service\FilebotRunnerService;
use App\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;

final class FilebotRunnerServiceTest extends TestCase
{
    public function testRunLogsDryRunCommand(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            overwrite: false,
            dryRun: true,
        ));

        self::assertCount(2, $logger->records);

        self::assertSame('Processing path: /media/incoming/example.mkv', $logger->records[0]['message']);
        self::assertSame('Prepared FileBot command.', $logger->records[1]['message']);
        self::assertSame('/media/incoming/example.mkv', $logger->records[1]['context']['path']);

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'test');
        self::assertCommandContainsOptionValue($command, '--conflict', 'auto');
        self::assertCommandContainsOptionValue($command, '--output', '/media/output');
        self::assertCommandContainsOptionValue($command, '--lang', 'en');
        self::assertCommandContainsOptionValue($command, '--log', 'info');
        self::assertCommandContainsOptionValue($command, '--order', 'Airdate');

        self::assertContains('filebot', $command);
        self::assertContains('-script', $command);
        self::assertContains('fn:amc', $command);
        self::assertContains('-no-xattr', $command);

        self::assertContainsDef($command, 'ut_dir=/media/incoming/example.mkv');
        self::assertContainsDef($command, 'ut_kind=multi');
        self::assertContainsDef($command, 'ut_label=tv');
        self::assertContainsDef($command, 'ut_title=example.mkv');
        self::assertContainsDef($command, 'artwork=y');
    }

    public function testRunLogsOverwriteCommand(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            conflict: 'skip',
            overwrite: true,
            dryRun: false,
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'copy');
        self::assertCommandContainsOptionValue($command, '--conflict', 'replace');
    }

    public function testRunLogsDefaultCommandWhenNotDryRunAndNotOverwrite(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            conflict: 'skip',
            overwrite: false,
            dryRun: false,
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertCommandContainsOptionValue($command, '--action', 'copy');
        self::assertCommandContainsOptionValue($command, '--conflict', 'skip');
    }

    public function testRunAddsStrictFlagWhenJobIsStrict(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            strict: true,
        ));

        self::assertContains('--strict', $logger->records[1]['context']['command']);
    }

    public function testRunDoesNotAddArtworkDefinitionWhenArtworkIsDisabled(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'tv',
            artwork: false,
        ));

        self::assertNotContainsDef($logger->records[1]['context']['command'], 'artwork=y');
    }

    public function testRunIncludesConfiguredAmcDefinitions(): void
    {
        $logger = new InMemoryLogger();
        $service = new FilebotRunnerService($logger, $this->createConfig());

        $service->run(new FilebotJobModel(
            paths: ['/media/incoming/example.mkv'],
            label: 'movie',
        ));

        $command = $logger->records[1]['context']['command'];

        self::assertContainsDef($command, 'unsorted=1');
        self::assertContainsDef($command, 'clean=');
        self::assertContainsDef($command, 'subtitles=en');
        self::assertContainsDef($command, 'movieDB=TheMovieDB');
        self::assertContainsDef($command, 'seriesDB=TheTVDB');
        self::assertContainsDef($command, 'animeDB=AniDB');
        self::assertContainsDef($command, 'minLengthMS=300000');
        self::assertContainsDef($command, 'minFileSize=100000000');
        self::assertContainsDef($command, 'movieFormat=/media/output/Films/{plex.tail}');
        self::assertContainsDef($command, 'seriesFormat=/media/output/TV/{plex.tail}');
        self::assertContainsDef($command, 'animeFormat=/media/output/TV/{plex.tail}');
    }

    private function createConfig(): FilebotConfig
    {
        return new FilebotConfig(
            torrentsRoot: '/media/incoming',
            outputDir: '/media/output',
            filebotLang: 'en',
            filebotLogLevel: 'info',
            amcLogDir: '/var/log/filebot',
            amcAction: 'copy',
            amcUnsorted: true,
            amcClean: false,
            amcSubtitleLang: 'en',
            amcMovieDb: 'TheMovieDB',
            amcSeriesDb: 'TheTVDB',
            amcAnimeDb: 'AniDB',
            amcMinLength: 300000,
            amcMinFilesize: 100000000,
        );
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
        $temporaryRoot = sys_get_temp_dir() . '/filebot-runner-test-' . bin2hex(random_bytes(8));
        $folderPath = $temporaryRoot . '/incoming-folder';
        $filePath = $temporaryRoot . '/example.mkv';

        mkdir($temporaryRoot);
        mkdir($folderPath);
        touch($filePath);

        try {
            $logger = new InMemoryLogger();
            $service = new FilebotRunnerService($logger, $this->createConfig());

            $service->run(new FilebotJobModel(
                paths: [$folderPath, $filePath],
                label: 'tv',
                dryRun: true,
            ));

            self::assertCount(4, $logger->records);

            self::assertSame('Processing path: ' . $folderPath, $logger->records[0]['message']);
            self::assertSame('Prepared FileBot command.', $logger->records[1]['message']);
            self::assertSame($folderPath, $logger->records[1]['context']['path']);

            $folderCommand = $logger->records[1]['context']['command'];

            self::assertCommandContainsOptionValue($folderCommand, '--action', 'test');
            self::assertContainsDef($folderCommand, 'ut_dir=' . $folderPath);
            self::assertContainsDef($folderCommand, 'ut_kind=multi');
            self::assertContainsDef($folderCommand, 'ut_label=tv');
            self::assertContainsDef($folderCommand, 'ut_title=incoming-folder');

            self::assertSame('Processing path: ' . $filePath, $logger->records[2]['message']);
            self::assertSame('Prepared FileBot command.', $logger->records[3]['message']);
            self::assertSame($filePath, $logger->records[3]['context']['path']);

            $fileCommand = $logger->records[3]['context']['command'];

            self::assertCommandContainsOptionValue($fileCommand, '--action', 'test');
            self::assertContainsDef($fileCommand, 'ut_dir=' . $filePath);
            self::assertContainsDef($fileCommand, 'ut_kind=single');
            self::assertContainsDef($fileCommand, 'ut_label=tv');
            self::assertContainsDef($fileCommand, 'ut_title=example.mkv');
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }

            if (is_dir($folderPath)) {
                rmdir($folderPath);
            }

            if (is_dir($temporaryRoot)) {
                rmdir($temporaryRoot);
            }
        }
    }
}
