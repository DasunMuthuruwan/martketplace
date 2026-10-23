<?php

namespace App\Notifications;

use App\Models\SellerReview;
use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** G5: a buyer rated the seller; the seller can read it and reply once. Queued. */
class SellerReviewed extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public function __construct(public readonly SellerReview $review)
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
            ->subject(__('reviews.mail.subject'))
            ->line(trans_choice('reviews.mail.line', $this->review->rating, ['stars' => $this->review->rating]))
            ->line(__('reviews.mail.reply'))
            ->action(__('reviews.mail.action'), route('account.reviews'));
    }
}
