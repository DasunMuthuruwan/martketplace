<?php

namespace App\Console\Commands\Listings;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Notifications\ListingExpired;
use App\Notifications\ListingExpiringSoon;
use Illuminate\Console\Command;

/**
 * LST-08. Visibility never waits for this command (the public scope checks expires_at); it keeps
 * the status in step and sends the emails: "expires soon" once per publication period, then
 * "expired". Idempotent: each listing is handled once per state.
 */
class ExpireListings extends Command
{
    protected $signature = 'marketplace:expire-listings';

    protected $description = 'Mark expired listings and send expiry reminders';

    public function handle(): int
    {
        $reminded = 0;
        $expired = 0;
        $reminderDays = (int) config('marketplace.listings.expiry_reminder_days');

        Listing::with('user')->where('status', ListingStatus::Active->value)
            ->whereNull('expiry_reminder_sent_at')
            ->whereBetween('expires_at', [now(), now()->addDays($reminderDays)])
            ->chunkById(200, function ($listings) use (&$reminded): void {
                foreach ($listings as $listing) {
                    $listing->forceFill(['expiry_reminder_sent_at' => now()])->save();
                    $listing->user?->notify(new ListingExpiringSoon($listing));
                    $reminded++;
                }
            });

        Listing::with('user')->where('status', ListingStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($listings) use (&$expired): void {
                foreach ($listings as $listing) {
                    $listing->forceFill(['status' => ListingStatus::Expired])->save();
                    $listing->user?->notify(new ListingExpired($listing));
                    $expired++;
                }
            });

        $this->info("Reminders sent: {$reminded}. Listings expired: {$expired}.");

        return self::SUCCESS;
    }
}
