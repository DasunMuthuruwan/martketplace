<?php

namespace App\Http\Controllers\Account;

use App\Actions\Listings\ListingLifecycle;
use App\Enums\BoostOrderStatus;
use App\Enums\BoostType;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\BoostOrder;
use App\Models\Listing;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Listings\ListingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * My listings (routes doc §3, layout doc §3.5): the seller's ads by status tab, with renew, mark
 * sold and delete through ListingLifecycle (owner-only policy). Plain forms and redirects.
 */
class ListingsController extends Controller
{
    /** Tab => what it shows. "Active" means live now; a live ad past its date counts as expired. */
    public const TABS = ['active', 'pending', 'drafts', 'rejected', 'expired', 'sold'];

    public function __construct(private readonly ListingLifecycle $lifecycle) {}

    public function index(Request $request, ListingPresenter $present, BoostCatalogue $catalogue): View
    {
        $user = $request->user();
        $tab = in_array($request->query('status'), self::TABS, true) ? $request->query('status') : 'active';
        $counts = self::counts($user->id);

        $listings = self::tab(Listing::where('user_id', $user->id), $tab)
            ->with(['country', 'currencyModel', 'coverImage', 'category.parent', 'city', 'region', 'subregion', 'locality',
                // Running or queued Top ads, shown on the row (B3).
                'boosts' => fn ($q) => $q->where('type', BoostType::Top->value)->whereNull('ended_at')->where('ends_at', '>', now())->orderBy('starts_at')])
            ->withCount('images')
            ->latest('updated_at')->latest('id')
            ->paginate(20)->withQueryString();

        return view('account.listings', [
            'tab' => $tab,
            'counts' => $counts,
            'listings' => $listings,
            'publicIds' => self::publicIds($listings->getCollection()),
            // Promotions (B5): countries where selling is open, and unpaid orders per ad.
            'promotable' => $listings->getCollection()->pluck('country')->filter()->unique('id')
                ->filter(fn ($country) => $catalogue->isOpen($country))->pluck('id')->all(),
            'pendingOrders' => BoostOrder::where('user_id', $user->id)->where('status', BoostOrderStatus::Pending->value)
                ->whereIn('listing_id', $listings->getCollection()->modelKeys())->get()->keyBy('listing_id'),
            'present' => $present,
            'lifecycle' => $this->lifecycle,
        ]);
    }

    /**
     * Which of these ads are public, in one query (F-08: the rows asked one by one).
     *
     * @param  Collection<int, Listing>  $listings
     * @return list<int>
     */
    public static function publicIds(Collection $listings): array
    {
        if ($listings->isEmpty()) {
            return [];
        }

        return Listing::public()->whereIn('listings.id', $listings->modelKeys())->pluck('listings.id')->map(fn ($id) => (int) $id)->all();
    }

    public function sold(Request $request, Listing $listing): RedirectResponse
    {
        return $this->run(fn () => $this->lifecycle->markSold($request->user(), $listing), __('account.listings.marked_sold'), 'sold');
    }

    public function renew(Request $request, Listing $listing): RedirectResponse
    {
        return $this->run(fn () => $this->lifecycle->renew($request->user(), $listing), __('account.listings.renewed'), 'active');
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        return $this->run(fn () => $this->lifecycle->delete($request->user(), $listing), __('account.listings.deleted'), null);
    }

    /** @return array<string, int> tab => number of the user's listings */
    public static function counts(int $userId): array
    {
        $counts = [];
        foreach (self::TABS as $tab) {
            $counts[$tab] = self::tab(Listing::where('user_id', $userId), $tab)->count();
        }

        return $counts;
    }

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    private static function tab(Builder $query, string $tab): Builder
    {
        $now = now();

        return match ($tab) {
            'pending' => $query->where('status', ListingStatus::PendingReview),
            'drafts' => $query->where('status', ListingStatus::Draft),
            'rejected' => $query->whereIn('status', [ListingStatus::Rejected, ListingStatus::Suspended]),
            'expired' => $query->where(fn (Builder $q) => $q->where('status', ListingStatus::Expired)
                ->orWhere(fn (Builder $q) => $q->where('status', ListingStatus::Active)->where('expires_at', '<=', $now))),
            'sold' => $query->where('status', ListingStatus::Sold),
            default => $query->where('status', ListingStatus::Active)->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
        };
    }

    private function run(callable $action, string $message, ?string $tab): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        $back = back();

        return ($tab !== null ? redirect()->route('account.listings', ['status' => $tab]) : $back)->with('status', $message);
    }
}
