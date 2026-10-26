<?php

namespace App\Service;

use App\Message\FilebotJobMessage;

interface FilebotRunnerInterface
{
    public function run(FilebotJobMessage $job): void;
}
