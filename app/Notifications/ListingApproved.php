<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** MOD-05: the seller's ad is live. Queued (architecture §6); the in-app channel arrives with notifications (M7). */
class ListingApproved extends Notification implements ShouldQueue
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
            ->subject(__('moderation.mail.approved_subject'))
            ->line(__('moderation.mail.approved_line', ['title' => $this->listing->title]))
            ->line(__('moderation.mail.expires', ['date' => $this->listing->expires_at?->toFormattedDayDateString()]));
    }
}
