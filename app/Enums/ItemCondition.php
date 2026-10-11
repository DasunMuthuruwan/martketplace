<?php

namespace App\Enums;

/** Core listing column `condition` (category tree doc §2). Categories turn it on or off. */
enum ItemCondition: string
{
    case New = 'new';
    case Used = 'used';
    case Reconditioned = 'reconditioned';
}
