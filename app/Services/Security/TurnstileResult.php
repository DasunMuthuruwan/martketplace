<?php

namespace App\Services\Security;

enum TurnstileResult
{
    /** Cloudflare confirmed the token. */
    case Passed;

    /** Missing, invalid, expired or reused token. */
    case Failed;

    /** Cloudflare could not be reached; the caller decides (fail closed for sign-up, open for listings). */
    case Unavailable;
}
