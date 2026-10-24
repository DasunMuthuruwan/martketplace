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
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Boosts\BoostOrders;
use App\Services\Boosts\PaymentProofs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Boosts\PaymentProofsTest;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * B7: proof of payment holds bank details, so only the seller who ordered and staff with
 * `boosts.manage` reach it; uploads are checked by content, images re-encoded, PDFs with active
 * content refused and only ever downloaded; files never get a public address.
 */
class PaymentProofSecurityTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

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
        $lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true, 'default_currency' => $currency->code]);
        $this->seller = User::factory()->create();
        $ad = Listing::factory()->inCountry($lk)->for($this->seller)->create([
            'category_id' => Category::factory()->create(['parent_id' => Category::factory()->create()->id])->id, 'expires_at' => now()->addDays(40),
        ]);
        $price = BoostPrice::create(['boost_product_id' => BoostProduct::where('type', 'bump')->sole()->id, 'country_id' => $lk->id, 'currency' => 'LKR', 'amount' => '1000']);
        BankTransferAccount::create(['country_id' => $lk->id, 'bank_name' => 'Test Bank', 'account_name' => 'Marketplace', 'account_number' => '0001112223', 'is_active' => true]);
        $this->order = app(BoostOrders::class)->place($this->seller, $ad, $price->id);
    }

    private function proof(): PaymentProof
    {
        return app(PaymentProofs::class)->store($this->seller, $this->order, UploadedFile::fake()->image('slip.jpg', 600, 400));
    }

    public function test_only_the_seller_who_ordered_uploads_views_or_removes(): void
    {
        $proof = $this->proof();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('account.orders.proofs.store', $this->order), ['proof' => UploadedFile::fake()->image('x.jpg', 600, 400)])->assertForbidden();
        $this->actingAs($stranger)->get(route('account.orders.proofs.show', [$this->order, $proof]))->assertForbidden();
        $this->actingAs($stranger)->delete(route('account.orders.proofs.destroy', [$this->order, $proof]))->assertForbidden();
        $this->assertSame(1, PaymentProof::count());

        // Guests are sent to log in.
        auth()->logout();
        $this->get(route('account.orders.proofs.show', [$this->order, $proof]))->assertRedirect(route('login'));
    }

    public function test_a_proof_cannot_be_reached_through_another_order(): void
    {
        $proof = $this->proof();
        $otherOrder = new BoostOrder;
        $otherOrder->forceFill($this->order->only(['user_id', 'listing_id', 'boost_product_id', 'product_type', 'duration_days', 'country_id', 'currency', 'amount', 'status']) + ['reference' => 'OTHER0001'])->save();
        $mine = User::factory()->create();
        $otherOrder->forceFill(['user_id' => $mine->id])->save();

        // The attacker owns $otherOrder and swaps the proof id in the address.
        $this->actingAs($mine)->get(route('account.orders.proofs.show', [$otherOrder, $proof]))->assertNotFound();
        $this->actingAs($mine)->delete(route('account.orders.proofs.destroy', [$otherOrder, $proof]))->assertNotFound();
    }

    public function test_staff_need_the_boosts_permission(): void
    {
        $proof = $this->proof();

        $this->actingAs($this->staff(StaffRole::Moderator, twoFactor: false))->get(route('admin.orders.proof', $proof))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.orders.proof', $proof))->assertForbidden();
        $this->actingAs($this->staff(StaffRole::Admin))->get(route('admin.orders.proof', $proof))->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function refusedFiles(): array
    {
        return [
            'HTML named .pdf' => ['slip.pdf', '<html><script>alert(1)</script></html>'],
            'PHP named .jpg' => ['slip.jpg', '<?php echo "x"; ?>'],
            'SVG' => ['slip.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
            'GIF' => ['slip.gif', "GIF89a\x01\x00\x01\x00\x00\x00\x00;"],
            'executable' => ['slip.exe', "MZ\x90\x00"],
            'PDF without end' => ['slip.pdf', "%PDF-1.4\n1 0 obj << >> endobj\n"],
            'PDF with JavaScript' => ['slip.pdf', "%PDF-1.4\n1 0 obj << /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >> endobj\n%%EOF\n"],
            'PDF launching a program' => ['slip.pdf', "%PDF-1.4\n1 0 obj << /OpenAction << /S /Launch /F (cmd.exe) >> >> endobj\n%%EOF\n"],
            'PDF with an attached file' => ['slip.pdf', "%PDF-1.4\n1 0 obj << /Type /EmbeddedFile >> endobj\n%%EOF\n"],
        ];
    }

    #[DataProvider('refusedFiles')]
    public function test_disguised_or_active_files_are_refused(string $name, string $content): void
    {
        $this->actingAs($this->seller)->post(route('account.orders.proofs.store', $this->order), ['proof' => UploadedFile::fake()->createWithContent($name, $content)])
            ->assertSessionHasErrors('proof', null, 'proof');

        $this->assertSame(0, PaymentProof::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_an_oversized_file_is_refused(): void
    {
        $this->actingAs($this->seller)->post(route('account.orders.proofs.store', $this->order), ['proof' => UploadedFile::fake()->create('slip.pdf', 5121, 'application/pdf')])
            ->assertSessionHasErrors('proof', null, 'proof');
        $this->assertSame(0, PaymentProof::count());
    }

    public function test_pdfs_are_downloaded_never_shown_and_files_are_not_cached(): void
    {
        $pdf = app(PaymentProofs::class)->store($this->seller, $this->order, PaymentProofsTest::pdf('my slip.pdf'));

        $response = $this->actingAs($this->seller)->get(route('account.orders.proofs.show', [$this->order, $pdf]))->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $image = $this->proof();
        $this->assertStringStartsWith('inline;', (string) $this->actingAs($this->seller)->get(route('account.orders.proofs.show', [$this->order, $image]))->headers->get('Content-Disposition'));
    }

    public function test_file_names_are_cleaned_and_escaped(): void
    {
        // No path survives; the extension is always the stored kind's (served as image/jpeg anyway).
        $this->assertSame('evil.php.jpg', PaymentProofs::displayName('../../etc/evil.php.jpg', PaymentProof::IMAGE));
        $this->assertSame('evil.jpg', PaymentProofs::displayName('..\\..\\evil.php', PaymentProof::IMAGE));
        $this->assertSame('proof.pdf', PaymentProofs::displayName('<>:"|?*.pdf', PaymentProof::PDF));
        $this->assertSame('script.jpg', PaymentProofs::displayName('<script>alert(1)</script>.png', PaymentProof::IMAGE), 'the slash counts as a folder');
        $this->assertSame('scriptalert(1).jpg', PaymentProofs::displayName('<script>alert(1).png', PaymentProof::IMAGE));

        app(PaymentProofs::class)->store($this->seller, $this->order, UploadedFile::fake()->image('slip.jpg', 600, 400), null, '<script>xss(1)</script>');
        $page = (string) $this->actingAs($this->seller)->get(route('account.orders.show', $this->order))->getContent();
        $this->assertStringNotContainsString('<script>xss', $page);
        $html = Livewire::actingAs($this->staff(StaffRole::Admin))->test(BoostOrdersAdmin::class)->call('toggleProof', $this->order->id)->html();
        $this->assertStringNotContainsString('<script>xss', $html);
        $this->assertStringContainsString('&lt;script&gt;xss(1)', $html);
    }

    public function test_files_are_stored_privately_with_random_names(): void
    {
        $proof = $this->proof();

        $this->assertMatchesRegularExpression('#^payment-proofs/\d+/[0-9a-f]{32}\.jpg$#', $proof->path);
        $this->assertStringNotContainsString('slip', $proof->path);
        $this->assertSame(storage_path('app/private'), config('filesystems.disks.local.root'));
        $this->assertFalse((bool) config('filesystems.disks.local.serve'), 'no signed URL route for the private disk');
    }

    public function test_uploads_are_rate_limited_per_account(): void
    {
        config(['marketplace.boosts.proofs_per_hour_user' => 2]);
        foreach (range(1, 2) as $i) {
            $this->actingAs($this->seller)->post(route('account.orders.proofs.store', $this->order), ['proof' => UploadedFile::fake()->image("s{$i}.jpg", 600, 400)])->assertRedirect();
        }
        $this->actingAs($this->seller)->post(route('account.orders.proofs.store', $this->order), ['proof' => UploadedFile::fake()->image('s3.jpg', 600, 400)])->assertTooManyRequests();
    }
}
