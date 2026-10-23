<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\SellerReview;
use App\Services\Reviews\ReviewModeration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Review moderation (G5b, ADR-027, `reviews.moderate`): comments waiting for a decision, reported
 * reviews (keep or remove with a reason), and removed reviews. Staff never decide on reviews about
 * or by themselves. Every decision is audited.
 */
#[Layout('layouts.admin')]
#[Title('Reviews')]
class ReviewsAdmin extends Component
{
    use RequiresPermission, WithPagination;

    public const TABS = ['comments', 'reported', 'removed'];

    #[Url]
    public string $tab = 'comments';

    public ?int $removingId = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::REVIEWS_MODERATE);
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'comments';
        }
    }

    public function updatedTab(): void
    {
        $this->resetPage();
        $this->cancelRemove();
    }

    public function approve(int $id, ReviewModeration $moderation): void
    {
        $this->act(fn (SellerReview $r) => $moderation->approveComment(Auth::user(), $r), $id, 'approved');
    }

    public function reject(int $id, ReviewModeration $moderation): void
    {
        $this->act(fn (SellerReview $r) => $moderation->rejectComment(Auth::user(), $r), $id, 'rejected');
    }

    public function keep(int $id, ReviewModeration $moderation): void
    {
        $this->act(fn (SellerReview $r) => $moderation->keep(Auth::user(), $r), $id, 'kept');
    }

    public function startRemove(int $id): void
    {
        $this->requirePermission(Permissions::REVIEWS_MODERATE);
        $this->removingId = $id;
        $this->reason = '';
        $this->resetErrorBag();
    }

    public function cancelRemove(): void
    {
        $this->reset(['removingId', 'reason']);
        $this->resetErrorBag();
    }

    public function remove(ReviewModeration $moderation): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => __('admin.reviews.reason')]);
        $this->act(fn (SellerReview $r) => $moderation->remove(Auth::user(), $r, $this->reason), (int) $this->removingId, 'removed');
        $this->cancelRemove();
    }

    private function act(callable $decision, int $id, string $done): void
    {
        $this->requirePermission(Permissions::REVIEWS_MODERATE);
        try {
            $decision(SellerReview::findOrFail($id));
        } catch (ValidationException $e) {
            session()->flash('error', collect($e->errors())->flatten()->first());

            return;
        }
        session()->flash('status', __('admin.reviews.done.'.$done));
    }

    public function render(): View
    {
        $query = SellerReview::query()->with(['seller', 'reviewer', 'reports'])->withCount('reports');
        $query = match ($this->tab) {
            'reported' => $query->published()->whereNotNull('reported_at')->orderBy('reported_at'),
            'removed' => $query->where('status', SellerReview::REMOVED)->latest('moderated_at'),
            default => $query->published()->where('comment_status', SellerReview::COMMENT_PENDING)->orderBy('updated_at'),
        };

        return view('livewire.admin.reviews', ['reviews' => $query->orderBy('id')->paginate(30)]);
    }
}
