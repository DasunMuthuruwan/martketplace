<?php

namespace Tests\Feature\Boosts;

use App\Enums\BoostOrderStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\BoostOrdersAdmin;
use App\Livewire\Admin\BoostPricesAdmin;
use App\Models\AuditLog;
use App\Models\BankTransferAccount;
use App\Models\BoostOrder;
use App\Models\BoostPrice;
use App\Models\BoostProduct;
use App\Models\Category;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Listing;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Boosts\BoostCatalogue;
use App\Services\Boosts\BoostOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** B7 (owner 2026-10-24): sellers attach proof of payment to unpaid orders; staff see it in Orders. */
class PaymentProofsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    public const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private Country $lk;

    private User $seller;

    private BoostOrder $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        Storage::fake('local');
        config(['marketplace.boosts.sales_enabled' => true]);
        $currency = Currency::factory()->create(['code' => 'LKR', 'minor_units' => 2]);
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true, 'default_currency' => $currency->code, 'number_locale' => 'en']);
        $this->seller = User::factory()->create();
        $ad = Listing::factory()->inCountry($this->lk)->for($this->seller)->create([
            'category_id' => Category::factory()->create(['parent_id' => Category::factory()->create()->id])->id, 'expires_at' => now()->addDays(40),
        ]);
        $price = BoostPrice::create(['boost_product_id' => BoostProduct::where('type', 'bump')->sole()->id, 'country_id' => $this->lk->id, 'currency' => 'LKR', 'amount' => '1000']);
        BankTransferAccount::create(['country_id' => $this->lk->id, 'bank_name' => 'Test Bank', 'account_name' => 'Marketplace', 'account_number' => '0001112223', 'is_active' => true]);
        $this->order = app(BoostOrders::class)->place($this->seller, $ad, $price->id);
    }

    private function upload(UploadedFile $file, array $extra = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->seller)->post(route('account.orders.proofs.store', $this->order), ['proof' => $file] + $extra);
    }

    public static function pdf(string $name = 'slip.pdf', string $content = self::PDF): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ---- Seller ---------------------------------------------------------------------------------

    public function test_the_seller_attaches_an_image_and_a_pdf_with_the_transfer_details(): void
    {
        $this->actingAs($this->seller)->get(route('account.orders.show', $this->order))->assertOk()->assertSee('data-proof-form', false);

        $this->upload(UploadedFile::fake()->image('Bank slip.png', 1200, 800), ['transfer_date' => now()->toDateString(), 'transfer_note' => 'TXN 55821'])
            ->assertRedirect(route('account.orders.show', $this->order))->assertSessionHas('status', __('boosts.proof.saved'));
        $this->upload(self::pdf());

        $proofs = $this->order->proofs()->get();
        $this->assertSame([PaymentProof::IMAGE, PaymentProof::PDF], $proofs->pluck('kind')->all());
        $this->assertSame('Bank slip.jpg', $proofs[0]->original_name, 'images are stored as JPEG');
        Storage::disk('local')->assertExists($proofs->pluck('path')->all());
        $this->assertStringStartsWith('payment-proofs/'.$this->order->id.'/', $proofs[0]->path);

        $order = $this->order->fresh();
        $this->assertNotNull($order->proof_at);
        $this->assertSame([now()->toDateString(), 'TXN 55821'], [$order->transfer_date->toDateString(), $order->transfer_note]);
        $this->assertSame(['boost_order.proof_added', 'boost_order.proof_added'], AuditLog::where('action', 'like', 'boost_order.proof%')->pluck('action')->all());

        $page = $this->actingAs($this->seller)->get(route('account.orders.show', $this->order))->assertSee('Bank slip.jpg')->assertSee('slip.pdf')->assertSee('TXN 55821');
        $page->assertSee(__('boosts.proof.count', ['count' => 2, 'max' => 3]));
    }

    public function test_images_are_re_encoded_without_their_original_bytes(): void
    {
        $file = UploadedFile::fake()->image('slip.jpg', 3000, 2000);
        $original = (string) file_get_contents($file->getRealPath());

        $this->upload($file);

        $stored = Storage::disk('local')->get(PaymentProof::sole()->path);
        $this->assertNotSame($original, $stored);
        [$width, $height, $type] = getimagesizefromstring((string) $stored);
        $this->assertSame([2400, 1600, IMAGETYPE_JPEG], [$width, $height, $type], 'scaled to the longest edge, JPEG');
    }

    public function test_at_most_three_files_per_order_and_removing_one_frees_a_place(): void
    {
        foreach (range(1, 3) as $i) {
            $this->upload(UploadedFile::fake()->image("slip{$i}.jpg", 600, 400));
        }
        $this->upload(UploadedFile::fake()->image('slip4.jpg', 600, 400))->assertSessionHasErrors('proof', null, 'proof');
        $this->assertSame(3, PaymentProof::count());
        $this->actingAs($this->seller)->get(route('account.orders.show', $this->order))->assertDontSee('data-proof-form', false);

        $first = PaymentProof::orderBy('id')->first();
        $this->actingAs($this->seller)->delete(route('account.orders.proofs.destroy', [$this->order, $first]))->assertSessionHas('status', __('boosts.proof.removed'));
        Storage::disk('local')->assertMissing($first->path);
        $this->upload(UploadedFile::fake()->image('slip4.jpg', 600, 400))->assertSessionHasNoErrors();
        $this->assertSame(3, PaymentProof::count());
    }

    public function test_removing_the_last_proof_clears_the_proof_mark(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400));
        $this->actingAs($this->seller)->delete(route('account.orders.proofs.destroy', [$this->order, PaymentProof::sole()]));

        $this->assertNull($this->order->fresh()->proof_at);
    }

    public function test_proof_is_locked_once_the_order_is_no_longer_unpaid(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400));
        $proof = PaymentProof::sole();
        $this->order->forceFill(['status' => BoostOrderStatus::Cancelled, 'closed_at' => now()])->save();

        $this->upload(UploadedFile::fake()->image('late.jpg', 600, 400))->assertSessionHasErrors('proof', null, 'proof');
        $this->actingAs($this->seller)->delete(route('account.orders.proofs.destroy', [$this->order, $proof]))->assertSessionHasErrors('proof', null, 'proof');
        Storage::disk('local')->assertExists($proof->path);

        // Still visible to the seller, read-only.
        $this->actingAs($this->seller)->get(route('account.orders.show', $this->order))
            ->assertSee('slip.jpg')->assertDontSee('data-proof-form', false)->assertDontSee('value="DELETE"', false);
        $this->actingAs($this->seller)->get(route('account.orders.proofs.show', [$this->order, $proof]))->assertOk();
    }

    public function test_a_transfer_date_in_the_future_or_long_before_the_order_is_refused(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400), ['transfer_date' => now()->addDay()->toDateString()])->assertSessionHasErrors('transfer_date', null, 'proof');
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400), ['transfer_date' => now()->subDays(10)->toDateString()])->assertSessionHasErrors('transfer_date', null, 'proof');
        $this->assertSame(0, PaymentProof::count());
    }

    // ---- Staff ----------------------------------------------------------------------------------

    public function test_staff_filter_orders_with_proof_see_them_first_and_view_the_files(): void
    {
        $this->travel(1)->hours();
        $older = $this->order;
        $this->order = app(BoostOrders::class)->place($this->seller, $older->listing, (int) BoostPrice::value('id'));
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400), ['transfer_note' => 'Paid at branch']);
        $this->upload(self::pdf());
        $admin = $this->staff(StaffRole::Admin);

        $screen = Livewire::actingAs($admin)->test(BoostOrdersAdmin::class);
        $this->assertSame([$this->order->id, $older->id], $screen->viewData('orders')->pluck('id')->all(), 'proof first, although older unpaid orders usually come first');
        $screen->assertSee(__('boosts.proof.received'))
            ->set('proof', 'without')->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$older->id])
            ->set('proof', 'with')->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$this->order->id])
            ->call('toggleProof', $this->order->id)
            ->assertSee(__('admin.orders.proof_warning'))->assertSee('Paid at branch')
            ->assertSee(route('admin.orders.proof', PaymentProof::where('kind', 'image')->sole()), false);

        $image = PaymentProof::where('kind', 'image')->sole();
        $this->actingAs($admin)->get(route('admin.orders.proof', $image))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(1, AuditLog::where('action', 'boost_order.proof_viewed')->count());
    }

    public function test_marking_paid_works_as_before_with_or_without_proof(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400));
        Livewire::actingAs($this->staff(StaffRole::Admin))->test(BoostOrdersAdmin::class)
            ->call('start', $this->order->id, 'paid')->call('confirm')->assertHasNoErrors();

        $this->assertSame(BoostOrderStatus::Paid, $this->order->fresh()->status);
        Storage::disk('local')->assertExists(PaymentProof::sole()->path);
    }

    // ---- Retention ------------------------------------------------------------------------------

    public function test_proof_files_are_deleted_90_days_after_the_order_is_paid_or_closed(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400));
        $path = PaymentProof::sole()->path;
        $this->order->forceFill(['status' => BoostOrderStatus::Paid, 'paid_at' => now()->subDays(89)])->save();

        $this->artisan('marketplace:prune')->assertSuccessful();
        Storage::disk('local')->assertExists($path);

        $this->order->forceFill(['paid_at' => now()->subDays(91)])->save();
        $this->artisan('marketplace:prune')->assertSuccessful();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, PaymentProof::count());
        $this->assertNull($this->order->fresh()->proof_at);
        $this->assertSame(BoostOrderStatus::Paid, $this->order->fresh()->status, 'the order itself stays');
    }

    public function test_unpaid_orders_keep_their_proof(): void
    {
        $this->upload(UploadedFile::fake()->image('slip.jpg', 600, 400));
        $this->travel(120)->days();

        $this->artisan('marketplace:prune')->assertSuccessful();
        $this->assertSame(1, PaymentProof::count());
    }

    // ---- Boost prices status (B7 fix) -----------------------------------------------------------

    public function test_the_prices_screen_names_exactly_what_stops_selling(): void
    {
        $catalogue = app(BoostCatalogue::class);
        $this->assertSame([], $catalogue->missing($this->lk));

        BoostProduct::query()->update(['is_active' => false]);
        $this->assertSame(['products_off'], $catalogue->missing($this->lk));

        BankTransferAccount::query()->update(['is_active' => false]);
        BoostPrice::query()->delete();
        config(['marketplace.boosts.sales_enabled' => false]);
        $this->assertSame(['sales_off', 'bank', 'prices'], $catalogue->missing($this->lk));

        Livewire::actingAs($this->staff(StaffRole::Admin))->test(BoostPricesAdmin::class)
            ->assertSee('data-missing="bank"', false)->assertSee('data-missing="prices"', false)
            ->assertSee(__('admin.boost_prices.missing.bank'))
            ->assertSee('data-product-state="off"', false)->assertSee(__('admin.boost_prices.turn_on'));
    }
}
