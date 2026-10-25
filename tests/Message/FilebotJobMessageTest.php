<?php

namespace App\Tests\Message;

use App\Message\FilebotJobMessage;
use App\Model\FilebotJobModel;
use PHPUnit\Framework\TestCase;

final class FilebotJobMessageTest extends TestCase
{
    public function testCarriesTheJob(): void
    {
        $job = new FilebotJobModel(paths: ['/media/example.mkv'], label: 'movie');

        self::assertSame($job, (new FilebotJobMessage($job))->job);
    }
}
