<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Security alert: sent immediately (not queued) when a user's 2FA is reset by staff or the CLI. */
class TwoFactorResetNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('staff.reset_mail.subject'))
            ->line(__('staff.reset_mail.line'))
            ->line(__('staff.reset_mail.warning'));
    }
}
