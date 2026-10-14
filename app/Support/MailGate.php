<?php

namespace App\Support;

/**
 * Production email safety switch (ADR-009, CD-09). Until MAIL_SENDING_ENABLED=true is set (after
 * SMTP, SPF/DKIM/DMARC and test deliveries are verified and the Owner approves), every message
 * goes to the `log` mailer instead of a real transport. `log` and `array` (tests) are left alone.
 */
final class MailGate
{
    private const SAFE_MAILERS = ['log', 'array'];

    public static function apply(): void
    {
        if (self::sendingEnabled()) {
            return;
        }

        if (! in_array(config('mail.default'), self::SAFE_MAILERS, true)) {
            config(['mail.default' => 'log']);
        }
    }

    public static function sendingEnabled(): bool
    {
        return (bool) config('marketplace.mail.sending_enabled');
    }
}
