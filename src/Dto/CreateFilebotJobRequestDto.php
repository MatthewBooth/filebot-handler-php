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
            new Assert\NotBlank(),
            new Assert\Type('string')
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
        public string $conflict = 'auto',

        public string $mediaId = '',

        public bool $strict = false,

        public bool $artwork = true,

        public bool $overwrite = false,

        public bool $dryRun = false
    ) {
    }

    public function toFilebotJob(): FilebotJobModel
    {
        return new FilebotJobModel(paths: $this->paths,
            label: $this->label,
            order: $this->order,
            conflict: $this->conflict,
            mediaId: $this->mediaId,
            strict: $this->strict,
            artwork: $this->artwork,
            overwrite: $this->overwrite,
            dryRun: $this->dryRun
        );
    }
}
