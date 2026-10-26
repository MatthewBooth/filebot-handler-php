<?php

namespace App\Service;

use App\Model\FilebotJobModel;

interface FilebotRunnerInterface
{
    public function run(FilebotJobModel $job): void;
}
