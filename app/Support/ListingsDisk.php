<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * The `listings` disk plus the script guard for the `uploads` mode (security doc §4): when photos
 * live under public/, an .htaccess denies script files and removes their handlers, so nothing
 * uploaded there can ever execute. Written by the app because public/uploads is not in git.
 */
final class ListingsDisk
{
    public const NAME = 'listings';

    public const GUARD_FILE = '.htaccess';

    /** LiteSpeed/lsphp ignores `php_flag engine off`, so deny by extension and drop handlers. */
    public const GUARD = <<<'HTACCESS'
        # Written by the marketplace (App\Support\ListingsDisk). Uploaded files must never execute.
        <FilesMatch "\.(php\d?|phtml|phar|pl|py|cgi|sh|shtml|asp|aspx|jsp)$">
            Require all denied
        </FilesMatch>
        RemoveHandler .php .php5 .php7 .php8 .phtml .phar .pl .py .cgi .sh .shtml
        RemoveType .php .php5 .php7 .php8 .phtml .phar
        Options -ExecCGI -Indexes
        HTACCESS;

    public static function disk(): Filesystem
    {
        return Storage::disk(self::NAME);
    }

    /** Writes the guard when the disk is a local folder inside public/ and the guard is missing or changed. */
    public static function ensureGuard(): void
    {
        $config = config('filesystems.disks.'.self::NAME);
        if (($config['driver'] ?? null) !== 'local' || ! self::insidePublic((string) ($config['root'] ?? ''))) {
            return;
        }

        $disk = self::disk();
        if (! $disk->exists(self::GUARD_FILE) || $disk->get(self::GUARD_FILE) !== self::GUARD) {
            $disk->put(self::GUARD_FILE, self::GUARD);
        }
    }

    private static function insidePublic(string $root): bool
    {
        $public = rtrim(str_replace('\\', '/', public_path()), '/').'/';

        return str_starts_with(str_replace('\\', '/', $root).'/', $public);
    }
}
