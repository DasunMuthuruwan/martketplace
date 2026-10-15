<?php

namespace App\Http\Controllers\Account;

use App\Actions\Listings\SaveListingLocation;
use App\Exceptions\Listings\CountryChangeNeedsConfirmation;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Locality;
use App\Models\LocationRequest;
use App\Models\Region;
use App\Services\Catalogue\CategoryTree;
use App\Services\Geo\CountryContext;
use App\View\PostingSteps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Posting wizard (layout doc §3.4, global UX §2–§4): one server-rendered page per step, each saved
 * through the draft actions (T4.2–T4.4), which hold every rule. Selects that load the next level
 * (country → region → city) reload the page with GET; Continue saves with POST.
 *   create:  category, then location → store creates the draft
 *   edit:    any reachable step of an existing draft or ad → update saves that step
 */
class PostListingController extends Controller
{
    public function __construct(
        private readonly CategoryTree $tree,
        private readonly CountryContext $countries,
    ) {}

    /** New ad: category, then location (`?category=` a subcategory id). */
    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Listing::class);
        $country = $this->country($request, null);
        $category = $this->leaf($request->query('category'));

        return $category === null
            ? $this->categoryStep($request, null, $country)
            : $this->locationStep($request, null, $country, $category);
    }

    public function store(Request $request, SaveListingLocation $save): RedirectResponse
    {
        try {
            $listing = $save->handle($request->user(), null, $this->locationInput($request));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        $this->requestTown($request, $listing);

        return redirect()->route('listings.edit', [$listing, 'details'])->with('status', __('posting.saved_draft'));
    }

    public function edit(Request $request, Listing $listing, ?string $step = null): View|RedirectResponse
    {
        Gate::authorize('update', $listing);
        $steps = new PostingSteps($listing);
        $step ??= $steps->next();
        if (! in_array($step, PostingSteps::STEPS, true)) {
            abort(404);
        }
        if (! $steps->reachable($step)) {
            return redirect()->route('listings.edit', [$listing, $steps->next()]);
        }

        $listing->loadMissing(['category.parent', 'country']);
        $country = $this->country($request, $listing);

        return match ($step) {
            'category' => $this->categoryStep($request, $listing, $country),
            'location' => $this->locationStep($request, $listing, $country, $this->leaf($request->query('category')) ?? $listing->category),
            default => $this->pendingStep($listing, $step),
        };
    }

    public function update(Request $request, Listing $listing, string $step, SaveListingLocation $save): RedirectResponse
    {
        Gate::authorize('update', $listing);
        if (! in_array($step, ['category', 'location'], true)) {
            abort(404);
        }

        try {
            $save->handle($request->user(), $listing, $this->locationInput($request, $listing));
        } catch (CountryChangeNeedsConfirmation $e) {
            return back()->withInput()->with('country_change', $e->clears);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        $this->requestTown($request, $listing);

        return redirect()->route('listings.edit', [$listing, (new PostingSteps($listing->fresh()))->after('location')])
            ->with('status', __('posting.saved'));
    }

    // ---- Steps --------------------------------------------------------------------------------

    private function categoryStep(Request $request, ?Listing $listing, Country $country): View
    {
        $tree = $this->tree->forCountry($country->iso2);
        $root = $tree->firstWhere('id', (int) $request->query('root'));

        return view('posting.category', $this->shared($listing, 'category') + [
            'country' => $country,
            'tree' => $tree,
            'root' => $root,
        ]);
    }

    private function locationStep(Request $request, ?Listing $listing, Country $country, ?Category $category): View
    {
        // Values: what was just submitted, then the query (a select reloaded the page), then the draft.
        // The draft's places only apply while the country is unchanged.
        $draft = $listing !== null && $listing->country_id === $country->id ? $listing : null;
        $pick = fn (string $key) => $request->old($key, $request->query($key, $draft?->{$key}));
        $regionId = $this->id($pick('region_id'));
        $subregionId = $this->id($pick('subregion_id'));
        $cityId = $this->id($pick('city_id'));
        $levels = $country->region_levels;
        $cityParent = $levels >= 2 ? $subregionId : $regionId;

        return view('posting.location', $this->shared($listing, 'location') + [
            'country' => $country,
            'category' => $category,
            'countries' => Country::active()->get()->sortBy('name')->values(),
            'regions' => $levels > 0 ? Region::where('country_id', $country->id)->where('level', 1)->active()->orderBy('sort_order')->get()->sortBy('name') : collect(),
            'subregions' => $levels >= 2 && $regionId ? Region::where('parent_id', $regionId)->active()->get()->sortBy('name') : collect(),
            'cities' => $levels === 0
                ? City::where('country_id', $country->id)->active()->orderBy('ascii_name')->limit(1000)->get()
                : ($cityParent ? City::where('region_id', $cityParent)->active()->orderBy('ascii_name')->get() : collect()),
            'localities' => $cityId ? Locality::where('city_id', $cityId)->active()->orderBy('slug')->get() : collect(),
            'values' => [
                'region_id' => $regionId,
                'subregion_id' => $subregionId,
                'city_id' => $cityId,
                'locality_id' => $this->id($pick('locality_id')),
                'locality_text' => $pick('locality_text'),
            ],
            'countryChange' => session('country_change'),
        ]);
    }

    private function pendingStep(Listing $listing, string $step): View
    {
        return view('posting.pending', $this->shared($listing, $step));
    }

    /** @return array<string, mixed> */
    private function shared(?Listing $listing, string $step): array
    {
        return ['listing' => $listing, 'step' => $step, 'steps' => new PostingSteps($listing)];
    }

    // ---- Input ------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function locationInput(Request $request, ?Listing $listing = null): array
    {
        $input = $request->only(['category_id', 'country_id', 'region_id', 'subregion_id', 'city_id', 'locality_id', 'locality_text', 'confirm_country_change']);
        // Changing only the category of a draft keeps its location.
        if ($listing !== null && $request->input('keep_location')) {
            $input += $listing->only(['country_id', 'region_id', 'subregion_id', 'city_id', 'locality_id', 'locality_text']);
        }

        return array_map(fn ($value) => $value === '' ? null : $value, $input);
    }

    /** "My town isn't listed" (global UX §2): staff get a request; the seller picks the nearest town meanwhile. */
    private function requestTown(Request $request, Listing $listing): void
    {
        $town = trim((string) $request->input('proposed_city'));
        if ($town === '' || mb_strlen($town) > 80 || preg_match('/[<>]/', $town) === 1) {
            return;
        }

        LocationRequest::create([
            'user_id' => $request->user()->id,
            'country_id' => $listing->country_id,
            'region_id' => $listing->region_id,
            'subregion_id' => $listing->subregion_id,
            'proposed_city' => $town,
        ]);
    }

    private function country(Request $request, ?Listing $listing): Country
    {
        $chosen = $this->id($request->old('country_id', $request->query('country_id')));
        $country = $chosen ? Country::active()->find($chosen) : null;

        return $country
            ?? ($listing?->country?->is_active ? $listing->country : null)
            ?? $this->countries->current($request)
            ?? ($request->user()->country_id ? Country::active()->find($request->user()->country_id) : null)
            ?? Country::active()->orderBy('sort_order')->firstOrFail();
    }

    private function leaf(mixed $id): ?Category
    {
        $id = $this->id($id);
        $category = $id ? Category::with('parent')->find($id) : null;

        return $category?->parent_id !== null ? $category : null;
    }

    private function id(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
