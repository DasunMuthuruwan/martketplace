<?php

namespace Tests\Feature\Auth;

use App\Enums\ContactVisibility;
use App\Enums\UserStatus;
use App\Models\Country;
use App\Models\User;
use App\Rules\HumanTiming;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'name' => ['en' => 'Sri Lanka'], 'phone_country_code' => '94']);
        Notification::fake();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        $token = HumanTiming::token();
        $this->travel(5)->seconds(); // a human takes a few seconds to fill the form

        return array_merge([
            'name' => 'Nimal Perera',
            'email' => 'Nimal@Example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'country_id' => $this->lk->id,
            'phone_country' => 'LK',
            'phone' => '077 123 4567',
            'terms' => '1',
            'website' => '',
            HumanTiming::FIELD => $token,
        ], $overrides);
    }

    public function test_registration_page_lists_active_countries_only(): void
    {
        // No calling code, so it is not listed as a phone country either (that list includes inactive countries).
        Country::factory()->inactive()->create(['name' => ['en' => 'Hiddenland'], 'phone_country_code' => null]);

        $this->get('/register')->assertOk()->assertSee('Sri Lanka')->assertDontSee('Hiddenland');
    }

    public function test_registration_is_closed_when_no_country_is_active(): void
    {
        $this->lk->update(['is_active' => false]);

        $this->get('/register')->assertOk()->assertSee(__('auth.ui.no_countries'));
    }

    public function test_new_user_is_created_with_normalised_data_and_must_verify_email(): void
    {
        $this->post('/register', $this->payload())->assertRedirect('/');

        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('nimal@example.com', $user->email);
        $this->assertSame('+94771234567', $user->phone);
        $this->assertSame('LK', $user->phone_country);
        $this->assertSame($this->lk->id, $user->country_id);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(ContactVisibility::Hidden, $user->contact_visibility, 'Phone hidden until the seller chooses');
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_phone_number_is_optional(): void
    {
        $this->post('/register', $this->payload(['phone' => '', 'phone_country' => '']))->assertRedirect('/');

        $this->assertNull(User::firstOrFail()->phone);
    }

    public function test_phone_from_another_country_is_validated_for_that_country(): void
    {
        Country::factory()->inactive()->create(['iso2' => 'GB', 'iso3' => 'GBR', 'phone_country_code' => '44']);

        $this->post('/register', $this->payload(['phone_country' => 'GB', 'phone' => '07400 123456']))->assertRedirect('/');

        $this->assertSame('+447400123456', User::firstOrFail()->phone);
    }

    public function test_invalid_phone_for_the_country_is_rejected(): void
    {
        $this->post('/register', $this->payload(['phone' => '12345']))->assertSessionHasErrors('phone');
        $this->post('/register', $this->payload(['phone_country' => '']))->assertSessionHasErrors('phone_country');

        $this->assertGuest();
    }

    public function test_inactive_country_is_rejected(): void
    {
        $inactive = Country::factory()->inactive()->create();

        $this->post('/register', $this->payload(['country_id' => $inactive->id]))->assertSessionHasErrors('country_id');
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->post('/register', $this->payload(['terms' => null]))->assertSessionHasErrors('terms');
    }

    public function test_short_password_is_rejected(): void
    {
        $this->post('/register', $this->payload(['password' => 'short-pw1', 'password_confirmation' => 'short-pw1']))
            ->assertSessionHasErrors('password');
    }

    public function test_email_uniqueness_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'nimal@example.com']);

        $this->post('/register', $this->payload(['email' => 'NIMAL@EXAMPLE.COM']))->assertSessionHasErrors('email');
    }

    public function test_honeypot_field_blocks_bots(): void
    {
        $this->post('/register', $this->payload(['website' => 'https://spam.example']))->assertSessionHasErrors('website');
        $this->assertSame(0, User::count());
    }

    public function test_instant_submission_is_rejected(): void
    {
        $payload = $this->payload();
        $payload[HumanTiming::FIELD] = HumanTiming::token(); // minted after the delay: submitted instantly

        $this->post('/register', $payload)->assertSessionHasErrors(HumanTiming::FIELD);
    }

    public function test_tampered_or_missing_form_token_is_rejected(): void
    {
        $this->post('/register', $this->payload([HumanTiming::FIELD => 'forged']))->assertSessionHasErrors(HumanTiming::FIELD);
        $this->post('/register', $this->payload([HumanTiming::FIELD => null]))->assertSessionHasErrors(HumanTiming::FIELD);
    }

    public function test_old_form_token_is_rejected(): void
    {
        $token = HumanTiming::token();
        $this->travel(2)->days();

        $this->post('/register', $this->payload([HumanTiming::FIELD => $token]))->assertSessionHasErrors(HumanTiming::FIELD);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', ['name' => 'x'])->assertSessionHasErrors();
        }

        $this->post('/register', ['name' => 'x'])->assertStatus(429);
    }

    private function enableTurnstile(): void
    {
        config(['marketplace.turnstile.site_key' => 'site-key', 'marketplace.turnstile.secret_key' => 'secret-key']);
    }

    public function test_turnstile_success_allows_registration(): void
    {
        $this->enableTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->post('/register', $this->payload(['cf-turnstile-response' => 'token']))->assertRedirect('/');

        Http::assertSent(fn ($request) => $request['secret'] === 'secret-key' && $request['response'] === 'token');
        $this->assertSame(1, User::count());
    }

    public function test_turnstile_failure_blocks_registration(): void
    {
        $this->enableTurnstile();
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $this->post('/register', $this->payload(['cf-turnstile-response' => 'bad']))->assertSessionHasErrors('cf-turnstile-response');
        $this->assertSame(0, User::count());
    }

    public function test_missing_turnstile_token_is_rejected_when_enabled(): void
    {
        $this->enableTurnstile();
        Http::fake();

        $this->post('/register', $this->payload())->assertSessionHasErrors(['cf-turnstile-response' => __('security.turnstile_failed')]);
        Http::assertNothingSent();
        $this->assertSame(0, User::count());
    }

    public function test_turnstile_outage_fails_closed_for_registration(): void
    {
        $this->enableTurnstile();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->post('/register', $this->payload(['cf-turnstile-response' => 'token']))
            ->assertSessionHasErrors(['cf-turnstile-response' => __('security.turnstile_unavailable')]);
        $this->assertSame(0, User::count());
    }

    public function test_turnstile_widget_and_csp_are_present_only_when_configured(): void
    {
        $this->get('/register')->assertDontSee('cf-turnstile', false);

        $this->enableTurnstile();
        config(['marketplace.csp.script' => ['https://challenges.cloudflare.com'], 'marketplace.csp.frame' => ['https://challenges.cloudflare.com']]);

        $response = $this->get('/register')->assertSee('cf-turnstile', false);
        $this->assertStringContainsString('frame-src https://challenges.cloudflare.com', (string) $response->headers->get('Content-Security-Policy'));
    }
}
