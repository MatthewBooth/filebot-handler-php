<?php

namespace App\Tests\Dto;

use App\Dto\CreateFilebotJobMessageDto;
use App\Message\FilebotJobMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class CreateFilebotJobMessageDtoTest extends TestCase
{
    private string $existingPath;

    protected function setUp(): void
    {
        $this->existingPath = tempnam(sys_get_temp_dir(), 'filebot-dto-');
    }

    protected function tearDown(): void
    {
        unlink($this->existingPath);
    }

    public function testDefaultsAndConvertsToFilebotJobMessage(): void
    {
        $request = new CreateFilebotJobMessageDto(path: '/media/example.mkv', label: 'movie');

        self::assertSame('Airdate', $request->order);
        self::assertSame('skip', $request->conflict);
        self::assertSame('copy', $request->action);
        self::assertSame('', $request->mediaId);
        self::assertFalse($request->strict);
        self::assertTrue($request->artwork);

        self::assertEquals(new FilebotJobMessage(
            path: '/media/example.mkv',
            label: 'movie',
        ), $request->toFilebotJobMessage());
    }

    public function testConvertsEveryRequestOptionToFilebotJob(): void
    {
        $request = new CreateFilebotJobMessageDto(
            path: '/media/one.mkv',
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: '123456',
            strict: true,
            artwork: false,
        );

        self::assertEquals(new FilebotJobMessage(
            path: '/media/one.mkv',
            label: 'anime',
            order: 'Absolute',
            conflict: 'replace',
            action: 'move',
            mediaId: '123456',
            strict: true,
            artwork: false,
        ), $request->toFilebotJobMessage());
    }

    public function testValidatesPathLabelOrderAndConflict(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertCount(0, $validator->validate(new CreateFilebotJobMessageDto(
            path: $this->existingPath,
            label: 'tv',
            order: 'DVD',
            conflict: 'fail',
            action: 'test',
        )));

        $violations = $validator->validate(new CreateFilebotJobMessageDto(
            path: '',
            label: 'documentary',
            order: 'invalid',
            conflict: 'invalid',
            action: 'invalid',
        ));

        self::assertCount(5, $violations);
    }

    public function testRejectsBlankPath(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CreateFilebotJobMessageDto(
            path: '',
            label: 'movie',
        ));

        self::assertCount(1, $violations);
    }

    public function testRejectsRelativePaths(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CreateFilebotJobMessageDto(
            path: 'downloads/example.mkv',
            label: 'movie',
        ));

        self::assertCount(2, $violations);
        self::assertSame('path', $violations[0]->getPropertyPath());
    }

    public function testRejectsNonNumericMediaId(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        $violations = $validator->validate(new CreateFilebotJobMessageDto(
            path: $this->existingPath,
            label: 'tv',
            mediaId: 'show-42',
        ));

        self::assertCount(1, $violations);
        self::assertSame('mediaId', $violations[0]->getPropertyPath());
    }
}
