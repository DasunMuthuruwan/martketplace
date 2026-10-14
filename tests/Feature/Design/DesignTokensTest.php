<?php

namespace Tests\Feature\Design;

use App\Support\Icons;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Vite;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * F1.1: the CSS tokens match the approved design system (docs/design/01-design-system.md §3) and
 * keep the verified contrast (§3.3); icons and the no-flash theme script work.
 */
class DesignTokensTest extends TestCase
{
    private const LIGHT = [
        'canvas' => '#f7f7f7', 'surface' => '#ffffff', 'surface-muted' => '#f0f0f0', 'surface-hover' => '#f0f0f0',
        'line' => '#e5e5e5', 'control' => '#858585', 'fg' => '#0a0a0a', 'fg-muted' => '#4d4d4d', 'fg-subtle' => '#666666',
        'primary' => '#0a0a0a', 'primary-fg' => '#ffffff', 'primary-hover' => '#262626',
        'success' => '#137333', 'danger' => '#c5221f', 'warning' => '#8a5300', 'info' => '#1a56db',
    ];

    private const DARK = [
        'canvas' => '#0a0a0a', 'surface' => '#141414', 'surface-muted' => '#1c1c1c', 'surface-hover' => '#1f1f1f',
        'line' => '#2a2a2a', 'control' => '#6e6e6e', 'fg' => '#f5f5f5', 'fg-muted' => '#b3b3b3', 'fg-subtle' => '#949494',
        'primary' => '#f5f5f5', 'primary-fg' => '#0a0a0a', 'primary-hover' => '#d9d9d9',
        'success' => '#4cc38a', 'danger' => '#ff7a70', 'warning' => '#f5b83d', 'info' => '#7ea6ff',
    ];

    /** @return array<string, string> */
    private function tokens(string $selector): array
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertSame(1, preg_match('/^'.preg_quote($selector, '/').'\s*\{(.*?)^\}/ms', $css, $block), "Missing {$selector} block");
        preg_match_all('/--([a-z-]+):\s*(#[0-9a-f]{6})\s*;/i', $block[1], $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m) => [$m[1] => strtolower($m[2])])->all();
    }

    public function test_light_and_dark_tokens_match_the_design_system(): void
    {
        $light = $this->tokens(':root');
        $dark = $this->tokens('.dark');

        foreach (self::LIGHT as $name => $value) {
            $this->assertSame($value, $light[$name] ?? null, "light --{$name}");
        }
        foreach (self::DARK as $name => $value) {
            $this->assertSame($value, $dark[$name] ?? null, "dark --{$name}");
        }
    }

    public function test_verified_contrast_pairs_still_hold(): void
    {
        foreach (['light' => $this->tokens(':root'), 'dark' => $this->tokens('.dark')] as $theme => $t) {
            foreach (['canvas', 'surface', 'surface-muted'] as $bg) {
                foreach (['fg', 'fg-muted', 'fg-subtle', 'success', 'danger', 'warning', 'info'] as $fg) {
                    $this->assertGreaterThanOrEqual(4.5, $this->contrast($t[$fg], $t[$bg]), "{$theme}: {$fg} on {$bg}");
                }
                $this->assertGreaterThanOrEqual(3.0, $this->contrast($t['control'], $t[$bg]), "{$theme}: control on {$bg}");
            }
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($t['primary-fg'], $t['primary']), "{$theme}: primary-fg on primary");
        }
    }

    public function test_fonts_are_bundled_not_loaded_from_a_cdn(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $vite = (string) file_get_contents(base_path('vite.config.js'));

        $this->assertStringContainsString("@import '@fontsource-variable/geist/wght.css'", $css);
        $this->assertStringContainsString("'Geist Variable'", $css);
        $this->assertStringContainsString('system-ui', $css); // fallback stack
        $this->assertStringNotContainsString('bunny', $vite);
        $this->assertStringNotContainsString('fonts.googleapis', $css.$vite);
        $this->assertFileExists(public_path('licenses/geist-OFL.txt'));
    }

    public function test_icons_render_inline_and_hidden_from_assistive_technology(): void
    {
        $svg = Icons::svg('heart', 16, 'text-danger');

        $this->assertStringStartsWith('<svg aria-hidden="true" focusable="false"', $svg);
        $this->assertStringContainsString('width="16" height="16"', $svg);
        $this->assertStringContainsString('class="icon text-danger"', $svg);
        $this->assertStringContainsString('stroke-width="1.75"', $svg);
        $this->assertStringNotContainsString('<!--', $svg);

        $this->assertSame($svg, trim(Blade::render('<x-ui.icon name="heart" size="16" class="text-danger" />')));
    }

    public function test_every_registered_icon_is_copied_and_unknown_names_fail(): void
    {
        $script = (string) file_get_contents(base_path('scripts/build-icons.mjs'));
        preg_match('/ICONS = \[(.*?)\];/s', $script, $list);
        preg_match_all("/'([a-z0-9-]+)'/", $list[1], $names);

        $this->assertNotEmpty($names[1]);
        foreach ($names[1] as $name) {
            $this->assertTrue(Icons::exists($name), "resources/icons/{$name}.svg is missing: run npm run icons");
        }
        $this->assertFalse(Icons::exists('../secrets'));

        $this->expectException(InvalidArgumentException::class);
        Icons::svg('not-an-icon');
    }

    public function test_theme_script_runs_before_paint_with_the_csp_nonce(): void
    {
        Vite::useCspNonce('test-nonce');

        $html = view('partials.theme-script')->render();

        $this->assertStringContainsString('<script nonce="test-nonce">', $html);
        $this->assertStringContainsString("localStorage.getItem('theme')", $html);
        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
    }

    private function contrast(string $a, string $b): float
    {
        [$la, $lb] = [$this->luminance($a), $this->luminance($b)];

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
