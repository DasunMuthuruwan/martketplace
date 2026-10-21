<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesMailBudget;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * S-05: tells the account holder that a security setting changed (password changed or reset,
 * email changed, two-factor turned off, new recovery codes), so a takeover does not go unnoticed.
 * An email change goes to the old address. No passwords, codes or links that act on the account:
 * only the forgot-password page, which anyone can open.
 */
class SecurityChange extends Notification implements ShouldQueue
{
    use Queueable, UsesMailBudget;

    public const PASSWORD_CHANGED = 'password_changed';

    public const PASSWORD_RESET = 'password_reset';

    public const EMAIL_CHANGED = 'email_changed';

    public const TWO_FACTOR_DISABLED = 'two_factor_disabled';

    public const RECOVERY_CODES = 'recovery_codes';

    public readonly Carbon $at;

    /** @param  string|null  $newEmail  for an email change: the new address, shown masked */
    public function __construct(public readonly string $kind, public readonly ?string $newEmail = null)
    {
        $this->at = now();
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = 'account.security_mail.'.$this->kind;

        return (new MailMessage)
            ->subject(__($key.'_subject'))
            ->line(__($key.'_line', [
                'date' => $this->at->toDayDateTimeString().' ('.config('app.timezone').')',
                'email' => $this->newEmail !== null ? self::mask($this->newEmail) : '',
            ]))
            ->line(__('account.security_mail.if_you'))
            ->line(__('account.security_mail.if_not'))
            ->action(__('account.security_mail.reset'), route('password.request'));
    }

    /** "nimal.perera@example.com" → "n***@example.com": enough to recognise, not to harvest. */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
