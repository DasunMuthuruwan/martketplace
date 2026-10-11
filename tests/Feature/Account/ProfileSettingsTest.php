<?php

namespace Tests\Feature\Account;

use App\Enums\ContactVisibility;
use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private Country $gb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'name' => ['en' => 'Sri Lanka'], 'phone_country_code' => '94']);
        $this->gb = Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => ['en' => 'United Kingdom'], 'phone_country_code' => '44']);
        Notification::fake();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email' => 'nimal@example.com',
            'country_id' => $this->lk->id,
            'phone' => '+94771234567',
            'phone_country' => 'LK',
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'country_id' => $user->country_id,
            'phone_country' => $user->phone_country,
            'phone' => $user->phone,
        ], $overrides);
    }

    private function save(User $user, array $overrides = []): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('account.settings.profile'))
            ->put(route('user-profile-information.update'), $this->payload($user, $overrides));
    }

    public function test_settings_pages_need_login(): void
    {
        $this->get(route('account.settings.profile'))->assertRedirect(route('login'));
        $this->get(route('account.settings.security'))->assertRedirect(route('login'));
        $this->get(route('account.settings.contact'))->assertRedirect(route('login'));
        $this->put(route('user-profile-information.update'))->assertRedirect(route('login'));
    }

    public function test_profile_page_shows_current_values(): void
    {
        $user = $this->user(['name' => 'Nimal Perera']);

        $this->actingAs($user)->get(route('account.settings.profile'))
            ->assertOk()
            ->assertSee('Nimal Perera')
            ->assertSee('nimal@example.com')
            ->assertSee('+94771234567');
    }

    public function test_unverified_users_can_still_reach_profile_and_security(): void
    {
        $user = $this->user(['email_verified_at' => null]);

        $this->actingAs($user)->get(route('account.settings.profile'))->assertOk();
        $this->actingAs($user)->get(route('account.settings.security'))->assertOk();
        $this->actingAs($user)->get(route('account.settings.contact'))->assertRedirect(route('verification.notice'));
    }

    public function test_name_country_and_phone_can_be_updated(): void
    {
        $user = $this->user(['phone_verified_at' => now()]);

        $this->save($user, [
            'name' => '  Nimal P.  ',
            'country_id' => $this->gb->id,
            'phone_country' => 'gb',
            'phone' => '07400 123456',
        ])->assertRedirect(route('account.settings.profile'))->assertSessionHas('status', 'profile-information-updated');

        $user->refresh();
        $this->assertSame('Nimal P.', $user->name);
        $this->assertSame($this->gb->id, $user->country_id);
        $this->assertSame('+447400123456', $user->phone);
        $this->assertSame('GB', $user->phone_country);
        $this->assertNull($user->phone_verified_at, 'A new number is no longer verified');
        $this->assertNotNull($user->email_verified_at, 'Email unchanged, still verified');
        Notification::assertNothingSent();
    }

    public function test_unchanged_phone_keeps_its_verification(): void
    {
        $user = $this->user(['phone_verified_at' => now()]);

        $this->save($user, ['name' => 'New Name', 'phone' => '077 123 4567'])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_phone_is_validated_for_its_country(): void
    {
        $user = $this->user();

        $this->save($user, ['phone_country' => 'GB', 'phone' => '077 123 4567'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'phone');
        $this->save($user, ['phone_country' => '', 'phone' => '077 123 4567'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'phone_country');

        $this->assertSame('+94771234567', $user->fresh()->phone);
    }

    public function test_removing_the_phone_hides_contact_details_when_no_number_is_left(): void
    {
        $user = $this->user();
        $user->forceFill(['contact_visibility' => ContactVisibility::Public])->save();

        $this->save($user, ['phone' => '', 'phone_country' => ''])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->phone);
        $this->assertNull($user->phone_country);
        $this->assertSame(ContactVisibility::Hidden, $user->contact_visibility);
    }

    public function test_removing_the_phone_keeps_visibility_when_a_whatsapp_number_remains(): void
    {
        $user = $this->user();
        $user->forceFill(['contact_visibility' => ContactVisibility::Registered, 'whatsapp_phone' => '+447400123456'])->save();

        $this->save($user, ['phone' => '', 'phone_country' => ''])->assertSessionHasNoErrors();

        $this->assertSame(ContactVisibility::Registered, $user->fresh()->contact_visibility);
    }

    public function test_changing_email_needs_the_current_password(): void
    {
        $user = $this->user();

        $this->save($user, ['email' => 'new@example.com'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');
        $this->save($user, ['email' => 'new@example.com', 'current_password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'current_password');

        $this->assertSame('nimal@example.com', $user->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_changing_email_requires_verifying_the_new_address(): void
    {
        $user = $this->user();

        $this->save($user, ['email' => 'New@Example.com', 'current_password' => 'password'])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_same_email_in_different_case_is_not_a_change(): void
    {
        $user = $this->user();

        $this->save($user, ['email' => 'NIMAL@example.com'])->assertSessionHasNoErrors();

        $this->assertNotNull($user->fresh()->email_verified_at);
        Notification::assertNothingSent();
    }

    public function test_email_must_be_unique_including_deleted_accounts(): void
    {
        $user = $this->user();
        User::factory()->create(['email' => 'taken@example.com']);
        User::factory()->create(['email' => 'deleted@example.com'])->delete();

        $this->save($user, ['email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'email');
        $this->save($user, ['email' => 'deleted@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'email');
    }

    public function test_country_must_be_active_unless_it_is_already_set(): void
    {
        $inactive = Country::factory()->inactive()->create();
        $user = $this->user();

        $this->save($user, ['country_id' => $inactive->id])
            ->assertSessionHasErrorsIn('updateProfileInformation', 'country_id');

        // A country deactivated after sign-up does not block other edits.
        $this->lk->update(['is_active' => false]);
        $this->save($user, ['name' => 'Still Here'])->assertSessionHasNoErrors();
        $this->assertSame('Still Here', $user->fresh()->name);
    }

    public function test_protected_fields_cannot_be_changed_through_the_profile_form(): void
    {
        $user = $this->user();

        $this->save($user, [
            'status' => 'active',
            'contact_visibility' => 'public',
            'legal_hold' => '1',
            'email_verified_at' => null,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(ContactVisibility::Hidden, $user->contact_visibility);
        $this->assertFalse($user->legal_hold);
        $this->assertNotNull($user->email_verified_at);
    }
}
