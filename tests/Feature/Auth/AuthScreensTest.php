<?php

namespace Tests\Feature\Auth;

use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** F3.1: sign-in screens use the auth layout and the form components (layout doc §3.7). */
class AuthScreensTest extends TestCase
{
    use RefreshDatabase;

    private function assertAuthScreen(string $html): void
    {
        $this->assertSame(1, substr_count($html, '<h1'), 'one h1');
        $this->assertStringContainsString('<main id="main"', $html);
        $this->assertStringNotContainsString('data-bottom-nav', $html);
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_guest_screens_render_with_labelled_fields(): void
    {
        Country::factory()->create();

        foreach ([route('login'), route('register'), route('password.request'), route('password.reset', ['token' => 'abc', 'email' => 'a@example.com'])] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertAuthScreen($html);
            $this->assertMatchesRegularExpression('/<label\s+for="f-email"/', $html);
            $this->assertStringContainsString('id="f-email"', $html);
        }
    }

    public function test_login_password_has_a_show_toggle_and_failed_login_links_the_error(): void
    {
        $html = $this->get(route('login'))->getContent();
        $this->assertStringContainsString('data-password-toggle', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);

        $this->from(route('login'))->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong-password'])->assertRedirect(route('login'));
        $after = $this->get(route('login'))->getContent();

        $this->assertStringContainsString('aria-invalid="true"', $after);
        $this->assertStringContainsString('aria-describedby="f-email-error"', $after);
        $this->assertStringContainsString('id="f-email-error"', $after);
        $this->assertStringContainsString('value="nobody@example.com"', $after);
    }

    public function test_register_shows_the_error_summary_country_select_and_phone_combobox(): void
    {
        $country = Country::factory()->create(['name' => ['en' => 'Testland']]);

        $html = $this->get(route('register'))->getContent();
        $this->assertStringContainsString('<option value="'.$country->id.'"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('data-combobox-value', $html);
        $this->assertStringContainsString('name="phone_country"', $html);
        $this->assertStringContainsString('name="terms"', $html);

        $this->from(route('register'))->post(route('register.store'), ['name' => '', 'email' => 'bad', 'phone_country' => 'XX']);
        $after = $this->get(route('register'))->getContent();
        $this->assertStringContainsString('data-error-summary', $after);
        $this->assertStringContainsString('href="#f-email"', $after);
    }

    public function test_verify_email_and_confirm_password_screens(): void
    {
        $user = User::factory()->unverified()->create();

        $verify = $this->actingAs($user)->get(route('verification.notice'))->assertOk()->getContent();
        $this->assertAuthScreen($verify);
        $this->assertStringContainsString('action="'.route('verification.send').'"', $verify);
        $this->assertStringContainsString('action="'.route('logout').'"', $verify);

        $confirm = $this->actingAs(User::factory()->create())->get(route('password.confirm'))->assertOk()->getContent();
        $this->assertAuthScreen($confirm);
        $this->assertStringContainsString('id="f-password"', $confirm);
    }

    public function test_two_factor_challenge_has_a_code_field_and_recovery_option(): void
    {
        $user = User::factory()->create();
        $html = $this->withSession(['login.id' => $user->id, 'login.remember' => false])->get(route('two-factor.login'))->assertOk()->getContent();

        $this->assertAuthScreen($html);
        $this->assertStringContainsString('autocomplete="one-time-code"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('name="recovery_code"', $html);
    }
}
