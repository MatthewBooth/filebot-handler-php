<?php

namespace App\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FilebotConfig
{
    public function __construct(
        #[Autowire(env: 'TORRENTS_ROOT')]
        public string $torrentsRoot,

        #[Autowire(env: 'STORAGE_ROOT')]
        public string $storageRoot,

        #[Autowire(env: 'FILEBOT_LANG')]
        public string $filebotLang,

        #[Autowire(env: 'FILEBOT_LOG_LEVEL')]
        public string $filebotLogLevel,

        #[Autowire(env: 'AMC_LOG_DIR')]
        public string $amcLogDir,

        #[Autowire(env: 'AMC_ACTION')]
        public string $amcAction,

        #[Autowire(env: 'bool:AMC_UNSORTED')]
        public bool $amcUnsorted,

        #[Autowire(env: 'bool:AMC_CLEAN')]
        public bool $amcClean,

        #[Autowire(env: 'AMC_SUBTITLE_LANG')]
        public string $amcSubtitleLang,

        #[Autowire(env: 'AMC_MOVIE_DB')]
        public string $amcMovieDb,

        #[Autowire(env: 'AMC_SERIES_DB')]
        public string $amcSeriesDb,

        #[Autowire(env: 'AMC_ANIME_DB')]
        public string $amcAnimeDb,

        #[Autowire(env: 'int:AMC_MIN_LENGTH')]
        public int $amcMinLength,

        #[Autowire(env: 'int:AMC_MIN_FILESIZE')]
        public int $amcMinFilesize,
    ) {
    }
}
