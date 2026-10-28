<?php

namespace App\Message;

final readonly class FilebotJobMessage
{
    public string $jobId;

    public function __construct(
        public string $path,
        public string $label,
        public string $order = 'Airdate',
        public string $conflict = 'skip',
        public string $action = 'copy',
        public string $mediaId = '',
        public bool $strict = false,
        public bool $artwork = true,
        ?string $jobId = null,
    ) {
        $this->jobId = $jobId ?? bin2hex(random_bytes(16));
    }

    /**
     * @return array{
     *     path: string,
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
            'path' => $this->path,
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
