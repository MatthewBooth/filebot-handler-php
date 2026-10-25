<?php

namespace App\Service;

use App\Config\FilebotConfig;
use App\Model\FilebotJobModel;
use Psr\Log\LoggerInterface;

final readonly class FilebotRunnerService implements FilebotRunnerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private FilebotConfig $config,
    )
    {
    }

    public function run(FilebotJobModel $job): void
    {
        foreach ($job->paths as $path) {
            $this->logger->info('Processing path: ' . $path);
            $command = $this->buildCommand($path, $job);
            $this->logger->info('Prepared FileBot command.', [
                'path' => $path,
                'command' => $command
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function buildCommand(string $path, FilebotJobModel $job): array
    {
        $action = $job->dryRun ? 'test' : 'copy';
        $conflict = $job->overwrite ? 'replace' : $job->conflict;
        $utKind = is_file($path) ? 'single' : 'multi';
        $utTitle = basename($path);

        $command = [
            'filebot',
            '-script',
            'fn:amc',
            '--output',
            $this->config->outputDir,
            '--action',
            $action,
            '--conflict',
            $conflict,
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

        if ($job->strict) {
            $command[] = '--strict';
        }

        $defs = [
            "unsorted=" . $this->config->amcUnsorted,
            "clean=" . $this->config->amcClean,
            "subtitles=" . $this->config->amcSubtitleLang,
            "ut_dir=" . $path,
            "ut_kind=" . $utKind,
            "ut_label=" . $job->label,
            "ut_title=" . $utTitle,
            "movieDB=" . $this->config->amcMovieDb,
            "seriesDB=" . $this->config->amcSeriesDb,
            "animeDB=" . $this->config->amcAnimeDb,
            "minLengthMS=" . $this->config->amcMinLength,
            "minFileSize=" . $this->config->amcMinFilesize,
            "movieFormat=" . $this->config->outputDir . "/Films/{plex.tail}",
            "seriesFormat=" . $this->config->outputDir . "/TV/{plex.tail}",
            "animeFormat=" . $this->config->outputDir . "/TV/{plex.tail}",
        ];

        if ($job->artwork) {
            $defs[] = 'artwork=y';
        }

        foreach ($defs as $def) {
            $command[] = "--def";
            $command[] = $def;
        }

        return $command;
    }
}
