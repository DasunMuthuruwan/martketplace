<?php

namespace Tests\Feature\Security;

use App\Enums\StaffRole;
use App\Livewire\Admin\BoostOrdersAdmin;
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
use App\Notifications\BoostOrderPlaced;
use App\Services\Boosts\BoostOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * B6 (ADR-026): text staff type for promotions (bank instructions, payment and refund notes) is shown
 * to sellers on pages and in emails, so it is escaped everywhere; amounts never come from the browser.
 */
class BoostSecurityTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const PAYLOAD = '<script>xss(1)</script><img src=x onerror=xss(2)>';

    private User $seller;

    private Listing $ad;

    private int $priceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        config(['marketplace.boosts.sales_enabled' => true]);
        $currency = Currency::factory()->create(['minor_units' => 2]);
        $country = Country::factory()->create(['is_active' => true, 'default_currency' => $currency->code]);
        $this->seller = User::factory()->create();
        $this->ad = Listing::factory()->inCountry($country)->for($this->seller)->create([
            'category_id' => Category::factory()->create(['parent_id' => Category::factory()->create()->id])->id, 'expires_at' => now()->addDays(40),
        ]);
        $this->priceId = BoostPrice::create(['boost_product_id' => BoostProduct::where('type', 'bump')->value('id'), 'country_id' => $country->id, 'currency' => $currency->code, 'amount' => '1000'])->id;
        BankTransferAccount::create(['country_id' => $country->id, 'bank_name' => 'Bank '.self::PAYLOAD, 'account_name' => 'Name', 'account_number' => '123', 'instructions' => 'Pay '.self::PAYLOAD, 'is_active' => true]);
    }

    private function assertEscaped(string $html, string $where): void
    {
        $this->assertStringNotContainsString('<script>xss', $html, $where);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*onerror=xss/i', $html, $where);
    }

    public function test_bank_details_are_escaped_on_the_order_page_and_in_the_email(): void
    {
        $order = app(BoostOrders::class)->place($this->seller, $this->ad, $this->priceId);

        $html = $this->actingAs($this->seller)->get(route('account.orders.show', $order))->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;xss(1)', $html, 'the text is shown, escaped');
        $this->assertEscaped($html, 'order page');
        Notification::assertSentTo($this->seller, BoostOrderPlaced::class, function (BoostOrderPlaced $n) {
            $this->assertEscaped((string) $n->toMail($this->seller)->render(), 'order email');

            return true;
        });
    }

    public function test_staff_notes_are_escaped_in_the_admin_list_and_the_sellers_email(): void
    {
        $admin = $this->staff(StaffRole::Admin);
        $order = app(BoostOrders::class)->place($this->seller, $this->ad, $this->priceId);
        app(BoostOrders::class)->cancel($admin, $order, 'Reason '.self::PAYLOAD);

        $html = Livewire::actingAs($admin)->test(BoostOrdersAdmin::class)->set('status', 'cancelled')->html();
        $this->assertStringContainsString('&lt;script&gt;xss(1)', $html);
        $this->assertEscaped($html, 'admin orders');
        Notification::assertSentTo($this->seller, BoostOrderClosed::class, function (BoostOrderClosed $n) {
            $this->assertEscaped((string) $n->toMail($this->seller)->render(), 'closed email');

            return true;
        });
    }

    public function test_the_amount_always_comes_from_the_price_table(): void
    {
        $this->actingAs($this->seller)->post(route('account.listings.promote.store', $this->ad), [
            'price' => $this->priceId, 'amount' => '1', 'currency' => 'XXX', 'status' => 'paid', 'user_id' => 1,
        ])->assertRedirect();

        $order = BoostOrder::sole();
        $this->assertEquals(1000, (float) $order->amount);
        $this->assertSame('pending', $order->status->value);
        $this->assertSame($this->seller->id, $order->user_id);
    }
}
