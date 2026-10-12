<?php

namespace App\Exceptions\Listings;

use RuntimeException;

/** A guest over the hourly reveal threshold must complete Turnstile (HTTP 428, routes doc §4.1). */
class ContactChallengeRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('challenge_required');
    }
}
