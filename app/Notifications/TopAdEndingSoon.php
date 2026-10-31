<?php

namespace App\Notifications;

use App\Models\ListingBoost;
use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** B5: a paid Top ad ends within a day; the seller can promote again. Queued, sent once. */
class TopAdEndingSoon extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public function __construct(public readonly ListingBoost $boost)
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
        $listing = $this->boost->listing;

        return (new MailMessage)
            ->subject(__('boosts.mail.ending_subject'))
            ->line(__('boosts.mail.ending_line', ['title' => $listing?->title, 'date' => $this->boost->ends_at?->toDayDateTimeString()]))
            ->line(__('boosts.mail.ending_next'))
            ->action(__('boosts.mail.view_ads'), route('account.listings'));
    }
}
