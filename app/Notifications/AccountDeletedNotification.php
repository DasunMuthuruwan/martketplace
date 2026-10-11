<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmation that the account was deleted (retention §2 step 2); sent immediately, not queued. */
class AccountDeletedNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.delete.mail_subject'))
            ->line(__('account.delete.mail_line'))
            ->line(__('account.delete.mail_restore', ['days' => config('marketplace.retention.deleted_account_grace')]))
            ->line(__('account.delete.mail_warning'));
    }
}
