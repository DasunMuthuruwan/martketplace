<?php

namespace App\Enums;

/** Per-category override of the site moderation setting. */
enum CategoryModeration: string
{
    case Inherit = 'inherit';
    case Always = 'always';
    case Never = 'never';
}
