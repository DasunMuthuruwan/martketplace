<?php

namespace App\Notifications;

use App\Models\BoostOrder;
use App\Notifications\Concerns\UsesMailBudget;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** B5 (ADR-026, D3): the order and how to pay it by bank transfer, with its reference. Queued. */
class BoostOrderPlaced extends Notification implements ShouldQueue
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
        $order = $this->order->loadMissing(['listing', 'country', 'currencyModel']);
        $amount = (string) app(Money::class)->forCountry($order->amount, $order->currencyModel, $order->country, true);
        $bank = app(BoostCatalogue::class)->bankAccount($order->country);

        $mail = (new MailMessage)
            ->subject(__('boosts.mail.placed_subject', ['reference' => $order->reference]))
            ->line(__('boosts.mail.placed_line', ['product' => $order->productName(), 'title' => $order->listing?->title]))
            ->line(__('boosts.mail.amount', ['amount' => $amount]))
            ->line(__('boosts.mail.reference', ['reference' => $order->reference]));
        if ($bank !== null) {
            $mail->line(__('boosts.mail.bank', ['bank' => $bank->bank_name.($bank->branch ? ', '.$bank->branch : '')]))
                ->line(__('boosts.mail.account', ['name' => $bank->account_name, 'number' => $bank->account_number]));
            if ($bank->swift_code) {
                $mail->line(__('boosts.mail.swift', ['swift' => $bank->swift_code]));
            }
            if ($bank->instructions) {
                $mail->line($bank->instructions);
            }
        }

        return $mail->line(__('boosts.mail.pay_by', ['date' => $order->expires_at?->toFormattedDayDateString()]))
            ->action(__('boosts.mail.view_order'), route('account.orders.show', $order));
    }
}
