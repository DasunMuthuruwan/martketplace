<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\Attributes\AttributeSchema;
use App\Services\Contact\ContactResolver;
use App\Services\Listings\ListingPresenter;
use App\Services\Listings\ListingViews;
use App\Services\Security\Turnstile;
use App\Services\Seo\PageSeo;
use App\Services\Seo\StructuredData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * `/ad/{slug}-{id}` (LST-11, CD-12, routes doc §1). The id decides; a different slug answers 301.
 *   public            → 200, the ad
 *   sold recently     → 200 "sold" page, noindex (retention §1: listings.sold_page_days)
 *   expired, sold long ago, deleted → 410 Gone with a link to similar ads
 *   anything else (draft, pending, rejected, suspended, inactive country/category, owner
 *   suspended) → 404, so nothing leaks about ads that were never public.
 */
class ListingController extends Controller
{
    public const SIMILAR = 4;

    public function __construct(
        private readonly ListingPresenter $present,
        private readonly ContactResolver $contact,
        private readonly AttributeSchema $schemas,
        private readonly StructuredData $structured,
        private readonly ListingViews $views,
    ) {}

    public function show(Request $request, string $slugId): Response|RedirectResponse
    {
        $id = (int) substr($slugId, (int) strrpos($slugId, '-') + 1);
        $listing = Listing::withTrashed()->with(['category.parent', 'country', 'currencyModel', 'images', 'coverImage', 'city', 'region', 'subregion', 'locality', 'user'])->find($id);
        if ($listing === null) {
            abort(404);
        }

        if ($listing->isPublic()) {
            if ($slugId !== $listing->slug.'-'.$listing->id) {
                return redirect()->to($listing->publicUrl(), 301);
            }

            $this->views->record($request, $listing);

            return response()->view('public.listing', $this->viewData($request, $listing) + ['ownerView' => $this->ownedBy($request, $listing)]);
        }

        // The owner sees their own ad in any state with a status banner (components §5.2); nobody
        // else learns anything about it. Never indexed. A draft without details goes to the wizard.
        if ($this->ownedBy($request, $listing) && ! $listing->trashed()) {
            if ($listing->title === null) {
                return redirect()->route('listings.edit', $listing);
            }
            $data = $this->viewData($request, $listing);
            $data['seo'] = PageSeo::make((string) $listing->title, null, null, false);

            return response()->view('public.listing', $data + ['ownerView' => true]);
        }

        return match (true) {
            $listing->trashed(), $this->isExpired($listing) => $this->gone($listing),
            $listing->status === ListingStatus::Sold => $this->sold($listing),
            default => abort(404),
        };
    }

    /** @return array<string, mixed> */
    private function viewData(Request $request, Listing $listing): array
    {
        $labels = [];
        foreach ($this->schemas->for($listing->category, $listing->country->iso2)->form() as $field) {
            $labels[$field['key']] = $field['label'];
        }

        return [
            'listing' => $listing,
            'present' => $this->present,
            'attributes' => $this->attributeRows($listing, $labels),
            'buttons' => $this->contact->buttons($listing, $request->user()),
            'favourited' => $request->user()?->favourites()->whereKey($listing->id)->exists() ?? false,
            'similar' => $this->similar($listing),
            'sellerAds' => Listing::public()->where('listings.user_id', $listing->user_id)->count(),
            'turnstileSiteKey' => app(Turnstile::class)->enabled() ? app(Turnstile::class)->siteKey() : null,
            'currentCountry' => $listing->country,
            'seo' => $this->seo($listing),
        ];
    }

    /** Title "{title} – {place}, {country}", price in the description, cover as OG image, JSON-LD. */
    private function seo(Listing $listing): PageSeo
    {
        $place = $listing->city->name ?? ($listing->subregion_id !== null ? $listing->subregion->name : ($listing->region_id !== null ? $listing->region->name : null));
        $title = $listing->title.' – '.implode(', ', array_filter([$place, $listing->country->name]));
        $description = trim($this->present->price($listing).' · '.$this->present->location($listing, true).'. '.$listing->description);

        $cc = strtolower($listing->country->iso2);
        $category = $listing->category;
        $crumbs = [[$listing->country->name, route('country.home', ['cc' => $cc])]];
        if ($category->parent !== null) {
            $crumbs[] = [$category->parent->name, route('ads.category', ['cc' => $cc, 'category' => $category->parent->slug])];
            $crumbs[] = [$category->name, route('ads.category', ['cc' => $cc, 'category' => $category->parent->slug, 'subcategory' => $category->slug])];
        } else {
            $crumbs[] = [$category->name, route('ads.category', ['cc' => $cc, 'category' => $category->slug])];
        }
        $crumbs[] = [$listing->title, $listing->publicUrl()];

        return PageSeo::make($title, $description, $listing->publicUrl(), true, $this->present->coverUrl($listing, 'lg'), 'product')
            ->withJsonLd($this->structured->product($listing))
            ->withJsonLd($this->structured->breadcrumbs($crumbs));
    }

    /**
     * Label => display value from the card summary (no extra queries). Areas show as entered.
     *
     * @param  array<string, string>  $labels
     * @return array<string, string>
     */
    private function attributeRows(Listing $listing, array $labels): array
    {
        $rows = [];
        foreach ($listing->attributes_summary ?? [] as $key => $value) {
            $rows[$labels[$key] ?? $key] = $this->present->attributeValue($value);
        }

        return $rows;
    }

    /** @return Collection<int, Listing> */
    private function similar(Listing $listing): Collection
    {
        return Listing::public()->where('category_id', $listing->category_id)->where('country_id', $listing->country_id)
            ->whereKeyNot($listing->id)
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion', 'locality', 'category.parent'])
            ->withCount('images')
            ->orderByDesc('sorted_at')->limit(self::SIMILAR)->get();
    }

    private function ownedBy(Request $request, Listing $listing): bool
    {
        return $request->user() !== null && $request->user()->id === $listing->user_id;
    }

    private function isExpired(Listing $listing): bool
    {
        return $listing->status === ListingStatus::Expired
            || ($listing->status === ListingStatus::Active && $listing->expires_at !== null && $listing->expires_at->isPast())
            || ($listing->status === ListingStatus::Sold && ($listing->sold_at === null || $listing->sold_at->isBefore(now()->subDays((int) config('marketplace.listings.sold_page_days')))));
    }

    private function sold(Listing $listing): Response
    {
        return response()->view('public.listing-gone', ['listing' => $listing, 'sold' => true, 'robots' => 'noindex,follow', 'currentCountry' => $listing->country], 200);
    }

    private function gone(Listing $listing): Response
    {
        return response()->view('public.listing-gone', ['listing' => $listing, 'sold' => false, 'robots' => 'noindex,follow', 'currentCountry' => $listing->country], 410);
    }
}
