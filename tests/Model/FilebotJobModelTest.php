<?php

namespace App\Tests\Model;

use App\Model\FilebotJobModel;
use PHPUnit\Framework\TestCase;

final class FilebotJobModelTest extends TestCase
{
    public function testUsesDefaultsAndReturnsCompleteLogContext(): void
    {
        $job = new FilebotJobModel(paths: ['/media/example.mkv'], label: 'tv');

        self::assertSame([
            'paths' => ['/media/example.mkv'],
            'label' => 'tv',
            'order' => 'Airdate',
            'conflict' => 'skip',
            'mediaId' => '',
            'strict' => false,
            'artwork' => true,
            'overwrite' => false,
            'dryRun' => false,
        ], $job->toLogContext());
    }

    public function testLogContextContainsAllExplicitValues(): void
    {
        $job = new FilebotJobModel(
            paths: ['/media/one.mkv', '/media/two.mkv'],
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            mediaId: 'show-42',
            strict: true,
            artwork: false,
            overwrite: true,
            dryRun: true,
        );

        self::assertSame([
            'paths' => ['/media/one.mkv', '/media/two.mkv'],
            'label' => 'anime',
            'order' => 'Absolute',
            'conflict' => 'replace',
            'mediaId' => 'show-42',
            'strict' => true,
            'artwork' => false,
            'overwrite' => true,
            'dryRun' => true,
        ], $job->toLogContext());
    }
}
