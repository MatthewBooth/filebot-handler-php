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
            'action' => 'copy',
            'mediaId' => '',
            'strict' => false,
            'artwork' => true,
        ], $job->toLogContext());
    }

    public function testLogContextContainsAllExplicitValues(): void
    {
        $job = new FilebotJobModel(
            paths: ['/media/one.mkv', '/media/two.mkv'],
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: '123456',
            strict: true,
            artwork: false,
        );

        self::assertSame([
            'paths' => ['/media/one.mkv', '/media/two.mkv'],
            'label' => 'anime',
            'order' => 'Absolute',
            'conflict' => 'replace',
            'action' => 'move',
            'mediaId' => '123456',
            'strict' => true,
            'artwork' => false,
        ], $job->toLogContext());
    }
}
