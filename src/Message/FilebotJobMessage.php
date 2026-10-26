<?php

namespace App\Message;

use App\Model\FilebotJobModel;

final readonly class FilebotJobMessage
{
    public function __construct(public FilebotJobModel $job)
    {
    }
}
