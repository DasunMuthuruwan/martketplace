<?php

namespace Tests\Feature\Boosts;

use App\Authorization\Permissions;
use App\Enums\StaffRole;
use App\Livewire\Admin\BoostPricesAdmin;
use App\Livewire\Admin\SettingsAdmin;
use App\Models\AuditLog;
use App\Models\BankTransferAccount;
use App\Models\BoostPrice;
use App\Models\BoostProduct;
use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use App\Services\Boosts\BoostCatalogue;
use App\Support\Decimal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B4 (ADR-026, D1–D3, D6): products, prices and bank details per country, and the sales switch. */
class BoostCatalogueTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $admin;

    private Country $lk;

    private Country $jp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->staff(StaffRole::Admin);
        $lkr = Currency::factory()->create(['code' => 'LKR', 'minor_units' => 2]);
        $jpy = Currency::factory()->create(['code' => 'JPY', 'minor_units' => 0]);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true, 'default_currency' => $lkr->code]);
        $this->jp = Country::factory()->create(['iso2' => 'JP', 'is_active' => true, 'default_currency' => $jpy->code]);
    }

    private function product(string $type, ?int $days = null): BoostProduct
    {
        return BoostProduct::where('type', $type)->where('duration_days', $days)->sole();
    }

    private function screen(Country $country): Testable
    {
        return Livewire::actingAs($this->admin)->test(BoostPricesAdmin::class)->set('countryId', (string) $country->id);
    }

    public function test_the_first_products_exist_without_prices(): void
    {
        $this->assertSame(['Bump', 'Top ad · 3 days', 'Top ad · 7 days', 'Top ad · 15 days'], BoostProduct::orderBy('sort_order')->get()->map->name()->all());
        $this->assertSame(0, BoostPrice::count());
    }

    public function test_prices_are_set_per_country_in_its_currency_and_audited(): void
    {
        $this->screen($this->lk)
            ->set('prices.'.$this->product('bump')->id, '1,500.50')
            ->set('prices.'.$this->product('top', 7)->id, '4500')
            ->call('savePrices')->assertHasNoErrors();

        $prices = BoostPrice::where('country_id', $this->lk->id)->get()->keyBy('boost_product_id');
        $this->assertSame('1500.5', Decimal::trim((string) $prices[$this->product('bump')->id]->amount));
        $this->assertSame('LKR', $prices[$this->product('top', 7)->id]->currency);
        $this->assertCount(2, $prices);
        $this->assertSame(0, BoostPrice::where('country_id', $this->jp->id)->count(), 'other countries untouched');
        $this->assertSame(1, AuditLog::where('action', 'boosts.prices_changed')->count());
    }

    public function test_an_emptied_price_stops_the_offer(): void
    {
        $bump = $this->product('bump');
        $this->screen($this->lk)->set('prices.'.$bump->id, '1000')->call('savePrices');

        $this->screen($this->lk)->assertSet('prices.'.$bump->id, '1000')->set('prices.'.$bump->id, '')->call('savePrices');

        $this->assertSame(0, BoostPrice::count());
    }

    public function test_amounts_follow_the_currency(): void
    {
        $bump = $this->product('bump')->id;
        foreach (['abc', '-5', '0'] as $bad) {
            $this->screen($this->lk)->set('prices.'.$bump, $bad)->call('savePrices')->assertHasErrors(['prices.'.$bump]);
        }
        $this->screen($this->lk)->set('prices.'.$bump, '10.555')->call('savePrices')->assertHasErrors(['prices.'.$bump]);
        $this->screen($this->jp)->set('prices.'.$bump, '500.5')->call('savePrices')->assertHasErrors(['prices.'.$bump]);
        $this->screen($this->jp)->set('prices.'.$bump, '500')->call('savePrices')->assertHasNoErrors();

        $this->assertSame(1, BoostPrice::count());
    }

    public function test_bank_details_are_saved_per_country_without_the_number_in_the_audit_log(): void
    {
        $this->screen($this->lk)
            ->set('bank.bank_name', 'Commercial Bank')->set('bank.account_name', 'Marketplace (Pvt) Ltd')
            ->set('bank.account_number', '1234 5678 90')->set('bank.is_active', true)
            ->call('saveBank')->assertHasNoErrors();

        $account = BankTransferAccount::sole();
        $this->assertSame($this->lk->id, $account->country_id);
        $this->assertTrue($account->is_active);
        $this->assertSame($this->admin->id, $account->updated_by);
        $log = AuditLog::where('action', 'boosts.bank_account_changed')->sole();
        $this->assertStringNotContainsString('1234', (string) json_encode($log->changes));

        $this->screen($this->lk)->set('bank.account_number', '<script>')->call('saveBank')->assertHasErrors(['bank.account_number']);
        $this->screen($this->lk)->set('bank.bank_name', '')->call('saveBank')->assertHasErrors(['bank.bank_name']);
    }

    public function test_products_can_be_switched_off_everywhere(): void
    {
        $top = $this->product('top', 3);

        $this->screen($this->lk)->call('toggleProduct', $top->id);

        $this->assertFalse($top->fresh()->is_active);
        $this->assertSame(1, AuditLog::where('action', 'boosts.product_disabled')->count());
    }

    public function test_selling_opens_only_with_the_switch_a_bank_account_and_a_price(): void
    {
        $catalogue = app(BoostCatalogue::class);
        $this->assertFalse($catalogue->salesEnabled(), 'off by default (D6)');

        BoostPrice::create(['boost_product_id' => $this->product('top', 7)->id, 'country_id' => $this->lk->id, 'currency' => 'LKR', 'amount' => '4500']);
        BankTransferAccount::create(['country_id' => $this->lk->id, 'bank_name' => 'B', 'account_name' => 'A', 'account_number' => '1', 'is_active' => true]);
        $this->assertFalse($catalogue->isOpen($this->lk));

        config(['marketplace.boosts.sales_enabled' => true]);
        $this->assertTrue($catalogue->isOpen($this->lk));
        $this->assertFalse($catalogue->isOpen($this->jp), 'no price or bank account there');

        $this->product('top', 7)->update(['is_active' => false]);
        $this->assertFalse($catalogue->isOpen($this->lk), 'its only product is off');
    }

    public function test_offers_list_active_priced_products_in_order(): void
    {
        foreach ([['top', 15, '9000'], ['bump', null, '1000'], ['top', 3, '2500']] as [$type, $days, $amount]) {
            BoostPrice::create(['boost_product_id' => $this->product($type, $days)->id, 'country_id' => $this->lk->id, 'currency' => 'LKR', 'amount' => $amount]);
        }
        $this->product('top', 3)->update(['is_active' => false]);

        $offers = app(BoostCatalogue::class)->offers($this->lk);

        $this->assertSame(['Bump', 'Top ad · 15 days'], $offers->map(fn ($p) => $p->product->name())->all());
    }

    public function test_only_the_owner_switches_selling_on_in_settings(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        $this->withSession(['auth.password_confirmed_at' => time()]);

        Livewire::actingAs($owner)->test(SettingsAdmin::class)->set('values.boost_sales_enabled', true)->call('save')->assertHasNoErrors();

        $this->assertTrue(app(BoostCatalogue::class)->salesEnabled());
        $this->assertFalse($this->admin->can(Permissions::SETTINGS_MANAGE));
    }

    public function test_moderators_cannot_open_the_prices_screen(): void
    {
        $this->actingAs($this->staff(StaffRole::Moderator, twoFactor: false))->get(route('admin.boost-prices'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.boost-prices'))->assertOk()->assertSee(__('admin.boost_prices.sales_off'));
    }
}
