<?php

namespace App\Service;

use App\Message\FilebotJobMessage;
use App\Model\FilebotRunResult;

interface FilebotRunnerInterface
{
    public function run(FilebotJobMessage $job): FilebotRunResult;
}
