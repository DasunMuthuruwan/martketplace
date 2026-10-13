<?php

namespace App\Services\Seo;

use Illuminate\Support\Str;

/**
 * Per-page SEO data (SEO-02, architecture §8): title, description, canonical, robots, Open Graph
 * and Twitter card, plus optional JSON-LD blocks. Rendered by the layout. URLs are always built
 * from APP_URL by the callers (route()/url()), never from the request host (P3).
 */
final class PageSeo
{
    public const DESCRIPTION_MAX = 160;

    /** @var list<array<string, mixed>> */
    private array $jsonLd = [];

    private function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?string $canonical,
        public readonly string $robots,
        public readonly ?string $image = null,
        public readonly string $ogType = 'website',
    ) {}

    /**
     * @param  bool  $indexable  the page may be indexed (landing page without extra filters)
     */
    public static function make(string $title, ?string $description, ?string $canonical, bool $indexable = true, ?string $image = null, string $ogType = 'website'): self
    {
        return new self($title, self::clean($description), $canonical, self::robots($indexable), $image, $ogType);
    }

    /** Indexing is switched on per environment; staging and the old domain stay `noindex` (P10). */
    public static function robots(bool $indexable): string
    {
        if (! config('marketplace.seo.indexing')) {
            return 'noindex,nofollow';
        }

        return $indexable ? 'index,follow' : 'noindex,follow';
    }

    /** @param  array<string, mixed>  $data  a schema.org object */
    public function withJsonLd(array $data): self
    {
        $clone = clone $this;
        $clone->jsonLd[] = ['@context' => 'https://schema.org'] + $data;

        return $clone;
    }

    /** @return list<string> encoded JSON-LD blocks, safe inside a script element */
    public function jsonLdBlocks(): array
    {
        return array_map(
            fn (array $data) => (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            $this->jsonLd,
        );
    }

    /** One line, no markup, cut at a word boundary. */
    private static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        return $text === '' ? null : Str::limit($text, self::DESCRIPTION_MAX - 1, '…', preserveWords: true);
    }
}
