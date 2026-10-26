<?php

namespace App\Tests\Dto;

use App\Dto\CreateFilebotJobRequestDto;
use App\Model\FilebotJobModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class CreateFilebotJobRequestDtoTest extends TestCase
{
    public function testDefaultsAndConvertsToFilebotJob(): void
    {
        $request = new CreateFilebotJobRequestDto(paths: ['/media/example.mkv'], label: 'movie');

        self::assertSame('Airdate', $request->order);
        self::assertSame('skip', $request->conflict);
        self::assertSame('copy', $request->action);
        self::assertSame('', $request->mediaId);
        self::assertFalse($request->strict);
        self::assertTrue($request->artwork);

        self::assertEquals(new FilebotJobModel(
            paths: ['/media/example.mkv'],
            label: 'movie',
        ), $request->toFilebotJob());
    }

    public function testConvertsEveryRequestOptionToFilebotJob(): void
    {
        $request = new CreateFilebotJobRequestDto(
            paths: ['/media/one.mkv', '/media/two.mkv'],
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: 'show-42',
            strict: true,
            artwork: false,
        );

        self::assertEquals(new FilebotJobModel(
            paths: ['/media/one.mkv', '/media/two.mkv'],
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: 'show-42',
            strict: true,
            artwork: false,
        ), $request->toFilebotJob());
    }

    public function testValidatesPathsLabelOrderAndConflict(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertCount(0, $validator->validate(new CreateFilebotJobRequestDto(
            paths: ['/media/example.mkv'],
            label: 'tv',
            order: 'DVD',
            conflict: 'fail',
            action: 'test',
        )));

        $violations = $validator->validate(new CreateFilebotJobRequestDto(
            paths: [],
            label: 'documentary',
            order: 'invalid',
            conflict: 'invalid',
            action: 'invalid',
        ));

        self::assertCount(6, $violations);
    }

    public function testRejectsBlankAndNonStringPaths(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CreateFilebotJobRequestDto(
            paths: ['', 123],
            label: 'movie',
        ));

        self::assertCount(2, $violations);
    }
}
