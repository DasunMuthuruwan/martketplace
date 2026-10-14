<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Inline Lucide icons (design system §7) from resources/icons, copied there by `npm run icons`.
 * Icons inherit the text colour, use a 1.75 px stroke and are hidden from assistive technology:
 * the surrounding control carries the accessible name.
 */
final class Icons
{
    public const SIZES = [16, 20, 24];

    /** @var array<string, string> */
    private static array $cache = [];

    public static function exists(string $name): bool
    {
        return preg_match('/^[a-z0-9-]+$/', $name) === 1 && is_file(self::path($name));
    }

    public static function svg(string $name, int $size = 20, string $class = ''): string
    {
        if (! self::exists($name)) {
            throw new InvalidArgumentException("Unknown icon [{$name}]. Add it to scripts/build-icons.mjs and run npm run icons.");
        }

        $markup = self::$cache[$name] ??= self::load($name);
        $classes = trim('icon '.$class);

        return str_replace(
            ['__SIZE__', '__CLASS__'],
            [(string) $size, htmlspecialchars($classes, ENT_QUOTES)],
            $markup,
        );
    }

    private static function load(string $name): string
    {
        $svg = (string) file_get_contents(self::path($name));
        $svg = (string) preg_replace('/<!--.*?-->/s', '', $svg);
        $svg = (string) preg_replace('/\s+/', ' ', trim($svg));
        $svg = (string) preg_replace('/ class="[^"]*"/', ' class="__CLASS__"', $svg, 1);
        $svg = (string) preg_replace('/ width="24"/', ' width="__SIZE__"', $svg, 1);
        $svg = (string) preg_replace('/ height="24"/', ' height="__SIZE__"', $svg, 1);
        $svg = str_replace('stroke-width="2"', 'stroke-width="1.75"', $svg);
        $svg = str_replace(' >', '>', $svg);

        return str_replace('<svg ', '<svg aria-hidden="true" focusable="false" ', $svg);
    }

    private static function path(string $name): string
    {
        return resource_path("icons/{$name}.svg");
    }
}
