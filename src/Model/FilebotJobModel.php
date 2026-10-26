<?php

namespace App\Model;

final readonly class FilebotJobModel
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths,
        public string $label,
        public string $order = 'Airdate',
        public string $conflict = 'skip',
        public string $action = 'copy',
        public string $mediaId = '',
        public bool $strict = false,
        public bool $artwork = true,
    ) {
    }

    /**
     * @return array{
     *     paths: list<string>,
     *     label: string,
     *     order: string,
     *     conflict: string,
     *     action: string,
     *     mediaId: string,
     *     strict: bool,
     *     artwork: bool
     * }
     */
    public function toLogContext(): array
    {
        return [
            'paths' => $this->paths,
            'label' => $this->label,
            'order' => $this->order,
            'conflict' => $this->conflict,
            'action' => $this->action,
            'mediaId' => $this->mediaId,
            'strict' => $this->strict,
            'artwork' => $this->artwork,
        ];
    }
}
