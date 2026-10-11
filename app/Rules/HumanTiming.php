<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;

/**
 * Time trap: the form carries an encrypted "rendered at" timestamp. Submissions faster than a
 * human can type, replayed old forms and tampered tokens are rejected. Pair with a honeypot field
 * validated as `prohibited`. See resources/views/partials/bot-protection.blade.php.
 */
class HumanTiming implements ValidationRule
{
    public const FIELD = 'form_token';

    public const MIN_SECONDS = 2;

    public const MAX_SECONDS = 86400;

    public static function token(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $renderedAt = is_string($value) ? (int) Crypt::decryptString($value) : 0;
        } catch (DecryptException) {
            $renderedAt = 0;
        }

        $age = now()->timestamp - $renderedAt;

        if ($renderedAt === 0 || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            $fail(__('security.form_expired'));
        }
    }
}
