<?php

namespace App\Notifications;

use App\Enums\BoostType;
use App\Models\BoostOrder;
use App\Models\ListingBoost;
use App\Notifications\Concerns\UsesMailBudget;
use App\Services\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** B5: receipt for a confirmed bank transfer; the boost has started. Queued. */
class BoostOrderPaid extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public function __construct(public readonly BoostOrder $order, public readonly ListingBoost $boost)
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
        $order = $this->order->loadMissing(['listing', 'country', 'currencyModel']);
        $amount = (string) app(Money::class)->forCountry($order->amount, $order->currencyModel, $order->country, true);
        $period = $this->boost->type === BoostType::Bump
            ? __('boosts.mail.bumped', ['date' => $this->boost->starts_at->toFormattedDayDateString()])
            : __('boosts.mail.top_period', ['start' => $this->boost->starts_at->toFormattedDayDateString(), 'end' => $this->boost->ends_at?->toFormattedDayDateString()]);

        $mail = (new MailMessage)
            ->subject(__('boosts.mail.paid_subject'))
            ->line(__('boosts.mail.paid_line', ['amount' => $amount, 'reference' => $order->reference]))
            ->line(__('boosts.mail.receipt', ['product' => $order->productName(), 'title' => $order->listing?->title, 'date' => $order->paid_at?->toFormattedDayDateString()]))
            ->line($period);
        if ($order->listing !== null) {
            $mail->action(__('boosts.mail.see_stats'), route('account.listings.stats', $order->listing));
        }

        return $mail;
    }
}
