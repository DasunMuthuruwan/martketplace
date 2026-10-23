<?php

namespace Tests\Feature\Security;

use App\Enums\StaffRole;
use App\Livewire\Admin\ReviewsAdmin;
use App\Models\Category;
use App\Models\ContactReveal;
use App\Models\Country;
use App\Models\Listing;
use App\Models\SellerReview;
use App\Models\User;
use App\Services\Insights\Trends;
use App\Services\Reviews\SellerReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * G6 (ADR-027): security of the audience features. Text from buyers and sellers (replies, report
 * details) is escaped wherever it shows; review eligibility cannot be forged from the form; reviews
 * cannot target accounts that are not public sellers; suggestions never leak private data.
 */
class AudienceFeaturesSecurityTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const PAYLOAD = '<script>xss(1)</script><img src=x onerror=xss(2)>';

    private User $seller;

    private Listing $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $this->seller = User::factory()->create();
        $this->ad = Listing::factory()->inCountry($lk)->for($this->seller)->create(['category_id' => Category::factory()->create(['parent_id' => Category::factory()->create()->id])->id]);
    }

    private function assertEscaped(string $html): void
    {
        $this->assertStringNotContainsString('<script>xss', $html);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*onerror=xss/i', $html);
    }

    private function review(): SellerReview
    {
        $review = new SellerReview;
        $review->forceFill(['seller_id' => $this->seller->id, 'reviewer_id' => User::factory()->create()->id, 'rating' => 3,
            'reply' => 'Reply '.self::PAYLOAD])->save();

        return $review;
    }

    public function test_seller_replies_and_report_details_are_escaped(): void
    {
        $review = $this->review();
        $review->reports()->create(['reason' => 'other', 'details' => 'Details '.self::PAYLOAD])->forceFill(['reporter_id' => User::factory()->create()->id])->save();
        $review->forceFill(['reported_at' => now()])->save();

        $page = $this->get($this->seller->sellerUrl())->assertOk()->getContent();
        $this->assertStringContainsString('&lt;script&gt;xss(1)', $page);
        $this->assertEscaped($page);

        $admin = Livewire::actingAs($this->staff(StaffRole::Moderator, twoFactor: false))->test(ReviewsAdmin::class)->set('tab', 'reported')->html();
        $this->assertStringContainsString('&lt;script&gt;xss(1)', $admin);
        $this->assertEscaped($admin);
    }

    public function test_review_eligibility_and_ownership_cannot_be_forged_from_the_form(): void
    {
        $stranger = User::factory()->create(['created_at' => now()->subDays(30)]);
        $other = User::factory()->create();

        $this->actingAs($stranger)->post(route('seller.reviews.store', $this->seller->id), [
            'rating' => 5, 'listing_id' => $this->ad->id, 'reviewer_id' => $other->id, 'status' => 'published', 'comment_status' => 'approved',
        ])->assertSessionHasErrors('rating', null, 'review');
        $this->assertSame(0, SellerReview::count());

        // An eligible buyer cannot approve their own comment or write as someone else.
        $reveal = new ContactReveal(['listing_id' => $this->ad->id, 'user_id' => $stranger->id, 'channel' => 'call', 'ip_hash' => str_repeat('a', 64)]);
        $reveal->forceFill(['created_at' => now()->subDays(5)])->save();
        $this->actingAs($stranger)->post(route('seller.reviews.store', $this->seller->id), [
            'rating' => 5, 'comment' => 'Great', 'reviewer_id' => $other->id, 'comment_status' => 'approved', 'seller_id' => $other->id,
        ]);
        $review = SellerReview::sole();
        $this->assertSame([$stranger->id, $this->seller->id, SellerReview::COMMENT_PENDING], [$review->reviewer_id, $review->seller_id, $review->comment_status]);
    }

    public function test_reviews_only_target_public_seller_accounts(): void
    {
        $buyer = User::factory()->create(['created_at' => now()->subDays(30)]);
        $this->seller->forceFill(['status' => 'banned'])->save();

        $this->actingAs($buyer)->post(route('seller.reviews.store', $this->seller->id), ['rating' => 1])->assertNotFound();
        $this->actingAs($buyer)->post(route('seller.reviews.store', 999999), ['rating' => 1])->assertNotFound();
        $this->assertNull(app(SellerReviews::class)->eligibleListing($buyer, $this->seller));
    }

    public function test_suggestions_never_contain_emails_phone_numbers_or_rare_searches(): void
    {
        DB::table('search_terms')->insert([
            ['country_id' => $this->ad->country_id, 'term' => 'kamal 0771234567', 'date' => now()->toDateString(), 'searches' => 1],
            ['country_id' => $this->ad->country_id, 'term' => 'kamal private', 'date' => now()->toDateString(), 'searches' => 2],
        ]);

        $json = (string) $this->getJson(route('search.suggest', ['q' => 'kamal', 'cc' => 'lk']))->assertOk()->getContent();

        $this->assertStringNotContainsString('0771234567', $json);
        $this->assertStringNotContainsString('kamal private', $json, 'below the minimum count');
    }

    public function test_phone_numbers_are_never_recorded_or_shown_as_popular_searches(): void
    {
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36';
        $this->withHeader('User-Agent', $agent)->get('/lk/ads?q='.urlencode('077 123 4567'))->assertOk();
        $this->assertSame(0, DB::table('search_terms')->count());

        // Even if one was stored (older data, direct import), it is never shown.
        DB::table('search_terms')->insert(['country_id' => $this->ad->country_id, 'term' => 'call 0771234567', 'date' => now()->toDateString(), 'searches' => 50]);
        $this->assertStringNotContainsString('0771234567', (string) $this->getJson(route('search.suggest', ['q' => 'call', 'cc' => 'lk']))->getContent());
        $this->assertSame([], app(Trends::class)->trendingSearches(Country::where('iso2', 'LK')->firstOrFail()));
    }
}
