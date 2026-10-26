<?php

namespace App\Config;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FilebotConfig
{
    public function __construct(
        #[Autowire(env: 'default:filebot_config_torrents_root_default:TORRENTS_ROOT')]
        public string $torrentsRoot,

        #[Autowire(env: 'default:filebot_config_storage_root_default:STORAGE_ROOT')]
        public string $storageRoot,

        #[Autowire(env: 'default:filebot_config_lang_default:FILEBOT_LANG')]
        public string $filebotLang,

        #[Autowire(env: 'default:filebot_config_log_level_default:FILEBOT_LOG_LEVEL')]
        public string $filebotLogLevel,

        #[Autowire(env: 'default:filebot_config_plex_host_default:PLEX_HOST')]
        public string $plexHost,

        #[Autowire(env: 'default:filebot_config_plex_token_default:PLEX_TOKEN')]
        public string $plexToken,

        #[Autowire(env: 'default:filebot_config_amc_action_default:AMC_ACTION')]
        public string $amcAction,

        #[Autowire(env: 'bool:default:filebot_config_amc_unsorted_default:AMC_UNSORTED')]
        public bool $amcUnsorted,

        #[Autowire(env: 'bool:default:filebot_config_amc_clean_default:AMC_CLEAN')]
        public bool $amcClean,

        #[Autowire(env: 'default:filebot_config_amc_subtitle_lang_default:AMC_SUBTITLE_LANG')]
        public string $amcSubtitleLang,

        #[Autowire(env: 'default:filebot_config_amc_movie_db_default:AMC_MOVIE_DB')]
        public string $amcMovieDb,

        #[Autowire(env: 'default:filebot_config_amc_series_db_default:AMC_SERIES_DB')]
        public string $amcSeriesDb,

        #[Autowire(env: 'default:filebot_config_amc_anime_db_default:AMC_ANIME_DB')]
        public string $amcAnimeDb,

        #[Autowire(env: 'int:default:filebot_config_amc_min_length_default:AMC_MIN_LENGTH')]
        public int $amcMinLength,

        #[Autowire(env: 'int:default:filebot_config_amc_min_filesize_default:AMC_MIN_FILESIZE')]
        public int $amcMinFilesize,
    ) {
    }
}
