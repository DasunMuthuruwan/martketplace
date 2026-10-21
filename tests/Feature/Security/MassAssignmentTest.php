<?php

namespace Tests\Feature\Security;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\Region;
use App\Models\User;
use App\Rules\HumanTiming;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * S8.3: mass assignment. Forms take an allow-list of fields (`$fillable`, `$request->only()`), so
 * privileged columns sent along with a normal form (status, owner, verification, roles, moderation)
 * are ignored.
 */
class MassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private const PRIVILEGED_USER = [
        'status' => 'active', 'email_verified_at' => '2020-01-01 00:00:00', 'roles' => ['owner'], 'role' => 'owner',
        'is_staff' => '1', 'legal_hold' => '1', 'two_factor_confirmed_at' => '2020-01-01 00:00:00', 'id' => 1,
        'status_reason' => 'x', 'last_login_at' => '2020-01-01 00:00:00',
    ];

    public function test_registration_ignores_privileged_fields(): void
    {
        Notification::fake();
        $lk = Country::factory()->create(['iso2' => 'LK', 'phone_country_code' => '94']);
        $token = HumanTiming::token();
        $this->travel(5)->seconds();

        $this->post('/register', self::PRIVILEGED_USER + [
            'name' => 'Nimal Perera', 'email' => 'nimal@example.com', 'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery', 'country_id' => $lk->id, 'phone_country' => 'LK',
            'phone' => '077 123 4567', 'terms' => '1', 'website' => '', HumanTiming::FIELD => $token,
        ])->assertRedirect();

        $user = User::where('email', 'nimal@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame([], $user->getRoleNames()->all());
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertFalse((bool) $user->legal_hold);
        $this->assertNull($user->status_reason);
    }

    public function test_posting_steps_ignore_privileged_listing_fields(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->create();
        $currency = Currency::factory()->create();
        $country = Country::factory()->create(['default_currency' => $currency->code, 'region_levels' => 1]);
        $elsewhere = Country::factory()->create();
        $cars = Category::factory()->create(['parent_id' => Category::factory()->create()->id, 'allowed_types' => ['sale']]);
        $region = Region::factory()->create(['country_id' => $country->id]);
        $city = City::factory()->inRegion($region)->create();
        $draft = Listing::factory()->inCountry($country)->for($seller)->draft()->create(['category_id' => $cars->id, 'region_id' => $region->id, 'city_id' => $city->id]);
        $privileged = [
            'user_id' => $other->id, 'status' => ListingStatus::Active->value, 'published_at' => '2020-01-01 00:00:00',
            'expires_at' => '2099-01-01 00:00:00', 'flagged' => '0', 'moderated_by' => $other->id, 'moderation_note' => 'ok',
            'country_id' => $elsewhere->id, 'currency' => 'XXX', 'cover_image_id' => 999, 'views_count' => 99999, 'id' => 999,
        ];

        $this->actingAs($seller)->post(route('listings.update', [$draft, 'details']), $privileged + [
            'title' => 'Toyota Aqua 2015 hybrid', 'description' => 'One owner, full service history, very clean.', 'type' => 'sale', 'condition' => 'used',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($seller)->post(route('listings.update', [$draft, 'price']), $privileged + ['price_type' => 'fixed', 'price' => '100'])->assertRedirect();

        $draft->refresh();
        $this->assertSame('Toyota Aqua 2015 hybrid', $draft->title, 'the form itself was saved');
        $this->assertSame($seller->id, $draft->user_id);
        $this->assertSame(ListingStatus::Draft, $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertNull($draft->moderated_by);
        $this->assertNull($draft->moderation_note);
        $this->assertSame($country->id, $draft->country_id);
        $this->assertSame($currency->code, $draft->currency);
        $this->assertNull($draft->cover_image_id);
    }
}
