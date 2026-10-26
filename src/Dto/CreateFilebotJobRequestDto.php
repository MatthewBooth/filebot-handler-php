<?php

namespace App\Dto;

use App\Model\FilebotJobModel;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateFilebotJobRequestDto
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Count(min: 1)]
        #[Assert\All(constraints: [
            new Assert\Sequentially(constraints: [
                new Assert\NotBlank(),
                new Assert\Type('string'),
                new Assert\Regex(pattern: '#^/#', message: 'Path must be absolute.'),
            ]),
        ])]
        public array $paths,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['movie', 'tv', 'anime', 'music'])]
        public string $label,

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['Airdate', 'DVD', 'Absolute'])]
        public string $order = 'Airdate',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['skip', 'replace', 'auto', 'index', 'fail'])]
        public string $conflict = 'skip',

        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['test', 'copy', 'move'])]
        public string $action = 'copy',

        #[Assert\Regex(pattern: '/^[1-9]\d*$/')]
        public string $mediaId = '',

        public bool $strict = false,

        public bool $artwork = true
    ) {
    }

    public function toFilebotJob(): FilebotJobModel
    {
        return new FilebotJobModel(
            paths: $this->paths,
            label: $this->label,
            order: $this->order,
            conflict: $this->conflict,
            action: $this->action,
            mediaId: $this->mediaId,
            strict: $this->strict,
            artwork: $this->artwork,
        );
    }
}
