<?php

namespace Tests\Feature\Security;

use App\Models\Country;
use App\Models\User;
use App\Notifications\SecurityChange;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * S-05: every change to how an account is secured sends the holder a notice, so a takeover does
 * not go unnoticed; an email change is announced to the old address.
 */
class SecurityChangeNoticesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function sentTo(User $user, string $kind): void
    {
        Notification::assertSentTo($user, SecurityChange::class, fn (SecurityChange $n) => $n->kind === $kind);
    }

    public function test_a_password_change_is_announced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('account.settings.security'))->put(route('user-password.update'), [
            'current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasNoErrors();

        $this->sentTo($user, SecurityChange::PASSWORD_CHANGED);
    }

    public function test_a_failed_password_change_sends_nothing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('account.settings.security'))->put(route('user-password.update'), [
            'current_password' => 'wrong-password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'a-brand-new-passphrase',
        ]);

        Notification::assertNotSentTo($user, SecurityChange::class);
    }

    public function test_a_password_reset_is_announced(): void
    {
        $user = User::factory()->create(['email' => 'seller@example.com']);
        $this->post('/forgot-password', ['email' => 'seller@example.com']);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token, 'email' => $user->email,
                'password' => 'a-brand-new-password', 'password_confirmation' => 'a-brand-new-password',
            ])->assertSessionHasNoErrors();

            return true;
        });
        $this->sentTo($user, SecurityChange::PASSWORD_RESET);
    }

    public function test_an_email_change_is_announced_to_the_old_address(): void
    {
        $lk = Country::factory()->create(['iso2' => 'LK', 'phone_country_code' => '94']);
        $user = User::factory()->create(['email' => 'nimal@example.com', 'country_id' => $lk->id]);

        $this->actingAs($user)->from(route('account.settings.profile'))->put(route('user-profile-information.update'), [
            'name' => $user->name, 'email' => 'attacker@example.net', 'country_id' => $lk->id, 'current_password' => 'password',
        ])->assertSessionHasNoErrors();

        Notification::assertSentOnDemand(SecurityChange::class, function (SecurityChange $n, array $channels, AnonymousNotifiable $notifiable) {
            return $n->kind === SecurityChange::EMAIL_CHANGED
                && $notifiable->routes['mail'] === 'nimal@example.com'
                && $n->newEmail === 'attacker@example.net';
        });
        Notification::assertNotSentTo($user->fresh(), SecurityChange::class);
    }

    public function test_a_profile_save_without_an_email_change_sends_nothing(): void
    {
        $lk = Country::factory()->create(['iso2' => 'LK', 'phone_country_code' => '94']);
        $user = User::factory()->create(['email' => 'nimal@example.com', 'country_id' => $lk->id]);

        $this->actingAs($user)->from(route('account.settings.profile'))->put(route('user-profile-information.update'), [
            'name' => 'New Name', 'email' => 'Nimal@Example.com', 'country_id' => $lk->id,
        ])->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_turning_off_two_factor_and_new_recovery_codes_are_announced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->post('/user/two-factor-authentication');
        $this->post('/user/two-factor-recovery-codes')->assertRedirect();
        $this->sentTo($user, SecurityChange::RECOVERY_CODES);

        $this->delete('/user/two-factor-authentication')->assertRedirect();

        $this->sentTo($user, SecurityChange::TWO_FACTOR_DISABLED);
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    /** @return array<string, array{0: string}> */
    public static function kinds(): array
    {
        return array_combine(
            $kinds = [SecurityChange::PASSWORD_CHANGED, SecurityChange::PASSWORD_RESET, SecurityChange::EMAIL_CHANGED, SecurityChange::TWO_FACTOR_DISABLED, SecurityChange::RECOVERY_CODES],
            array_map(fn ($k) => [$k], $kinds),
        );
    }

    #[DataProvider('kinds')]
    public function test_each_notice_renders_with_its_own_text_and_no_secrets(string $kind): void
    {
        $user = User::factory()->create(['name' => 'Nimal Perera']);
        $mail = (new SecurityChange($kind, 'nimal.perera@example.net'))->toMail($user);
        $html = (string) $mail->render();
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));

        $this->assertSame(__("account.security_mail.{$kind}_subject"), $mail->subject);
        $this->assertStringNotContainsString('account.security_mail', $text, 'untranslated key');
        $this->assertStringNotContainsString(':date', $text);
        $this->assertStringContainsString(__('account.security_mail.if_not'), $text);
        $this->assertSame(route('password.request'), $mail->actionUrl, 'the only link: the public forgot-password page');
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), (string) $mail->actionUrl);
        if ($kind === SecurityChange::EMAIL_CHANGED) {
            $this->assertStringContainsString('n***@example.net', $text);
            $this->assertStringNotContainsString('nimal.perera@example.net', $text);
        }
    }

    public function test_masking_keeps_the_domain_and_one_letter(): void
    {
        $this->assertSame('n***@example.com', SecurityChange::mask('nimal.perera@example.com'));
        $this->assertSame('a***@x.lk', SecurityChange::mask('a@x.lk'));
    }
}
