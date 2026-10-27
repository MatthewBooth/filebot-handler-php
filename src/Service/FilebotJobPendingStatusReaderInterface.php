<?php

namespace App\Service;

interface FilebotJobPendingStatusReaderInterface
{
    /**
     * @param list<string> $messageIds
     *
     * @return array<string, array{worker: string, idleForMs: int, deliveryCount: int}>
     */
    public function findPendingStatuses(array $messageIds): array;
}
