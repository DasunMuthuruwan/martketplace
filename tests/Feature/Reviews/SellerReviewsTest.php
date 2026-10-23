<?php

namespace Tests\Feature\Reviews;

use App\Models\BlockedTerm;
use App\Models\Category;
use App\Models\ContactReveal;
use App\Models\Country;
use App\Models\Listing;
use App\Models\SellerReview;
use App\Models\User;
use App\Notifications\SellerReviewed;
use App\Services\Reviews\SellerReviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** G5a (ADR-027 E2): ratings only from buyers who contacted the seller; moderated comments; one reply. */
class SellerReviewsTest extends TestCase
{
    use RefreshDatabase;

    private Country $lk;

    private User $seller;

    private Listing $ad;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->lk = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $this->seller = User::factory()->create(['name' => 'Kamal Seller']);
        $this->ad = Listing::factory()->inCountry($this->lk)->for($this->seller)->create([
            'category_id' => Category::factory()->create(['parent_id' => Category::factory()->create()->id])->id,
        ]);
    }

    private function buyer(string $name = 'Nimal Perera', int $revealedDaysAgo = 5, array $attributes = []): User
    {
        $buyer = User::factory()->create($attributes + ['name' => $name, 'created_at' => now()->subDays(30)]);
        if ($revealedDaysAgo >= 0) {
            $reveal = new ContactReveal(['listing_id' => $this->ad->id, 'user_id' => $buyer->id, 'channel' => 'call', 'ip_hash' => str_repeat('a', 64)]);
            $reveal->forceFill(['created_at' => now()->subDays($revealedDaysAgo)])->save();
        }

        return $buyer;
    }

    private function rate(User $buyer, array $input): TestResponse
    {
        return $this->actingAs($buyer)->post(route('seller.reviews.store', $this->seller->id), $input);
    }

    // ---- Who may rate -------------------------------------------------------------------------

    public function test_only_buyers_who_contacted_the_seller_in_the_window_may_rate(): void
    {
        $reviews = app(SellerReviews::class);

        $this->assertSame($this->ad->id, $reviews->eligibleListing($this->buyer('In window', 5), $this->seller));
        $this->assertNull($reviews->eligibleListing($this->buyer('Too soon', 1), $this->seller), 'cool-off of 2 days');
        $this->assertNull($reviews->eligibleListing($this->buyer('Too late', 100), $this->seller));
        $this->assertNull($reviews->eligibleListing($this->buyer('Never contacted', -1), $this->seller));
        $this->assertNull($reviews->eligibleListing($this->buyer('New account', 5, ['created_at' => now()->subDays(3)]), $this->seller));
        $this->assertNull($reviews->eligibleListing($this->buyer('Unverified', 5, ['email_verified_at' => null]), $this->seller));
        $this->assertNull($reviews->eligibleListing($this->seller, $this->seller), 'not oneself');
        $this->assertNull($reviews->eligibleListing(null, $this->seller));
    }

    public function test_the_form_shows_only_to_eligible_buyers(): void
    {
        $this->actingAs($this->buyer())->get($this->seller->sellerUrl())->assertOk()->assertSee('data-review-form', false);
        $this->actingAs($this->buyer('Stranger', -1))->get($this->seller->sellerUrl())->assertOk()->assertDontSee('data-review-form', false);
        $this->app['auth']->forgetGuards();
        $this->get($this->seller->sellerUrl())->assertOk()->assertDontSee('data-review-form', false);
    }

    public function test_an_ineligible_rating_is_refused(): void
    {
        $this->rate($this->buyer('Stranger', -1), ['rating' => 5])->assertSessionHasErrors('rating', null, 'review');

        $this->assertSame(0, SellerReview::count());
    }

    // ---- Rating ---------------------------------------------------------------------------------

    public function test_stars_publish_at_once_the_comment_waits_and_the_seller_is_told(): void
    {
        $this->rate($this->buyer(), ['rating' => 4, 'comment' => 'Honest seller, car as described.', 'deal' => 'yes'])->assertRedirect();

        $review = SellerReview::sole();
        $this->assertSame([4, 'pending', 'yes', $this->ad->id], [$review->rating, $review->comment_status, $review->deal, $review->listing_id]);
        Notification::assertSentTo($this->seller, SellerReviewed::class);
        $this->app['auth']->forgetGuards(); // as a visitor (the reviewer sees their own comment in the edit form)
        $this->get($this->seller->sellerUrl())->assertSee('Nimal P.')->assertSee('★★★★☆')
            ->assertSee(__('reviews.dealt'))->assertDontSee('Honest seller, car as described.');
    }

    public function test_the_average_shows_from_three_reviews(): void
    {
        foreach ([['A One', 5], ['B Two', 4], ['C Three', 4]] as $i => [$name, $stars]) {
            $this->rate($this->buyer($name), ['rating' => $stars]);
            $summary = app(SellerReviews::class)->summary($this->seller);
            $this->assertSame($i + 1, $summary['count']);
            $this->assertSame($i < 2 ? null : 4.3, $summary['average']);
        }
        $this->get($this->seller->sellerUrl())->assertSee('4.3')->assertSee('3 reviews');
        $this->get($this->ad->publicUrl())->assertSee('4.3')->assertSee('data-rating-summary', false);
    }

    public function test_one_review_per_buyer_and_seller_which_can_be_updated(): void
    {
        $buyer = $this->buyer();
        $this->rate($buyer, ['rating' => 2]);
        $review = SellerReview::sole();
        $review->forceFill(['comment' => 'Old', 'comment_status' => SellerReview::COMMENT_APPROVED])->save();

        $this->rate($buyer, ['rating' => 5, 'comment' => 'Changed my mind, all good']);

        $review->refresh();
        $this->assertSame(1, SellerReview::count());
        $this->assertSame([5, SellerReview::COMMENT_PENDING], [$review->rating, $review->comment_status], 'a changed comment is checked again');
        Notification::assertSentToTimes($this->seller, SellerReviewed::class, 1);
    }

    public function test_html_and_blocked_words_are_refused(): void
    {
        BlockedTerm::create(['term' => 'scammer', 'match' => BlockedTerm::MATCH_WORD, 'action' => BlockedTerm::ACTION_BLOCK]);
        $buyer = $this->buyer();

        $this->rate($buyer, ['rating' => 1, 'comment' => '<b>bad</b>'])->assertSessionHasErrors('comment', null, 'review');
        $this->rate($buyer, ['rating' => 1, 'comment' => 'This guy is a scammer'])->assertSessionHasErrors('comment', null, 'review');
        $this->rate($buyer, ['rating' => 9])->assertSessionHasErrors('rating', null, 'review');

        $this->assertSame(0, SellerReview::count());
    }

    public function test_approved_comments_are_shown_escaped(): void
    {
        $this->rate($this->buyer(), ['rating' => 3, 'comment' => 'Fine & fair']);
        SellerReview::query()->update(['comment' => '<script>xss(1)</script> fine', 'comment_status' => SellerReview::COMMENT_APPROVED]);

        $html = $this->get($this->seller->sellerUrl())->getContent();

        $this->assertStringContainsString('&lt;script&gt;xss(1)', $html);
        $this->assertStringNotContainsString('<script>xss', $html);
    }

    // ---- The seller's reply ---------------------------------------------------------------------

    public function test_the_seller_replies_once(): void
    {
        $this->rate($this->buyer(), ['rating' => 2]);
        $review = SellerReview::sole();

        $this->actingAs($this->seller)->get(route('account.reviews'))->assertOk()->assertSee('Nimal P.');
        $this->actingAs($this->seller)->post(route('account.reviews.reply', $review), ['reply' => 'Sorry, the car was sold that morning.'])->assertRedirect(route('account.reviews'));
        $this->actingAs($this->seller)->post(route('account.reviews.reply', $review), ['reply' => 'Second try'])->assertSessionHasErrors('reply', null, 'reply-'.$review->id);

        $this->assertSame('Sorry, the car was sold that morning.', $review->fresh()->reply);
        $this->get($this->seller->sellerUrl())->assertSee(__('reviews.seller_reply'))->assertSee('Sorry, the car was sold that morning.');
    }

    public function test_nobody_else_replies(): void
    {
        $buyer = $this->buyer();
        $this->rate($buyer, ['rating' => 2]);

        $this->actingAs($buyer)->post(route('account.reviews.reply', SellerReview::sole()), ['reply' => 'Hijack'])->assertForbidden();
        $this->assertNull(SellerReview::sole()->reply);
    }
}
