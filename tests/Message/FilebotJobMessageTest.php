<?php

namespace App\Tests\Message;

use App\Message\FilebotJobMessage;
use PHPUnit\Framework\TestCase;

final class FilebotJobMessageTest extends TestCase
{
    public function testUsesDefaultsAndReturnsCompleteLogContext(): void
    {
        $job = new FilebotJobMessage(path: '/media/example.mkv', label: 'tv');

        self::assertSame([
            'path' => '/media/example.mkv',
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
        $job = new FilebotJobMessage(
            path: '/media/episode.mkv',
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: '123456',
            strict: true,
            artwork: false,
        );

        self::assertSame([
            'path' => '/media/episode.mkv',
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
