<?php

namespace App\Notifications;

use App\Models\BoostOrder;
use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** B5: an order was cancelled by staff, expired unpaid, or refunded (the boost ended). Queued. */
class BoostOrderClosed extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public function __construct(public readonly BoostOrder $order)
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
        $order = $this->order->loadMissing('listing');
        $status = $order->status->value;
        $mail = (new MailMessage)
            ->subject(__("boosts.mail.{$status}_subject", ['reference' => $order->reference]))
            ->line(__("boosts.mail.{$status}_line", ['product' => $order->productName(), 'title' => $order->listing?->title, 'reference' => $order->reference]));
        if ($order->note !== null && $order->note !== '') {
            $mail->line(__('boosts.mail.reason', ['reason' => $order->note]));
        }

        return $mail->action(__('boosts.mail.view_order'), route('account.orders.show', $order));
    }
}
