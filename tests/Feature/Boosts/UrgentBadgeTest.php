<?php

namespace Tests\Feature\Boosts;

use App\Enums\BoostOrderStatus;
use App\Enums\BoostType;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\BoostsAdmin;
use App\Models\AuditLog;
use App\Models\BankTransferAccount;
use App\Models\BoostOrder;
use App\Models\BoostPrice;
use App\Models\BoostProduct;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\User;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Boosts\BoostManager;
use App\Services\Boosts\BoostOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B9: the paid Urgent badge, sold per country like the other promotions. */
class UrgentBadgeTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Country $lk;

    private User $seller;

    private Listing $ad;

    private User $admin;

    private string $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        config(['marketplace.boosts.sales_enabled' => true]);
        $lkr = Currency::factory()->create(['code' => 'LKR', 'minor_units' => 2]);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true, 'default_currency' => $lkr->code, 'number_locale' => 'en']);
        $cars = Category::factory()->create(['parent_id' => Category::factory()->create(['slug' => 'vehicles'])->id, 'slug' => 'cars']);
        $this->seller = User::factory()->create();
        $this->ad = Listing::factory()->inCountry($this->lk)->for($this->seller)->create(['category_id' => $cars->id, 'title' => 'Toyota Aqua 2015', 'expires_at' => now()->addDays(40)]);
        $this->category = route('ads.category', ['cc' => 'lk', 'category' => 'vehicles', 'subcategory' => 'cars']);
        BankTransferAccount::create(['country_id' => $this->lk->id, 'bank_name' => 'Commercial Bank', 'account_name' => 'Marketplace Ltd', 'account_number' => '8001234567', 'is_active' => true]);
        $this->admin = $this->staff(StaffRole::Admin);
    }

    private function product(): BoostProduct
    {
        return BoostProduct::where('type', 'urgent')->sole();
    }

    private function price(): BoostPrice
    {
        return BoostPrice::create(['boost_product_id' => $this->product()->id, 'country_id' => $this->lk->id, 'currency' => 'LKR', 'amount' => '2500']);
    }

    private function until(): ?string
    {
        return DB::table('listings')->where('id', $this->ad->id)->value('urgent_until');
    }

    public function test_the_product_exists_for_seven_days_but_is_not_on_sale_without_a_price(): void
    {
        $this->assertSame(7, $this->product()->duration_days);
        $this->assertSame('Urgent · 7 days', $this->product()->name());
        $this->assertFalse(app(BoostCatalogue::class)->isOpen($this->lk));

        $this->price();
        $this->assertTrue(app(BoostCatalogue::class)->isOpen($this->lk));
        $this->actingAs($this->seller)->get(route('account.listings.promote', $this->ad))
            ->assertOk()->assertSee('Urgent · 7 days')->assertSee(__('boosts.product_help.urgent'));
    }

    public function test_a_paid_order_shows_the_badge_on_card_and_ad_page_and_hides_promote(): void
    {
        $order = app(BoostOrders::class)->place($this->seller, $this->ad, $this->price()->id);
        $this->assertSame(BoostType::Urgent, $order->product_type);
        $this->assertSame('Urgent · 7 days', $order->productName());
        $this->get($this->category)->assertDontSee('data-urgent-badge', false);

        $boost = app(BoostOrders::class)->markPaid($this->admin, $order);

        $this->assertSame(BoostOrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(BoostType::Urgent, $boost->type);
        $this->assertTrue($boost->ends_at->equalTo(now()->addDays(7)) || $boost->ends_at->diffInSeconds(now()->addDays(7), true) < 2);
        $this->get($this->category)->assertSee('data-urgent-badge', false)->assertSee(__('boosts.urgent.badge'));
        $this->get($this->ad->publicUrl())->assertSee('data-urgent-badge', false)->assertSee(__('boosts.urgent.note'));

        $until = $boost->ends_at->toFormattedDateString();
        $this->actingAs($this->seller)->get(route('account.listings'))
            ->assertSee(__('account.listings.urgent_until', ['date' => $until]))
            ->assertDontSee(route('account.listings.promote', $this->ad), false);
        $this->actingAs($this->seller)->get(route('account.listings.promote', $this->ad))
            ->assertRedirect(route('account.listings'))->assertSessionHas('error', __('boosts.already_promoted', ['date' => $until]));
    }

    public function test_the_badge_does_not_count_as_an_edit_or_change_the_order_of_results(): void
    {
        $updated = $this->ad->updated_at;
        $sorted = $this->ad->fresh()->sorted_at;
        $this->travel(1)->hours();

        app(BoostManager::class)->urgent($this->ad, 7);

        $fresh = $this->ad->fresh();
        $this->assertTrue($fresh->updated_at->equalTo($updated));
        $this->assertTrue($fresh->sorted_at->equalTo($sorted));
    }

    public function test_the_badge_stops_at_its_end_without_any_job(): void
    {
        $this->price();
        $boost = app(BoostManager::class)->urgent($this->ad, 7);
        $this->travelTo($boost->ends_at->addMinute());
        $this->ad->forceFill(['expires_at' => now()->addDays(40)])->save();

        $this->assertFalse($this->ad->fresh()->isUrgent());
        $this->get($this->category)->assertDontSee('data-urgent-badge', false);
        $this->actingAs($this->seller)->get(route('account.listings'))->assertSee(route('account.listings.promote', $this->ad), false);

        app(BoostManager::class)->endWhereAdNotLive();
        $this->assertNull($this->until());
    }

    public function test_a_refund_removes_the_badge_and_promote_comes_back(): void
    {
        $order = app(BoostOrders::class)->place($this->seller, $this->ad, $this->price()->id);
        app(BoostOrders::class)->markPaid($this->admin, $order);

        app(BoostOrders::class)->refund($this->admin, $order, 'Not shown');

        $this->assertNull($this->until());
        $this->get($this->category)->assertDontSee('data-urgent-badge', false);
        $this->actingAs($this->seller)->get(route('account.listings'))->assertSee(route('account.listings.promote', $this->ad), false);
    }

    public function test_the_badge_ends_with_the_ad_and_does_not_come_back(): void
    {
        app(BoostManager::class)->urgent($this->ad, 7);
        $this->ad->forceFill(['status' => ListingStatus::Sold])->save();
        $this->assertFalse($this->ad->fresh()->isUrgent());

        $this->assertSame(1, app(BoostManager::class)->endWhereAdNotLive());
        $this->assertNull($this->until());

        $this->ad->forceFill(['status' => ListingStatus::Active])->save();
        $this->assertFalse($this->ad->fresh()->isUrgent());
    }

    public function test_a_second_urgent_badge_queues_after_the_first_and_never_outlasts_the_ad(): void
    {
        $first = app(BoostManager::class)->urgent($this->ad, 7);
        $second = app(BoostManager::class)->urgent($this->ad, 7);
        $this->assertTrue($second->starts_at->equalTo($first->ends_at));
        $this->assertSame($second->ends_at->format('Y-m-d H:i:s'), $this->ad->fresh()->urgent_until->format('Y-m-d H:i:s'));

        // Ending the later one leaves the first in charge.
        app(BoostManager::class)->end($second);
        $this->assertSame($first->ends_at->format('Y-m-d H:i:s'), $this->ad->fresh()->urgent_until->format('Y-m-d H:i:s'));

        $this->ad->forceFill(['expires_at' => now()->addDays(3)])->save();
        $this->expectException(ValidationException::class);
        app(BoostManager::class)->urgent($this->ad->fresh(), 7);
    }

    public function test_an_order_is_refused_when_the_ad_expires_before_the_badge_would_end(): void
    {
        $price = $this->price();
        $this->ad->forceFill(['expires_at' => now()->addDays(10)])->save();

        try {
            app(BoostOrders::class)->place($this->seller, $this->ad->fresh(), $price->id);
            $this->fail('The order should be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(__('boosts.order.renew_first'), $e->errors()['price'][0]);
        }
        $this->assertSame(0, BoostOrder::count());
    }

    public function test_staff_can_grant_it_and_the_grant_is_audited(): void
    {
        Livewire::actingAs($this->admin)->test(BoostsAdmin::class)
            ->set('ad', (string) $this->ad->id)->set('type', 'urgent')->set('days', 5)
            ->call('grant')->assertHasNoErrors();

        $this->assertTrue($this->ad->fresh()->isUrgent());
        $this->assertSame(1, AuditLog::where('action', 'boost.granted')->count());
    }
}
