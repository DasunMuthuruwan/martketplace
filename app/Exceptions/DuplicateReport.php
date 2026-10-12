<?php

namespace App\Exceptions;

use RuntimeException;

/** The reporter already has an open report about this target (HTTP 409, routes doc §4.4). */
class DuplicateReport extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('reports.duplicate'));
    }
}
