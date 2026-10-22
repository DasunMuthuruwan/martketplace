<?php

namespace Tests\Feature\Boosts;

use App\Enums\BoostOrderStatus;
use App\Enums\BoostType;
use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\BoostOrdersAdmin;
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
use App\Notifications\BoostOrderClosed;
use App\Notifications\BoostOrderPaid;
use App\Notifications\BoostOrderPlaced;
use App\Notifications\TopAdEndingSoon;
use App\Services\Boosts\BoostOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B5 (ADR-026, D3, D4): ordering a promotion, paying by bank transfer, staff confirmation. */
class BoostOrdersTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private Country $lk;

    private User $seller;

    private Listing $ad;

    private User $admin;

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
        foreach ([['bump', null, '1000'], ['top', 7, '4500'], ['top', 15, '8000']] as [$type, $days, $amount]) {
            BoostPrice::create(['boost_product_id' => $this->product($type, $days)->id, 'country_id' => $this->lk->id, 'currency' => 'LKR', 'amount' => $amount]);
        }
        BankTransferAccount::create(['country_id' => $this->lk->id, 'bank_name' => 'Commercial Bank', 'account_name' => 'Marketplace Ltd', 'account_number' => '8001234567', 'is_active' => true]);
        $this->admin = $this->staff(StaffRole::Admin);
    }

    private function product(string $type, ?int $days = null): BoostProduct
    {
        return BoostProduct::where('type', $type)->where('duration_days', $days)->sole();
    }

    private function priceId(string $type, ?int $days = null): int
    {
        return BoostPrice::where('boost_product_id', $this->product($type, $days)->id)->where('country_id', $this->lk->id)->value('id');
    }

    private function order(string $type = 'top', ?int $days = 7): BoostOrder
    {
        return app(BoostOrders::class)->place($this->seller, $this->ad, $this->priceId($type, $days));
    }

    private function orderError(callable $place): string
    {
        try {
            $place();
        } catch (ValidationException $e) {
            return (string) collect($e->errors())->flatten()->first();
        }
        $this->fail('The order was accepted');
    }

    // ---- The seller orders ------------------------------------------------------------------

    public function test_the_promote_page_offers_the_countrys_prices_and_rules(): void
    {
        $this->actingAs($this->seller)->get(route('account.listings.promote', $this->ad))->assertOk()
            ->assertSee('Bump')->assertSee('Top ad · 7 days')->assertSee('4,500')
            ->assertSee(__('boosts.promote.rules')[2]);
        $this->actingAs($this->seller)->get(route('account.listings'))->assertSee(route('account.listings.promote', $this->ad));
    }

    public function test_promote_is_unavailable_while_selling_is_off(): void
    {
        config(['marketplace.boosts.sales_enabled' => false]);

        $this->actingAs($this->seller)->get(route('account.listings.promote', $this->ad))->assertOk()->assertSee(__('boosts.promote.not_open'));
        $this->actingAs($this->seller)->get(route('account.listings'))->assertDontSee(route('account.listings.promote', $this->ad));
        $this->assertSame(__('boosts.order.closed'), $this->orderError(fn () => $this->order()));
    }

    public function test_placing_an_order_copies_the_price_and_sends_the_payment_instructions(): void
    {
        $response = $this->actingAs($this->seller)->post(route('account.listings.promote.store', $this->ad), ['price' => $this->priceId('top', 7)]);

        $order = BoostOrder::sole();
        $response->assertRedirect(route('account.orders.show', $order));
        $this->assertMatchesRegularExpression('/^B[A-HJ-NP-Z2-9]{8}$/', $order->reference);
        $this->assertSame(BoostOrderStatus::Pending, $order->status);
        $this->assertSame(BoostType::Top, $order->product_type);
        $this->assertSame(7, $order->duration_days);
        $this->assertSame('LKR', $order->currency);
        $this->assertTrue($order->expires_at->isSameDay(now()->addDays(7)));

        BoostPrice::where('id', $this->priceId('top', 7))->update(['amount' => '9999']);
        $this->assertEquals(4500, (float) $order->fresh()->amount, 'later price changes do not alter the order');

        Notification::assertSentTo($this->seller, BoostOrderPlaced::class, function (BoostOrderPlaced $n) use ($order) {
            $text = strip_tags((string) $n->toMail($this->seller)->render());
            $this->assertStringContainsString($order->reference, $text);
            $this->assertStringContainsString('8001234567', $text);
            $this->assertStringContainsString('4,500', $text);

            return true;
        });
        $this->actingAs($this->seller)->get(route('account.orders.show', $order))->assertOk()
            ->assertSee($order->reference)->assertSee('8001234567')->assertSee('Commercial Bank');
    }

    public function test_orders_are_refused_when_they_cannot_be_delivered(): void
    {
        // Another country's or an inactive product's price, typed into the form.
        $this->product('bump')->update(['is_active' => false]);
        $this->assertSame(__('boosts.order.unavailable'), $this->orderError(fn () => $this->order('bump', null)));
        $this->assertSame(__('boosts.order.unavailable'), $this->orderError(fn () => app(BoostOrders::class)->place($this->seller, $this->ad, 999999)));

        // A Top ad that would outlast the ad.
        $this->ad->forceFill(['expires_at' => now()->addDays(10)])->save();
        $this->assertSame(__('boosts.order.renew_first'), $this->orderError(fn () => $this->order('top', 15)));

        // An ad that is not live.
        $this->ad->forceFill(['status' => ListingStatus::Sold])->save();
        $this->assertSame(__('boosts.not_live'), $this->orderError(fn () => $this->order()));
        $this->assertSame(0, BoostOrder::count());
    }

    public function test_at_most_three_unpaid_orders(): void
    {
        foreach (range(1, BoostOrders::MAX_PENDING) as $i) {
            $this->order();
        }

        $this->assertSame(__('boosts.order.too_many_pending', ['max' => BoostOrders::MAX_PENDING]), $this->orderError(fn () => $this->order()));
    }

    public function test_only_the_owner_orders_or_sees_an_order(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('account.listings.promote.store', $this->ad), ['price' => $this->priceId('bump')])->assertForbidden();
        $order = $this->order();

        $this->actingAs($stranger)->get(route('account.orders.show', $order))->assertForbidden();
        $this->actingAs($stranger)->post(route('account.orders.cancel', $order))->assertForbidden();
        $this->actingAs($stranger)->get(route('account.orders'))->assertOk()->assertDontSee($order->reference);
    }

    public function test_the_seller_cancels_an_unpaid_order_but_not_a_paid_one(): void
    {
        $order = $this->order();
        $this->actingAs($this->seller)->post(route('account.orders.cancel', $order))->assertRedirect();
        $this->assertSame(BoostOrderStatus::Cancelled, $order->fresh()->status);

        $paid = $this->order();
        app(BoostOrders::class)->markPaid($this->admin, $paid);
        $this->actingAs($this->seller)->post(route('account.orders.cancel', $paid))->assertSessionHasErrors('order');
        $this->assertSame(BoostOrderStatus::Paid, $paid->fresh()->status);
    }

    // ---- Staff confirm, cancel, refund ---------------------------------------------------------

    public function test_marking_paid_starts_the_boost_and_sends_a_receipt(): void
    {
        $order = $this->order();

        Livewire::actingAs($this->admin)->test(BoostOrdersAdmin::class)
            ->assertSee($order->reference)
            ->call('start', $order->id, 'paid')->set('note', 'Statement 21 Oct')
            ->call('confirm')->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(BoostOrderStatus::Paid, $order->status);
        $this->assertSame($this->admin->id, $order->handled_by);
        $boost = $order->boost;
        $this->assertSame(BoostType::Top, $boost->type);
        $this->assertTrue($boost->isRunning());
        $this->assertSame(1, AuditLog::where('action', 'boost_order.paid')->count());
        Notification::assertSentTo($this->seller, BoostOrderPaid::class);
        $this->assertStringContainsString('Toyota Aqua 2015', $this->get('/lk/ads')->getContent());
    }

    public function test_a_bump_order_moves_the_ad_up_when_paid(): void
    {
        $order = $this->order('bump', null);

        app(BoostOrders::class)->markPaid($this->admin, $order);

        $this->assertTrue($this->ad->fresh()->sorted_at->equalTo(now()->startOfSecond()));
        $this->assertSame($order->id, $this->ad->boosts()->sole()->order_id);
    }

    public function test_staff_never_handle_their_own_orders_or_handle_one_twice(): void
    {
        $ownAd = Listing::factory()->inCountry($this->lk)->for($this->admin)->create(['category_id' => $this->ad->category_id, 'expires_at' => now()->addDays(40)]);
        $own = app(BoostOrders::class)->place($this->admin, $ownAd, $this->priceId('bump'));
        $this->assertSame(__('boosts.order.own_order'), $this->orderError(fn () => app(BoostOrders::class)->markPaid($this->admin, $own)));

        $order = $this->order();
        app(BoostOrders::class)->markPaid($this->admin, $order);
        $this->assertStringContainsString(__('boosts.order.status.paid'), $this->orderError(fn () => app(BoostOrders::class)->markPaid($this->admin, $order)));
        $this->assertSame(1, $order->boost()->count());
    }

    public function test_cancel_and_refund_need_a_reason_and_refund_ends_the_boost(): void
    {
        $pending = $this->order();
        $paid = $this->order('bump', null);
        app(BoostOrders::class)->markPaid($this->admin, $paid);
        $top = $this->order();
        $boost = app(BoostOrders::class)->markPaid($this->admin, $top);

        $screen = Livewire::actingAs($this->admin)->test(BoostOrdersAdmin::class);
        $screen->call('start', $pending->id, 'cancel')->call('confirm')->assertHasErrors(['note']);
        $screen->set('note', 'Duplicate order')->call('confirm')->assertHasNoErrors();
        $screen->set('status', 'paid')->call('start', $top->id, 'refund')->set('note', 'We could not show it')->call('confirm')->assertHasNoErrors();

        $this->assertSame(BoostOrderStatus::Cancelled, $pending->fresh()->status);
        $this->assertSame(BoostOrderStatus::Refunded, $top->fresh()->status);
        $this->assertNotNull($boost->fresh()->ended_at);
        Notification::assertSentTo($this->seller, BoostOrderClosed::class, fn (BoostOrderClosed $n) => $n->order->id === $top->id
            && str_contains(strip_tags((string) $n->toMail($this->seller)->render()), 'We could not show it'));
        $this->assertSame(['boost_order.cancelled', 'boost_order.refunded'], AuditLog::whereIn('action', ['boost_order.cancelled', 'boost_order.refunded'])->orderBy('id')->pluck('action')->all());
    }

    // ---- Housekeeping ------------------------------------------------------------------------

    public function test_unpaid_orders_lapse_and_paid_top_ads_send_one_reminder(): void
    {
        $unpaid = $this->order();
        $top = $this->order('top', 7);
        app(BoostOrders::class)->markPaid($this->admin, $top);

        $this->travel(156)->hours(); // 6.5 days: the Top ad ends within a day
        $this->artisan('marketplace:end-boosts')->expectsOutputToContain('Ending reminders sent: 1')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('marketplace:end-boosts')->expectsOutputToContain('Unpaid orders expired: 1')->expectsOutputToContain('Ending reminders sent: 0')->assertSuccessful();

        $this->assertSame(BoostOrderStatus::Expired, $unpaid->fresh()->status);
        Notification::assertSentTo($this->seller, BoostOrderClosed::class, fn (BoostOrderClosed $n) => $n->order->id === $unpaid->id);
        Notification::assertSentToTimes($this->seller, TopAdEndingSoon::class, 1);
    }

    /** @return array<string, array{0: string}> */
    public static function mails(): array
    {
        return ['placed' => ['placed'], 'paid' => ['paid'], 'cancelled' => ['cancelled'], 'refunded' => ['refunded'], 'ending' => ['ending']];
    }

    #[DataProvider('mails')]
    public function test_every_order_email_renders_translated_with_links_on_app_url(string $which): void
    {
        $order = $this->order();
        $boost = null;
        if ($which !== 'placed' && $which !== 'cancelled') {
            $boost = app(BoostOrders::class)->markPaid($this->admin, $order);
        }
        $notification = match ($which) {
            'placed' => new BoostOrderPlaced($order),
            'paid' => new BoostOrderPaid($order->fresh(), $boost),
            'cancelled' => tap(new BoostOrderClosed($order), fn () => app(BoostOrders::class)->cancel($this->admin, $order, 'Duplicate')),
            'refunded' => tap(new BoostOrderClosed($order), fn () => app(BoostOrders::class)->refund($this->admin, $order, 'Not shown')),
            'ending' => new TopAdEndingSoon($boost),
        };
        $order->refresh();

        $mail = $notification->toMail($this->seller);
        $text = strip_tags((string) $mail->render());

        $this->assertDoesNotMatchRegularExpression('/\bboosts\.[a-z_]+/', $text.$mail->subject);
        $this->assertStringNotContainsString(':reference', $text.$mail->subject);
        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), (string) $mail->actionUrl);
    }
}
