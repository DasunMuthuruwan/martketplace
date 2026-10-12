<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** MOD-05 / LST-08 seller email. Queued, sent after the change is committed. */
class ListingExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Listing $listing)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('moderation.mail.expiring_subject'))
            ->line(__('moderation.mail.expiring_line', [
                'title' => $this->listing->title,
                'date' => $this->listing->expires_at?->toFormattedDayDateString(),
                'reason' => $this->listing->moderation_note,
            ]));
    }
}
