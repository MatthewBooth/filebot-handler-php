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
        self::assertSame('', $request->mediaId);
        self::assertFalse($request->strict);
        self::assertTrue($request->artwork);
        self::assertFalse($request->overwrite);
        self::assertFalse($request->dryRun);

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
            mediaId: 'show-42',
            strict: true,
            artwork: false,
            overwrite: true,
            dryRun: true,
        );

        self::assertEquals(new FilebotJobModel(
            paths: ['/media/one.mkv', '/media/two.mkv'],
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            mediaId: 'show-42',
            strict: true,
            artwork: false,
            overwrite: true,
            dryRun: true,
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
        )));

        $violations = $validator->validate(new CreateFilebotJobRequestDto(
            paths: [],
            label: 'documentary',
            order: 'invalid',
            conflict: 'invalid',
        ));

        self::assertCount(5, $violations);
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
