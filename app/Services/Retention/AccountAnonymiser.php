<?php

namespace App\Services\Retention;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Report;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\UserSessions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anonymises an account (retention §1–§2): listings and photos are deleted; name, email, phones,
 * password, 2FA and login data are replaced or cleared; the row stays (soft-deleted) so audit
 * records keep a meaningless id. Reports the person filed lose their reporter. Ban hashes in
 * `banned_identities` are separate and stay for their own period. Not reversible.
 */
class AccountAnonymiser
{
    public function __construct(
        private readonly ListingPurger $listings,
        private readonly AuditLogger $audit,
    ) {}

    /** @return int listings deleted */
    public function anonymise(User $user): int
    {
        $deleted = 0;
        foreach (Listing::withTrashed()->where('user_id', $user->id)->get() as $listing) {
            $this->listings->purge($listing);
            $deleted++;
        }
        // Draft photos never attached to a listing.
        ListingImage::where('user_id', $user->id)->get()->each(fn (ListingImage $image) => $image->delete());

        DB::transaction(function () use ($user): void {
            $user->favourites()->detach();
            $user->syncRoles([]);
            Report::where('reporter_id', $user->id)->update(['reporter_id' => null]);
            DB::table('contact_reveals')->where('user_id', $user->id)->update(['user_id' => null]);
            UserSessions::endAll($user);

            $user->forceFill([
                'name' => __('account.deleted_user'),
                'email' => 'deleted+'.$user->id.'@invalid',
                'email_verified_at' => null,
                'password' => Str::random(64),
                'phone' => null,
                'phone_country' => null,
                'phone_verified_at' => null,
                'whatsapp_phone' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => null,
                'status_reason' => null,
                'last_login_at' => null,
                'inactive_warned_at' => null,
                'anonymised_at' => now(),
            ])->saveQuietly();
            if (! $user->trashed()) {
                $user->delete();
            }
        });

        $this->audit->record(null, 'account.anonymised', $user);

        return $deleted;
    }
}
