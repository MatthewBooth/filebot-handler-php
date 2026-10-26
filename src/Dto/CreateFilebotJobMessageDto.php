<?php

namespace App\Dto;

use App\Message\FilebotJobMessage;
use App\Validator\ExistingPath;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateFilebotJobMessageDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\/.+$/', message: 'Path must be absolute.')]
        #[ExistingPath]
        public string $path,

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

    public function toFilebotJobMessage(): FilebotJobMessage
    {
        return new FilebotJobMessage(
            path: $this->path,
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
