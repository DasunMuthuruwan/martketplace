<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\AuditLog;
use App\Models\Listing;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Recent changes" feed (Q-MOD-1): price edits on submitted ads apply at once and are listed
 * here for moderators, newest first, from the audit log (`listing.price_changed`).
 */
#[Layout('layouts.admin')]
#[Title('Recent changes')]
class RecentChanges extends Component
{
    use RequiresPermission, WithPagination;

    public const PER_PAGE = 30;

    public function mount(): void
    {
        $this->requirePermission(Permissions::LISTINGS_MODERATE);
    }

    public function render(): View
    {
        $changes = AuditLog::where('action', 'listing.price_changed')
            ->where('subject_type', (new Listing)->getMorphClass())
            ->latest('id')
            ->paginate(self::PER_PAGE);
        $listings = Listing::withTrashed()->with(['currencyModel', 'country'])
            ->whereIn('id', $changes->getCollection()->pluck('subject_id'))->get()->keyBy('id');

        return view('livewire.admin.recent-changes', ['changes' => $changes, 'listings' => $listings]);
    }
}
