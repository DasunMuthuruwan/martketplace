<?php

namespace Tests\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Finds values that must never be hardcoded (portability rule P1 and ADR-019):
 * the production domain, country/currency/phone/unit literals, and absolute URLs
 * to hosts that are not on the allow-list.
 *
 * Seeders, data files, language files and tests may contain such values: they are
 * where data lives. Everything else must read them from config or the database.
 */
class HardcodedValueScanner
{
    /** @var array<string, string> label => regex */
    public const FORBIDDEN_PATTERNS = [
        'domain' => '/dev-?talk/i',
        'currency literal' => '/\bLKR\b/',
        'country literal' => '/\bSri\s+Lanka\b/i',
        'phone prefix literal' => '/\+94\d*/',
        'land unit literal' => '/\bperch(es)?\b/i',
    ];

    /** Hosts that may appear in absolute URLs (XML namespaces, docs in comments, placeholders, approved services). */
    public const ALLOWED_URL_HOSTS = [
        'localhost',
        '127.0.0.1',
        'example.com',
        'example.org',
        'www.w3.org',
        'schema.org',
        'www.sitemaps.org',
        'developer.mozilla.org',
        'sqs.us-east-1.amazonaws.com',
        'challenges.cloudflare.com',
        'wa.me',
        'www.facebook.com', // share link (G3, ADR-027), configured in marketplace.share
        'download.geonames.org',
    ];

    /** Directories scanned, relative to the project root. */
    public const SCANNED_DIRECTORIES = ['app', 'bootstrap', 'config', 'routes', 'resources', 'database'];

    /** Paths (relative, forward slashes) that are allowed to contain data values. */
    public const EXCLUDED_PATHS = ['database/seeders', 'database/data', 'bootstrap/cache'];

    public const SCANNED_EXTENSIONS = ['php', 'js', 'ts', 'css', 'json', 'vue'];

    /**
     * @return array<int, string> one line per finding: "path:line: label: match"
     */
    public static function scanProject(string $root): array
    {
        $findings = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $path = $root.DIRECTORY_SEPARATOR.$directory;
            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

                if (! in_array(strtolower($file->getExtension()), self::SCANNED_EXTENSIONS, true) || self::isExcluded($relative)) {
                    continue;
                }

                foreach (self::scanText((string) file_get_contents($file->getPathname())) as [$line, $label, $match]) {
                    $findings[] = "{$relative}:{$line}: {$label}: {$match}";
                }
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array{0: int, 1: string, 2: string}> [line, label, match]
     */
    public static function scanText(string $text): array
    {
        $findings = [];

        foreach (preg_split('/\R/', $text) as $index => $line) {
            foreach (self::FORBIDDEN_PATTERNS as $label => $pattern) {
                if (preg_match($pattern, $line, $match)) {
                    $findings[] = [$index + 1, $label, $match[0]];
                }
            }

            if (preg_match_all('#https?://([a-z0-9.\-]+)#i', $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $url) {
                    if (! in_array(strtolower($url[1]), self::ALLOWED_URL_HOSTS, true)) {
                        $findings[] = [$index + 1, 'absolute URL to non-allow-listed host', $url[0]];
                    }
                }
            }
        }

        return $findings;
    }

    private static function isExcluded(string $relative): bool
    {
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (str_starts_with($relative, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }
}
