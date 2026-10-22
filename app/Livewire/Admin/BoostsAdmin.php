<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\BoostType;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Listing;
use App\Models\ListingBoost;
use App\Services\Boosts\BoostManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Boosts (ADR-026, B3, `boosts.manage`): grant a Bump or a Top ad to a live ad without payment
 * (goodwill, testing, before selling opens), see running, upcoming and ended boosts, and end one
 * early. Staff never boost their own ads (like moderation, CD-06). Every grant and end is audited.
 */
#[Layout('layouts.admin')]
#[Title('Boosts')]
class BoostsAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public const STATES = ['running', 'upcoming', 'ended'];

    #[Url]
    public string $state = 'running';

    /** Ad number or its address (…/ad/some-title-123). */
    public string $ad = '';

    public string $type = 'top';

    public int $days = 7;

    public function mount(): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        if (! in_array($this->state, self::STATES, true)) {
            $this->state = 'running';
        }
    }

    public function updatedState(): void
    {
        $this->resetPage();
    }

    public function grant(BoostManager $boosts): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $this->validate([
            'ad' => ['required', 'string', 'max:300'],
            'type' => ['required', Rule::enum(BoostType::class)],
            'days' => ['required_if:type,top', 'integer', 'min:1', 'max:'.BoostManager::MAX_DAYS],
        ]);

        $listing = $this->findListing();
        if ($listing === null) {
            $this->addError('ad', __('admin.boosts.not_found'));

            return;
        }
        if ($listing->user_id === Auth::id()) {
            $this->addError('ad', __('admin.boosts.own_ad'));

            return;
        }

        $type = BoostType::from($this->type);
        try {
            $type === BoostType::Bump ? $boosts->bump($listing, Auth::user()) : $boosts->top($listing, $this->days, Auth::user());
        } catch (ValidationException $e) {
            $this->addError($type === BoostType::Top && isset($e->errors()['days']) ? 'days' : 'ad', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('status', __('admin.boosts.granted', ['type' => __('admin.boosts.types.'.$this->type), 'title' => $listing->title]));
        $this->reset(['ad']);
    }

    public function end(int $id, BoostManager $boosts): void
    {
        $this->requirePermission(Permissions::BOOSTS_MANAGE);
        $boost = ListingBoost::with('listing')->findOrFail($id);
        $boosts->end($boost, Auth::user(), __('admin.boosts.ended_by_staff'));
        session()->flash('status', __('admin.boosts.ended', ['title' => $boost->listing->title]));
    }

    private function findListing(): ?Listing
    {
        $input = trim($this->ad);
        $id = ctype_digit($input) ? (int) $input : (preg_match('#-(\d+)/?(?:[?\#].*)?$#', $input, $m) ? (int) $m[1] : null);

        return $id === null ? null : Listing::find($id);
    }

    public function render(): View
    {
        $now = now();
        $boosts = ListingBoost::query()
            ->with(['listing.user', 'grantedBy'])
            ->when($this->state === 'running', fn ($q) => $q->where(fn ($q) => $q->running()->orWhere(fn ($q) => $q->where('type', BoostType::Bump->value)->where('starts_at', '>=', $now->copy()->subDay()))))
            ->when($this->state === 'upcoming', fn ($q) => $q->whereNull('ended_at')->where('starts_at', '>', $now))
            ->when($this->state === 'ended', fn ($q) => $q->where(fn ($q) => $q->whereNotNull('ended_at')->orWhere('ends_at', '<=', $now)))
            ->orderByDesc('starts_at')->orderByDesc('id')
            ->paginate(30);

        return view('livewire.admin.boosts', ['boosts' => $boosts]);
    }
}
