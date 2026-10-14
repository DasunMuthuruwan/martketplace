<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Retention §1: no sign-in for years; the account is deleted unless the person signs in. */
class InactiveAccountWarning extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.inactive.mail_subject'))
            ->line(__('account.inactive.mail_line', ['days' => config('marketplace.retention.deleted_account_grace')]))
            ->action(__('account.inactive.mail_action'), route('login'))
            ->line(__('account.inactive.mail_ignore'));
    }
}
