<?php

namespace App\Livewire\Admin;

use App\Actions\Engagement\ResolveReport;
use App\Actions\Listings\SuspendListing;
use App\Authorization\Permissions;
use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Listing;
use App\Models\Report;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Reports queue (MOD-01/02): open reports first, oldest first, with the reported ad or account.
 * Staff dismiss or action a report (`reports.resolve`) and may take the reported ad down in the
 * same step (`listings.suspend`); the account actions arrive with the users screen (T6.2).
 */
#[Layout('layouts.admin')]
#[Title('Reports')]
class ReportsQueue extends Component
{
    use RequiresPermission, WithPagination;

    public const PER_PAGE = 20;

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $type = '';

    #[Locked]
    public ?int $selectedId = null;

    public string $note = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::REPORTS_VIEW);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['status', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::REPORTS_VIEW);
        abort_unless(Report::whereKey($id)->exists(), 404);

        $this->selectedId = $id;
        $this->reset('note');
        $this->resetErrorBag();
    }

    /** Back to the list (phones). */
    public function close(): void
    {
        $this->reset('selectedId', 'note');
        $this->resetErrorBag();
    }

    public function dismiss(ResolveReport $resolve): void
    {
        $this->resolveWith($resolve, ReportStatus::Dismissed, 'admin.reports.dismissed');
    }

    public function action(ResolveReport $resolve): void
    {
        $this->resolveWith($resolve, ReportStatus::Actioned, 'admin.reports.actioned');
    }

    /** Take the reported ad down and close the report as actioned, together. */
    public function suspendAndAction(SuspendListing $suspend, ResolveReport $resolve): void
    {
        $this->requirePermission(Permissions::REPORTS_RESOLVE);
        $this->requirePermission(Permissions::LISTINGS_SUSPEND);
        $report = $this->selectedReport();
        $listing = $report->target();
        abort_unless($listing instanceof Listing, 404);

        try {
            DB::transaction(function () use ($suspend, $resolve, $report, $listing): void {
                if (in_array($listing->status, [ListingStatus::Active, ListingStatus::PendingReview], true)) {
                    $suspend->suspend(Auth::user(), $listing, $this->note);
                }
                $resolve->handle(Auth::user(), $report, ReportStatus::Actioned, $this->note);
            });
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        session()->flash('status', __('admin.reports.suspended'));
        $this->reset('selectedId', 'note');
    }

    public function render(): View
    {
        $selected = $this->selectedId === null ? null : Report::with('reporter')->find($this->selectedId);

        return view('livewire.admin.reports-queue', [
            'reports' => Report::with('reporter')
                ->when(in_array($this->status, ['open', 'actioned', 'dismissed'], true), fn ($q) => $q->where('status', $this->status))
                ->when(in_array($this->type, array_keys(Report::TYPES), true), fn ($q) => $q->where('reportable_type', $this->type))
                ->orderBy($this->status === 'open' ? 'created_at' : 'handled_at', $this->status === 'open' ? 'asc' : 'desc')
                ->orderBy('id')
                ->paginate(self::PER_PAGE),
            'selected' => $selected,
            'target' => $selected?->target(),
            'canSuspend' => Auth::user()->can(Permissions::LISTINGS_SUSPEND),
        ]);
    }

    private function resolveWith(ResolveReport $resolve, ReportStatus $outcome, string $message): void
    {
        $this->requirePermission(Permissions::REPORTS_RESOLVE);

        try {
            $resolve->handle(Auth::user(), $this->selectedReport(), $outcome, $this->note);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }

        session()->flash('status', __($message));
        $this->reset('selectedId', 'note');
    }

    private function selectedReport(): Report
    {
        $report = $this->selectedId === null ? null : Report::find($this->selectedId);
        abort_if($report === null, 404);

        return $report;
    }
}
