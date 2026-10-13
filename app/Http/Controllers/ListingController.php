<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\Attributes\AttributeSchema;
use App\Services\Contact\ContactResolver;
use App\Services\Listings\ListingPresenter;
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

            return response()->view('public.listing', $this->viewData($request, $listing));
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
            'currentCountry' => $listing->country,
            'robots' => config('marketplace.seo.indexing') ? 'index,follow' : 'noindex,nofollow',
        ];
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
            $text = match (true) {
                is_array($value) && isset($value['value'], $value['unit']) => $value['value'].' '.$value['unit'],
                is_array($value) => implode(', ', $value),
                is_bool($value) => $value ? __('Yes') : __('No'),
                default => (string) $value,
            };
            $rows[$labels[$key] ?? $key] = $text;
        }

        return $rows;
    }

    /** @return Collection<int, Listing> */
    private function similar(Listing $listing): Collection
    {
        return Listing::public()->where('category_id', $listing->category_id)->where('country_id', $listing->country_id)
            ->whereKeyNot($listing->id)
            ->with(['country', 'currencyModel', 'coverImage', 'city', 'region', 'subregion'])
            ->orderByDesc('published_at')->limit(self::SIMILAR)->get();
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
