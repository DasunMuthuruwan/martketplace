<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Static page (ADM-06): About, Terms, Privacy, Safety tips, Prohibited items, FAQ, Contact.
 * The body is Markdown; raw HTML is stripped and unsafe links (javascript:, data:…) are dropped
 * when it is rendered (security doc §2). Links inside should be relative (portability P2).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property bool $is_published
 * @property array{description?: string}|null $meta
 */
class Page extends Model
{
    use HasTranslations;

    /** Pages every site needs; seeded unpublished and empty for the Owner to write. */
    public const DEFAULTS = [
        'about' => 'About us',
        'terms' => 'Terms of use',
        'privacy' => 'Privacy policy',
        'safety' => 'Safety tips',
        'prohibited-items' => 'Prohibited items',
        'faq' => 'Frequently asked questions',
        'contact' => 'Contact us',
    ];

    private const FOOTER_CACHE = 'pages:footer';

    /** @var list<string> */
    public array $translatable = ['title', 'body'];

    /** @var list<string> */
    protected $fillable = ['slug', 'title', 'body', 'is_published', 'meta'];

    /** @var array<string, mixed> */
    protected $attributes = ['is_published' => false];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::FOOTER_CACHE));
        static::deleted(fn () => Cache::forget(self::FOOTER_CACHE));
    }

    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]));
    }

    /**
     * Published pages for the footer: slug => title.
     *
     * @return Collection<string, string>
     */
    public static function footerLinks(): Collection
    {
        try {
            return collect(Cache::rememberForever(self::FOOTER_CACHE, fn () => self::where('is_published', true)->orderBy('id')->get()
                ->mapWithKeys(fn (Page $page) => [$page->slug => $page->title])->all()));
        } catch (\Throwable) {
            return collect(); // before migrations (error pages during install)
        }
    }
}
