<?php

namespace App\Tests\Config;

use App\Config\FilebotConfig;
use PHPUnit\Framework\TestCase;

final class FilebotConfigTest extends TestCase
{
    public function testStoresAllConfiguredValues(): void
    {
        $config = new FilebotConfig(
            storageRoot: '/media/output',
            filebotLang: 'en',
            filebotLogLevel: 'info',
            plexHost: 'http://plex.local:32400',
            plexToken: 'secret-token',
            amcUnsorted: true,
            amcClean: false,
            amcSubtitleLang: 'en',
            amcMovieDb: 'TheMovieDB',
            amcSeriesDb: 'TheTVDB',
            amcAnimeDb: 'AniDB',
            amcMinLength: 300000,
            amcMinFilesize: 100000000,
        );

        self::assertSame('/media/output', $config->storageRoot);
        self::assertSame('en', $config->filebotLang);
        self::assertSame('info', $config->filebotLogLevel);
        self::assertSame('http://plex.local:32400', $config->plexHost);
        self::assertSame('secret-token', $config->plexToken);
        self::assertTrue($config->amcUnsorted);
        self::assertFalse($config->amcClean);
        self::assertSame('en', $config->amcSubtitleLang);
        self::assertSame('TheMovieDB', $config->amcMovieDb);
        self::assertSame('TheTVDB', $config->amcSeriesDb);
        self::assertSame('AniDB', $config->amcAnimeDb);
        self::assertSame(300000, $config->amcMinLength);
        self::assertSame(100000000, $config->amcMinFilesize);
    }
}
