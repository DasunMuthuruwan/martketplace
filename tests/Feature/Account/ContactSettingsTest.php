<?php

namespace Tests\Feature\Account;

use App\Enums\ContactVisibility;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ContactSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Country::factory()->create(['iso2' => 'LK', 'iso3' => 'LKA', 'name' => ['en' => 'Sri Lanka'], 'phone_country_code' => '94']);
        Country::factory()->create(['iso2' => 'GB', 'iso3' => 'GBR', 'name' => ['en' => 'United Kingdom'], 'phone_country_code' => '44']);
    }

    private function seller(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['phone' => '+94771234567', 'phone_country' => 'LK'], $attributes));
    }

    private function save(User $user, array $input): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('account.settings.contact'))
            ->put(route('account.settings.contact.update'), $input);
    }

    public function test_new_accounts_are_hidden_by_default(): void
    {
        $this->assertSame(ContactVisibility::Hidden, $this->seller()->fresh()->contact_visibility);
    }

    public function test_page_shows_the_current_choice(): void
    {
        $user = $this->seller(['whatsapp_phone' => '+447400123456']);

        $this->actingAs($user)->get(route('account.settings.contact'))
            ->assertOk()
            ->assertSee('value="hidden" checked', false)
            ->assertSee('+447400123456')
            ->assertSee('value="GB" selected', false);
    }

    public function test_each_visibility_can_be_chosen(): void
    {
        $user = $this->seller();

        foreach (ContactVisibility::cases() as $visibility) {
            $this->save($user, ['contact_visibility' => $visibility->value])
                ->assertRedirect(route('account.settings.contact'))
                ->assertSessionHasNoErrors();
            $this->assertSame($visibility, $user->fresh()->contact_visibility);
        }
    }

    public function test_unknown_visibility_is_rejected(): void
    {
        $this->save($this->seller(), ['contact_visibility' => 'everyone-and-their-dog'])
            ->assertSessionHasErrorsIn('updateContactSettings', 'contact_visibility');
    }

    public function test_a_separate_whatsapp_number_is_stored_as_e164(): void
    {
        $user = $this->seller();

        $this->save($user, ['contact_visibility' => 'registered', 'whatsapp_country' => 'gb', 'whatsapp_phone' => '07400 123456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+447400123456', $user->fresh()->whatsapp_phone);
    }

    public function test_whatsapp_number_is_validated_for_its_country(): void
    {
        $user = $this->seller();

        $this->save($user, ['contact_visibility' => 'public', 'whatsapp_country' => 'GB', 'whatsapp_phone' => '077 123 4567'])
            ->assertSessionHasErrorsIn('updateContactSettings', 'whatsapp_phone');
        $this->save($user, ['contact_visibility' => 'public', 'whatsapp_phone' => '077 123 4567'])
            ->assertSessionHasErrorsIn('updateContactSettings', 'whatsapp_country');

        $this->assertNull($user->fresh()->whatsapp_phone);
        $this->assertSame(ContactVisibility::Hidden, $user->fresh()->contact_visibility);
    }

    public function test_whatsapp_equal_to_the_phone_or_empty_means_use_the_phone(): void
    {
        $user = $this->seller(['whatsapp_phone' => '+447400123456']);

        $this->save($user, ['contact_visibility' => 'public', 'whatsapp_country' => 'LK', 'whatsapp_phone' => '0771234567'])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->whatsapp_phone);

        $user->forceFill(['whatsapp_phone' => '+447400123456'])->save();
        $this->save($user, ['contact_visibility' => 'public', 'whatsapp_country' => '', 'whatsapp_phone' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->whatsapp_phone);
    }

    public function test_showing_numbers_needs_at_least_one_number(): void
    {
        $user = $this->seller(['phone' => null, 'phone_country' => null]);

        $this->save($user, ['contact_visibility' => 'public'])
            ->assertSessionHasErrorsIn('updateContactSettings', 'contact_visibility');
        $this->assertSame(ContactVisibility::Hidden, $user->fresh()->contact_visibility);

        // Hidden is always allowed; a WhatsApp-only seller may show it.
        $this->save($user, ['contact_visibility' => 'hidden'])->assertSessionHasNoErrors();
        $this->save($user, ['contact_visibility' => 'public', 'whatsapp_country' => 'GB', 'whatsapp_phone' => '07400 123456'])
            ->assertSessionHasNoErrors();
        $this->assertSame(ContactVisibility::Public, $user->fresh()->contact_visibility);
    }

    public function test_contact_settings_need_a_verified_email(): void
    {
        $user = $this->seller(['email_verified_at' => null]);

        $this->save($user, ['contact_visibility' => 'public'])->assertRedirect(route('verification.notice'));
        $this->assertSame(ContactVisibility::Hidden, $user->fresh()->contact_visibility);
    }
}
