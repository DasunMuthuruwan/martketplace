<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserStatus;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use App\Notifications\AccountStatusChanged;
use App\Notifications\InactiveAccountWarning;
use App\Notifications\ListingApproved;
use App\Notifications\ListingExpired;
use App\Notifications\ListingExpiringSoon;
use App\Notifications\ListingRejected;
use App\Notifications\ListingSuspended;
use App\Notifications\TwoFactorResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * F-05: every email is rendered for real (other tests fake notifications, so a broken template or a
 * missing translation would only show in a user's inbox). Subject and body must be translated
 * text, the HTML must render, and links must use APP_URL.
 */
class NotificationMailsRenderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: callable(Listing): Notification}> */
    public static function notifications(): array
    {
        return [
            'listing approved' => [fn (Listing $l) => new ListingApproved($l)],
            'listing rejected' => [fn (Listing $l) => new ListingRejected($l)],
            'changes requested' => [fn (Listing $l) => new ListingRejected($l, changesRequested: true)],
            'listing suspended' => [fn (Listing $l) => new ListingSuspended($l)],
            'listing expiring' => [fn (Listing $l) => new ListingExpiringSoon($l)],
            'listing expired' => [fn (Listing $l) => new ListingExpired($l)],
            'account suspended' => [fn () => new AccountStatusChanged(UserStatus::Suspended, 'Spam ads', now()->addDays(7))],
            'account banned' => [fn () => new AccountStatusChanged(UserStatus::Banned, 'Fraud')],
            'account reinstated' => [fn () => new AccountStatusChanged(UserStatus::Active, null)],
            'inactive warning' => [fn () => new InactiveAccountWarning],
            'account deleted' => [fn () => new AccountDeletedNotification],
            'two-factor reset' => [fn () => new TwoFactorResetNotification],
        ];
    }

    #[DataProvider('notifications')]
    public function test_email_renders_translated_text(callable $make): void
    {
        $user = User::factory()->create(['name' => 'Nimal Perera']);
        $listing = Listing::factory()->for($user)->create(['title' => 'Family hatchback', 'moderation_note' => 'Please add photos.']);
        $notification = $make($listing);

        $this->assertContains('mail', $notification->via($user));
        $mail = $notification->toMail($user);
        $html = (string) $mail->render();
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

        $this->assertNotEmpty($mail->subject);
        foreach ([$mail->subject, $text] as $part) {
            // An untranslated key looks like "moderation.mail.approved_line".
            $this->assertDoesNotMatchRegularExpression('/\b(moderation|staff|account|listings|auth|security|retention|privacy|mail|ui)\.[a-z_]+(\.[a-z_]+)*\b/', $part);
            $this->assertStringNotContainsString('{{', $part);
            $this->assertStringNotContainsString(':title', $part);
            $this->assertStringNotContainsString(':reason', $part);
        }
        if ($mail->actionUrl) {
            $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), $mail->actionUrl);
        }
    }
}
