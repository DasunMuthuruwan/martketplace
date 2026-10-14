<?php

namespace App\Console\Commands\Retention;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Notifications\InactiveAccountWarning;
use App\Services\Audit\AuditLogger;
use App\Services\Retention\AccountAnonymiser;
use App\Services\Retention\ListingPurger;
use App\Support\UserSessions;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data retention (docs/architecture/09-data-retention.md §1, CD-07). Periods come from
 * `marketplace.retention.*`. Records under legal hold (the listing or its owner) are skipped.
 * Idempotent and safe to re-run; `--dry-run` only counts. Contact reveals, ban hashes (model:prune)
 * and failed jobs (queue:prune-failed) have their own scheduled commands.
 */
class PruneData extends Command
{
    protected $signature = 'marketplace:prune {--dry-run : count what would be removed, change nothing}';

    protected $description = 'Delete or anonymise data past its retention period';

    private bool $dryRun = false;

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(ListingPurger $listings, AccountAnonymiser $accounts, AuditLogger $audit): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $this->pruneListings($listings);
        $this->pruneAccounts($accounts, $audit);
        $this->pruneReportsAndAudit();

        $this->table(['data', $this->dryRun ? 'would change' : 'changed'], collect($this->counts)->map(fn (int $n, string $k) => [$k, $n])->values()->all());

        return self::SUCCESS;
    }

    private function pruneListings(ListingPurger $purger): void
    {
        $r = config('marketplace.retention');

        // Deleted by the seller: photos first, the row later.
        $this->each('deleted listing photos', $this->listings()->onlyTrashed()->where('deleted_at', '<', $this->daysAgo($r['deleted_listing_images']))->whereHas('images'),
            fn (Listing $l) => $purger->deleteImages($l));
        $this->each('deleted listings', $this->listings()->onlyTrashed()->where('deleted_at', '<', $this->daysAgo($r['deleted_listing_rows'])),
            fn (Listing $l) => $purger->purge($l));

        // Expired (the status flips hourly; an active ad past its date counts too).
        $this->each('expired listings', $this->listings()->whereIn('status', [ListingStatus::Expired, ListingStatus::Active])
            ->where('expires_at', '<', $this->daysAgo($r['expired_listing_rows'])), fn (Listing $l) => $purger->purge($l));

        $this->each('sold listing photos', $this->listings()->where('status', ListingStatus::Sold)->where('sold_at', '<', $this->daysAgo($r['sold_listing_images']))->whereHas('images'),
            fn (Listing $l) => $purger->deleteImages($l));
        $this->each('sold listings', $this->listings()->where('status', ListingStatus::Sold)->where('sold_at', '<', $this->daysAgo($r['sold_listing_rows'])),
            fn (Listing $l) => $purger->purge($l));

        $this->each('rejected listings', $this->listings()->where('status', ListingStatus::Rejected)->where('moderated_at', '<', $this->daysAgo($r['rejected_listing'])),
            fn (Listing $l) => $purger->purge($l));

        $this->each('stale drafts', $this->listings()->where('status', ListingStatus::Draft)->where('updated_at', '<', $this->daysAgo($r['stale_draft'])),
            fn (Listing $l) => $purger->purge($l));
    }

    private function pruneAccounts(AccountAnonymiser $anonymiser, AuditLogger $audit): void
    {
        $r = config('marketplace.retention');
        $grace = $this->daysAgo($r['deleted_account_grace']);

        // Inactive for years: warn once, then delete if they still have not signed in.
        $this->each('inactive accounts warned', $this->users()->whereNull('inactive_warned_at')->doesntHave('roles')
            ->whereRaw('coalesce(last_login_at, created_at) < ?', [$this->daysAgo($r['inactive_account'])]),
            function (User $user): void {
                $user->forceFill(['inactive_warned_at' => now()])->saveQuietly();
                $user->notify(new InactiveAccountWarning);
            });
        $this->each('inactive accounts deleted', $this->users()->doesntHave('roles')->where('inactive_warned_at', '<', $grace)
            ->where(fn (Builder $q) => $q->whereNull('last_login_at')->orWhereColumn('last_login_at', '<', 'inactive_warned_at')),
            function (User $user) use ($audit): void {
                DB::transaction(function () use ($user): void {
                    $user->favourites()->detach();
                    UserSessions::endAll($user);
                    $user->delete();
                });
                $audit->record(null, 'account.deleted_inactive', $user);
            });

        // Deleted (by the person, staff or the step above) or banned: anonymise after the grace period.
        $this->each('deleted accounts anonymised', User::onlyTrashed()->whereNull('anonymised_at')->where('legal_hold', false)->where('deleted_at', '<', $grace),
            fn (User $user) => $anonymiser->anonymise($user));
        $this->each('banned accounts anonymised', $this->users()->where('status', UserStatus::Banned)->where('status_changed_at', '<', $grace),
            fn (User $user) => $anonymiser->anonymise($user));
    }

    private function pruneReportsAndAudit(): void
    {
        $r = config('marketplace.retention');

        $reports = Report::where('status', '!=', ReportStatus::Open)->where('handled_at', '<', $this->daysAgo($r['resolved_report']));
        $this->counts['closed reports'] = $this->dryRun ? $reports->count() : $reports->delete();

        // The model refuses deletes (append-only); retention is the one sanctioned removal.
        $audit = AuditLog::query()->toBase()->where('created_at', '<', $this->daysAgo($r['audit_log']));
        $this->counts['audit log entries'] = $this->dryRun ? $audit->count() : $audit->delete();
    }

    /**
     * Listings not under legal hold, whose owner is not under legal hold either.
     *
     * @return Builder<Listing>
     */
    private function listings(): Builder
    {
        return Listing::query()->where('legal_hold', false)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('users')->whereColumn('users.id', 'listings.user_id')->where('users.legal_hold', true));
    }

    /**
     * Live, not anonymised, not under legal hold.
     *
     * @return Builder<User>
     */
    private function users(): Builder
    {
        return User::query()->whereNull('anonymised_at')->where('legal_hold', false);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): mixed  $action
     */
    private function each(string $label, Builder $query, callable $action): void
    {
        if ($this->dryRun) {
            $this->counts[$label] = $query->count();

            return;
        }

        $done = 0;
        // chunkById pages by id, so removing or changing matched rows does not skip any.
        $query->chunkById(100, function ($models) use ($action, &$done): void {
            foreach ($models as $model) {
                $action($model);
                $done++;
            }
        });
        $this->counts[$label] = $done;
    }

    private function daysAgo(int|string $days): Carbon
    {
        return now()->subDays((int) $days);
    }
}
