<?php

namespace App\Service;

use App\Model\FilebotJobModel;

interface FilebotRunnerInterface
{
    /**
     * @param FilebotJobModel $job
     * @return void
     */
    public function run(FilebotJobModel $job): void;
}
