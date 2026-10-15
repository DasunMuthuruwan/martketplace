<?php

namespace App\Livewire\Admin;

use App\Actions\Listings\ModerateListing;
use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Report;
use App\Services\Attributes\AttributeSchema;
use App\Services\Listings\ListingPresenter;
use App\Services\Moderation\ListingScreener;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Moderation queue (MOD-01, CD-06): ads waiting for review, oldest first (flagged first when
 * filtered), a review pane with photos, details, the screener's reasons and a seller summary,
 * and approve / reject (reason templates or own text) / request changes. Decisions go through
 * ModerateListing (permission, not own ad, row lock, audit, seller email). Reject and request
 * changes first open a reason step ($decision); after a decision the next ad in the queue opens.
 * Keyboard shortcuts (A, R, J, K, Esc) live in resources/js/ui/admin.js.
 */
#[Layout('layouts.admin')]
#[Title('Moderation')]
class ModerationQueue extends Component
{
    use RequiresPermission, WithPagination;

    public const PER_PAGE = 20;

    #[Url]
    public bool $flaggedOnly = false;

    #[Url]
    public string $country = '';

    /** Set only by select(); the browser cannot change it. */
    #[Locked]
    public ?int $selectedId = null;

    /** The reason step that is open: reject | changes (UI state only; the actions check everything). */
    public ?string $decision = null;

    public string $template = '';

    public string $note = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::LISTINGS_MODERATE);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['flaggedOnly', 'country'], true)) {
            $this->resetPage();
        }
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::LISTINGS_MODERATE);
        abort_unless(Listing::whereKey($id)->where('status', ListingStatus::PendingReview->value)->exists(), 404);

        $this->selectedId = $id;
        $this->reset('decision', 'template', 'note');
        $this->resetErrorBag();
    }

    /** Back to the list (phones). */
    public function close(): void
    {
        $this->reset('selectedId', 'decision', 'template', 'note');
        $this->resetErrorBag();
    }

    public function startDecision(string $kind): void
    {
        abort_unless(in_array($kind, ['reject', 'changes'], true) && $this->selectedId !== null, 404);
        $this->decision = $kind;
        $this->reset('template', 'note');
        $this->resetErrorBag();
    }

    public function cancelDecision(): void
    {
        $this->reset('decision', 'template', 'note');
        $this->resetErrorBag();
    }

    public function approve(ModerateListing $moderate): void
    {
        $this->decide(fn (Listing $listing) => $moderate->approve(Auth::user(), $listing), 'admin.moderation.approved');
    }

    public function reject(ModerateListing $moderate): void
    {
        $this->decide(fn (Listing $listing) => $moderate->reject(Auth::user(), $listing, $this->reason()), 'admin.moderation.rejected');
    }

    public function requestChanges(ModerateListing $moderate): void
    {
        $this->decide(fn (Listing $listing) => $moderate->requestChanges(Auth::user(), $listing, $this->reason()), 'admin.moderation.changes_requested');
    }

    public function render(ListingScreener $screener, ListingPresenter $present, AttributeSchema $schemas): View
    {
        $selected = $this->selectedId === null ? null
            : Listing::with(['user', 'category.parent', 'country', 'currencyModel', 'images', 'city', 'region', 'subregion', 'locality'])->find($this->selectedId);

        return view('livewire.admin.moderation-queue', [
            'queue' => $this->queue()->with(['user', 'category', 'country', 'coverImage'])->paginate(self::PER_PAGE),
            'countries' => Country::active()->get()->sortBy('name'),
            'selected' => $selected,
            'reasons' => $selected === null ? [] : $screener->screen($selected)->flags,
            'seller' => $selected === null ? null : $this->sellerSummary($selected),
            'fields' => $selected === null ? [] : $this->fieldRows($selected, $present, $schemas),
            'present' => $present,
            'templates' => (array) __('admin.moderation.templates'),
        ]);
    }

    /** @return Builder<Listing> Pending ads in review order, with the current filters. */
    private function queue(): Builder
    {
        return Listing::where('status', ListingStatus::PendingReview->value)
            ->when($this->flaggedOnly, fn ($q) => $q->where('flagged', true))
            ->when($this->country !== '', fn ($q) => $q->whereHas('country', fn ($q) => $q->where('iso2', strtoupper($this->country))))
            ->orderByDesc('flagged')->orderBy('submitted_at')->orderBy('id');
    }

    /**
     * Label => value for the category's fields, as buyers will see them.
     *
     * @return array<string, string>
     */
    private function fieldRows(Listing $listing, ListingPresenter $present, AttributeSchema $schemas): array
    {
        $labels = [];
        foreach ($schemas->for($listing->category, $listing->country->iso2)->form() as $field) {
            $labels[$field['key']] = $field['label'];
        }
        $rows = [];
        foreach ($listing->attributes_summary ?? [] as $key => $value) {
            $rows[$labels[$key] ?? $key] = $present->attributeValue($value);
        }

        return $rows;
    }

    /** @param  callable(Listing): Listing  $decision */
    private function decide(callable $decision, string $message): void
    {
        $this->requirePermission(Permissions::LISTINGS_MODERATE);
        $listing = $this->selectedId === null ? null : Listing::find($this->selectedId);
        abort_if($listing === null, 404);

        try {
            $decision($listing);
        } catch (ValidationException $e) {
            // Show the action's message (e.g. "already decided") on the pane.
            $this->setErrorBag($e->validator->errors());

            return;
        }

        session()->flash('status', __($message, ['title' => $listing->title]));
        $this->reset('decision', 'template', 'note');
        // Straight on to the next ad waiting (none: back to the empty state).
        $this->selectedId = $this->queue()->value('id');
    }

    /** The chosen template, then the moderator's own words. */
    private function reason(): string
    {
        $templates = (array) __('admin.moderation.templates');
        $text = trim(($templates[$this->template] ?? '').' '.trim($this->note));

        return $text;
    }

    /** @return array<string, mixed> */
    private function sellerSummary(Listing $listing): array
    {
        $seller = $listing->user;
        $listings = Listing::withTrashed()->where('user_id', $seller->id);

        return [
            'name' => $seller->name,
            'since' => $seller->created_at,
            'verified' => $seller->hasVerifiedEmail(),
            'status' => $seller->status->value,
            'published' => (clone $listings)->whereNotNull('published_at')->count(),
            'rejected' => (clone $listings)->where('status', ListingStatus::Rejected->value)->count(),
            'open_reports' => Report::where('status', ReportStatus::Open->value)
                ->where(fn ($q) => $q->where(fn ($q) => $q->where('reportable_type', 'user')->where('reportable_id', $seller->id))
                    ->orWhere(fn ($q) => $q->where('reportable_type', 'listing')->whereIn('reportable_id', Listing::withTrashed()->where('user_id', $seller->id)->select('id'))))
                ->count(),
        ];
    }
}
