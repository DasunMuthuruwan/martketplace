<?php

namespace App\Console\Commands\Ops;

use Illuminate\Console\Command;

/**
 * Refuses an unsafe production configuration (S-02): run on the host after writing `.env` and
 * before going live (deployment checklist). A FAIL exits non-zero, so a deploy script can stop on
 * it; a WARN is reported only. Secret values are never printed, only whether they are set.
 */
class CheckProduction extends Command
{
    protected $signature = 'marketplace:check-production';

    protected $description = 'Check that the configuration is safe for production (debug off, HTTPS, secure cookies, real mail)';

    /** @var list<array{0: string, 1: string, 2: string}> level, check, detail */
    private array $results = [];

    public function handle(): int
    {
        $url = (string) config('app.url');
        $host = (string) parse_url($url, PHP_URL_HOST);

        $this->check(config('app.env') === 'production', 'APP_ENV is production', 'is "'.config('app.env').'"');
        $this->check(config('app.debug') === false, 'APP_DEBUG is off', 'debug pages would show code, queries and settings to visitors');
        $this->check(filled(config('app.key')), 'APP_KEY is set', 'run php artisan key:generate once, then keep the key');
        $this->check(str_starts_with($url, 'https://'), 'APP_URL uses https', 'is "'.$url.'"');
        $this->check($host !== '' && ! in_array($host, ['localhost', '127.0.0.1'], true) && ! str_ends_with($host, '.test') && ! str_ends_with($host, '.local'), 'APP_URL is a public host', 'is "'.$host.'"');
        $this->check(config('session.secure') === true, 'Session cookie is Secure', 'set SESSION_SECURE_COOKIE=true or remove it');
        $this->check(config('session.http_only') === true, 'Session cookie is HttpOnly', 'SESSION_HTTP_ONLY must not be false');
        $this->check(in_array(config('session.same_site'), ['lax', 'strict'], true), 'Session cookie is SameSite lax or strict', 'is "'.config('session.same_site').'"');
        $this->check(config('session.domain') === null, 'Session cookie is host-only', 'SESSION_DOMAIN would share the cookie with other sites on the domain');
        $this->check(config('session.driver') !== 'array', 'Sessions are stored', 'the array driver forgets every session');
        $this->check(config('cache.default') !== 'array', 'Cache is stored', 'the array cache breaks rate limits between requests');
        $this->check(! in_array(config('mail.default'), ['log', 'array'], true), 'Mail is really sent', 'MAIL_MAILER is "'.config('mail.default').'"');
        $this->check(config('marketplace.csp.report_only') === false, 'Content Security Policy is enforced', 'CSP_REPORT_ONLY is on');

        $this->advise(config('session.driver') === 'database', 'Sessions use the database', 'needed to sign users out everywhere (password reset, bans)');
        $this->advise(! in_array('debug', $this->logLevels(), true), 'LOG_LEVEL is not debug', 'debug logs grow fast and hold more detail than needed');
        $this->advise(filled(config('marketplace.turnstile.site_key')) && filled(config('marketplace.turnstile.secret_key')), 'Turnstile keys are set', 'bot protection falls back to the honeypot and time trap');
        $this->advise(config('queue.default') !== 'sync', 'Queue is not sync', 'emails would be sent during the request');
        $this->advise((bool) config('marketplace.mail.sending_enabled'), 'MAIL_SENDING_ENABLED is on', 'queued emails wait until it is on');
        $this->advise(! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Mail from address is real', 'MAIL_FROM_ADDRESS is a placeholder');
        $this->advise(! in_array('*', (array) config('marketplace.trusted_proxies'), true), 'TRUSTED_PROXIES is not *', 'any client could fake its IP address (rate limits, reveal limits)');

        $this->table(['', 'Check', 'Detail'], $this->results);
        $failed = count(array_filter($this->results, fn (array $r) => $r[0] === 'FAIL'));
        if ($failed > 0) {
            $this->error("{$failed} check(s) failed: this configuration is not safe for production.");

            return self::FAILURE;
        }
        $this->info('Production configuration checks passed.');

        return self::SUCCESS;
    }

    private function check(bool $ok, string $what, string $why): void
    {
        $this->results[] = [$ok ? 'OK' : 'FAIL', $what, $ok ? '' : $why];
    }

    private function advise(bool $ok, string $what, string $why): void
    {
        $this->results[] = [$ok ? 'OK' : 'WARN', $what, $ok ? '' : $why];
    }

    /** @return list<string> the level of the default log channel, or of each channel in its stack */
    private function logLevels(): array
    {
        $default = (string) config('logging.default');
        $channels = config("logging.channels.{$default}.driver") === 'stack'
            ? (array) config("logging.channels.{$default}.channels", [])
            : [$default];

        return array_values(array_map(fn ($c) => (string) config('logging.channels.'.trim((string) $c).'.level', 'debug'), $channels));
    }
}
