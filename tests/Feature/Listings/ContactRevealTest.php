<?php

namespace Tests\Feature\Listings;

use App\Enums\ContactVisibility;
use App\Enums\ListingStatus;
use App\Models\ContactReveal;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Contact\ContactResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * POST /listings/{id}/contact (T4.5, routes doc §4.1, MSG-01–07): 200, 401, 403, 404, 428, 429.
 */
class ContactRevealTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'phone_country_code' => '94']);
        $this->seller = User::factory()->create(['phone' => '+94771234567', 'phone_country' => 'LK']);
        $this->visibility(ContactVisibility::Public);
        $this->listing = Listing::factory()->for($this->seller)->inCountry($lk)->create(['title' => 'Toyota Aqua 2015 Hybrid']);
    }

    private function visibility(ContactVisibility $visibility): void
    {
        $this->seller->forceFill(['contact_visibility' => $visibility])->save();
    }

    private function reveal(string $channel = 'call', array $extra = [], ?Listing $listing = null): TestResponse
    {
        return $this->postJson(route('listings.contact', $listing ?? $this->listing), ['channel' => $channel] + $extra);
    }

    // ---- Allowed reveals --------------------------------------------------------------------

    public function test_guest_reveals_a_public_number_and_it_is_logged_without_the_raw_ip(): void
    {
        $this->reveal()
            ->assertOk()
            ->assertExactJson(['channel' => 'call', 'phone' => '+94771234567', 'display' => '077 123 4567'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $reveal = ContactReveal::sole();
        $this->assertSame(['call', null, $this->listing->id], [$reveal->channel, $reveal->user_id, $reveal->listing_id]);
        $this->assertSame(AuditLogger::hashIp('127.0.0.1'), $reveal->ip_hash);
    }

    public function test_numbers_from_another_country_are_shown_internationally(): void
    {
        $this->seller->forceFill(['phone' => '+447400123456', 'phone_country' => 'GB'])->save();

        $this->reveal()->assertOk()->assertJsonPath('display', '+44 7400 123456');
    }

    public function test_whatsapp_link_uses_the_e164_digits_and_a_prefilled_message(): void
    {
        $this->seller->forceFill(['whatsapp_phone' => '+447400123456'])->save();

        $url = $this->reveal('whatsapp')->assertOk()->json('url');

        $this->assertStringStartsWith('https://wa.me/447400123456?text=', $url);
        $text = rawurldecode(substr($url, strpos($url, '=') + 1));
        $this->assertStringContainsString('"Toyota Aqua 2015 Hybrid"', $text);
        $this->assertStringContainsString(url('/ad/toyota-aqua-2015-hybrid-'.$this->listing->id), $text);
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), url('/ad/x-1'), 'Link built from APP_URL');
    }

    public function test_whatsapp_falls_back_to_the_phone_number(): void
    {
        $this->assertStringStartsWith('https://wa.me/94771234567?text=', $this->reveal('whatsapp')->json('url'));
    }

    // ---- Visibility (401 / 403) -------------------------------------------------------------

    public function test_registered_visibility_asks_guests_to_log_in(): void
    {
        $this->visibility(ContactVisibility::Registered);

        $this->reveal()->assertUnauthorized()->assertExactJson(['message' => 'login_required']);
        $this->actingAs(User::factory()->create())->reveal()->assertOk();
        $this->assertSame(1, ContactReveal::count());
    }

    public function test_unavailable_channels_are_refused(): void
    {
        $this->visibility(ContactVisibility::Hidden);
        $this->reveal()->assertForbidden()->assertExactJson(['message' => 'unavailable']);

        $this->visibility(ContactVisibility::Public);
        $this->listing->forceFill(['show_call' => false])->save();
        $this->reveal()->assertForbidden();
        $this->reveal('whatsapp')->assertOk();

        config(['marketplace.contact.whatsapp_enabled' => false]);
        $this->reveal('whatsapp')->assertForbidden();

        $this->listing->forceFill(['show_call' => true])->save();
        $this->seller->forceFill(['phone' => null, 'phone_country' => null])->save();
        $this->reveal()->assertForbidden();
        $this->assertSame(1, ContactReveal::count());
    }

    public function test_only_public_listings_reveal_anything(): void
    {
        foreach ([ListingStatus::Draft, ListingStatus::PendingReview, ListingStatus::Sold, ListingStatus::Suspended] as $status) {
            $this->listing->forceFill(['status' => $status])->save();
            $this->reveal()->assertNotFound();
        }

        $this->listing->forceFill(['status' => ListingStatus::Active, 'expires_at' => now()->subMinute()])->save();
        $this->reveal()->assertNotFound();

        $this->listing->forceFill(['expires_at' => now()->addDay()])->save();
        $this->listing->delete();
        $this->reveal()->assertNotFound();

        $this->postJson('/listings/abc/contact', ['channel' => 'call'])->assertNotFound();
        $this->assertSame(0, ContactReveal::count());
    }

    public function test_invalid_channel(): void
    {
        $this->reveal('sms')->assertUnprocessable()->assertJsonValidationErrors('channel');
    }

    public function test_the_sellers_own_reveals_are_not_logged(): void
    {
        $this->actingAs($this->seller)->reveal()->assertOk();

        $this->assertSame(0, ContactReveal::count());
    }

    // ---- Abuse protection (429 / 428) -------------------------------------------------------

    public function test_guests_are_rate_limited_per_hour(): void
    {
        config(['marketplace.contact.reveals_per_hour_guest' => 3]);

        foreach (range(1, 3) as $attempt) {
            $this->reveal()->assertOk();
        }
        $this->reveal()->assertTooManyRequests()->assertHeader('Retry-After');

        $this->travel(61)->minutes();
        $this->reveal()->assertOk();
    }

    public function test_logged_in_users_have_their_own_higher_limit(): void
    {
        config(['marketplace.contact.reveals_per_hour_guest' => 1, 'marketplace.contact.reveals_per_hour_user' => 3]);
        $buyer = User::factory()->create();

        $this->reveal()->assertOk();
        $this->reveal()->assertTooManyRequests();
        foreach (range(1, 3) as $attempt) {
            $this->actingAs($buyer)->reveal()->assertOk();
        }
        $this->actingAs($buyer)->reveal()->assertTooManyRequests();
    }

    public function test_guests_over_the_threshold_must_pass_turnstile(): void
    {
        config([
            'marketplace.turnstile.site_key' => 'site', 'marketplace.turnstile.secret_key' => 'secret',
            'marketplace.contact.guest_challenge_after' => 2,
        ]);
        Http::fake(fn (ClientRequest $request) => Http::response(['success' => ($request->data()['response'] ?? null) === 'good']));

        $this->reveal()->assertOk();
        $this->reveal()->assertOk();
        $this->reveal()->assertStatus(428)->assertExactJson(['message' => 'challenge_required']);
        $this->reveal('call', ['turnstile_token' => 'bad'])->assertStatus(428);
        $this->reveal('call', ['turnstile_token' => 'good'])->assertOk();

        // Logged-in buyers are never challenged.
        $this->actingAs(User::factory()->create())->reveal()->assertOk();
    }

    // ---- Page buttons and retention ---------------------------------------------------------

    public function test_buttons_carry_decisions_but_never_numbers(): void
    {
        $this->visibility(ContactVisibility::Registered);
        $buttons = app(ContactResolver::class)->buttons($this->listing->fresh(), null);

        $this->assertSame(['call' => 'login_required', 'whatsapp' => 'login_required'], $buttons);
        $this->assertStringNotContainsString('771234567', json_encode($buttons));
    }

    public function test_reveal_logs_are_pruned_after_the_retention_period(): void
    {
        $this->listing->forceFill(['expires_at' => now()->addDays(120)])->save();
        $this->reveal()->assertOk();
        $this->travel(91)->days();
        $this->reveal()->assertOk();

        $this->artisan('model:prune', ['--model' => [ContactReveal::class]])->assertSuccessful();

        $this->assertSame(1, ContactReveal::count());
    }
}
