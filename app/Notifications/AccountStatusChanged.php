<?php

namespace App\Notifications;

use App\Enums\UserStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** MOD-05: the account was suspended, banned or reinstated, with the reason. Queued after commit. */
class AccountStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly UserStatus $status,
        public readonly ?string $reason,
        public readonly ?Carbon $until = null,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'staff.accounts.mail.'.$this->status->value;
        $mail = (new MailMessage)->subject(__($key.'_subject'))->line(__($key.'_line'));

        if ($this->status === UserStatus::Suspended && $this->until !== null) {
            $mail->line(__('staff.accounts.mail.suspended_until', ['date' => $this->until->toFormattedDayDateString()]));
        }
        if ($this->reason !== null && $this->reason !== '') {
            $mail->line(__('staff.accounts.mail.reason', ['reason' => $this->reason]));
        }

        return $mail;
    }
}
