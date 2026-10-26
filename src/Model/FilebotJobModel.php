<?php

namespace App\Model;

final readonly class FilebotJobModel {
    /**
     * @param list<string> $paths
     */
    public function __construct(
        public array $paths,
        public string $label,
        public string $order = 'Airdate',
        public string $conflict = 'skip',
        public string $mediaId = '',
        public bool $strict = false,
        public bool $artwork = true,
        public bool $overwrite = false,
        public bool $dryRun = false
    ) {
    }

    /**
     * @return array{
     *     paths: list<string>,
     *     label: string,
     *     order: string,
     *     conflict: string,
     *     mediaId: string,
     *     strict: bool,
     *     artwork: bool,
     *     overwrite: bool,
     *     dryRun: bool
     * }
     */
    public function toLogContext(): array
    {
        return [
            'paths' => $this->paths,
            'label' => $this->label,
            'order' => $this->order,
            'conflict' => $this->conflict,
            'mediaId' => $this->mediaId,
            'strict' => $this->strict,
            'artwork' => $this->artwork,
            'overwrite' => $this->overwrite,
            'dryRun' => $this->dryRun,
        ];
    }
}
