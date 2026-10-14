<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** MOD-05: the ad was rejected, or changes were requested, with the moderator's reason. Queued. */
class ListingRejected extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public function __construct(public readonly Listing $listing, public readonly bool $changesRequested = false)
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
        $kind = $this->changesRequested ? 'changes' : 'rejected';

        return (new MailMessage)
            ->subject(__("moderation.mail.{$kind}_subject"))
            ->line(__("moderation.mail.{$kind}_line", ['title' => $this->listing->title]))
            ->line(__('moderation.mail.reason', ['reason' => $this->listing->moderation_note]))
            ->line(__('moderation.mail.resubmit'));
    }
}
