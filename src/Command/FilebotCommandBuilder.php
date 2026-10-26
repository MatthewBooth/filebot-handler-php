<?php

namespace App\Command;

use App\Config\FilebotConfig;
use App\Exception\FilebotJobValidationException;
use App\Message\FilebotJobMessage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Response;

final readonly class FilebotCommandBuilder
{
    private const string MEDIA_FILE_PATTERN = '/\.(?:3gp|avi|flv|m2ts|m4v|mkv|mov|mp4|mpeg|mpg|mts|ogv|ts|webm|wmv)$/i';

    private const string FORMAT = <<<'GROOVY'
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

    public function __construct(
        private FilebotConfig $config,
        private Finder $finder,
    ) {
    }

    private function buildUtKind(string $path): string
    {
        if (is_file($path)) {
            return 'single';
        }

        if (is_dir($path)) {
            $mediaFileCount = (clone $this->finder)
                ->files()
                ->in($path)
                ->name(self::MEDIA_FILE_PATTERN)
                ->count();

            if ($mediaFileCount === 1) {
                return 'single';
            }

            if ($mediaFileCount > 1) {
                return 'multi';
            }
        }

        throw new FilebotJobValidationException(
            'Path does not contain any media files: ' . $path,
            Response::HTTP_BAD_REQUEST,
        );
    }

    /**
     * @return list<string>
     */
    public function build(string $path, FilebotJobMessage $job): array
    {
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
            $job->order,
        ];

        if (!$job->strict) {
            $command[] = '-non-strict';
        }

        if ($job->mediaId !== '') {
            $command[] = '--filter';
            $command[] = 'id in [' . $job->mediaId . ']';
        }

        $format = trim(self::FORMAT);
        $formatDef = static fn (string $root): string => $root . $format;

        $utKind = $this->buildUtKind($path);

        $definitions = [
            'unsorted=' . ($this->config->amcUnsorted ? 'y' : 'n'),
            'clean=' . ($this->config->amcClean ? 'y' : 'n'),
            'artwork=' . ($job->artwork ? 'y' : 'n'),
            'subtitles=' . $this->config->amcSubtitleLang,
            'ut_dir=' . $path,
            'ut_kind=' . $utKind,
            'ut_label=' . $job->label,
            'ut_title=' . basename($path),
            'movieDB=' . $this->config->amcMovieDb,
            'seriesDB=' . $this->config->amcSeriesDb,
            'animeDB=' . $this->config->amcAnimeDb,
            'minLengthMS=' . $this->config->amcMinLength,
            'minFileSize=' . $this->config->amcMinFilesize,
            'movieFormat=' . $formatDef($this->config->storageRoot . '/Films/'),
            'seriesFormat=' . $formatDef($this->config->storageRoot . '/TV/'),
            'animeFormat=' . $formatDef($this->config->storageRoot . '/TV/'),
        ];

        if ($this->config->plexHost && $this->config->plexToken) {
            $definitions[] = 'plex=' . $this->config->plexHost . ':' . $this->config->plexToken;
        }

        foreach ($definitions as $definition) {
            $command[] = '--def';
            $command[] = $definition;
        }

        return $command;
    }
}
