<?php

namespace Tests\Feature\Reviews;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\ReviewsAdmin;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Report;
use App\Models\SellerReview;
use App\Models\User;
use App\Services\Reviews\ReviewModeration;
use App\Services\Reviews\SellerReviews;
use App\Services\Reviews\TrustBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** G5b (ADR-027): moderating review comments, reporting reviews, the trusted seller badge. */
class ReviewModerationTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $seller;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $this->seller = User::factory()->create(['name' => 'Kamal Seller', 'created_at' => now()->subYear()]);
        $this->moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
    }

    private function review(int $rating = 4, ?string $comment = null, string $commentStatus = SellerReview::COMMENT_NONE): SellerReview
    {
        $review = new SellerReview;
        $review->forceFill([
            'seller_id' => $this->seller->id, 'reviewer_id' => User::factory()->create(['name' => 'Nimal Perera'])->id,
            'rating' => $rating, 'comment' => $comment, 'comment_status' => $commentStatus,
        ])->save();

        return $review;
    }

    // ---- Comments ------------------------------------------------------------------------------

    public function test_moderators_approve_or_reject_comments_and_it_is_audited(): void
    {
        $good = $this->review(5, 'Smooth deal, recommended.', SellerReview::COMMENT_PENDING);
        $bad = $this->review(1, 'Rude words here', SellerReview::COMMENT_PENDING);

        Livewire::actingAs($this->moderator)->test(ReviewsAdmin::class)
            ->assertSee('Smooth deal, recommended.')
            ->call('approve', $good->id)
            ->call('reject', $bad->id);

        $this->assertSame(SellerReview::COMMENT_APPROVED, $good->fresh()->comment_status);
        $this->assertSame(SellerReview::COMMENT_REJECTED, $bad->fresh()->comment_status);
        $page = $this->get($this->seller->sellerUrl())->assertSee('Smooth deal, recommended.')->assertDontSee('Rude words here');
        $page->assertSee('★☆☆☆☆'); // a rejected comment's stars stay
        $this->assertSame(['review.comment_approved', 'review.comment_rejected'], AuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_only_staff_with_the_permission_moderate_reviews(): void
    {
        $this->actingAs($this->moderator)->get(route('admin.reviews'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.reviews'))->assertForbidden();
    }

    public function test_staff_never_decide_on_reviews_about_or_by_themselves(): void
    {
        $about = new SellerReview;
        $about->forceFill(['seller_id' => $this->moderator->id, 'reviewer_id' => $this->seller->id, 'rating' => 1, 'comment' => 'x', 'comment_status' => SellerReview::COMMENT_PENDING])->save();

        $this->expectException(ValidationException::class);
        app(ReviewModeration::class)->approveComment($this->moderator, $about);
    }

    // ---- Reports -------------------------------------------------------------------------------

    public function test_a_reported_review_is_kept_or_removed_with_a_reason(): void
    {
        $kept = $this->review(2);
        $removed = $this->review(1);
        $reporter = User::factory()->create();
        foreach ([$kept, $removed] as $review) {
            $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason' => 'fake', 'details' => 'Never contacted him'])
                ->assertRedirect($this->seller->sellerUrl().'#reviews');
        }
        $this->assertNotNull($kept->fresh()->reported_at);

        $screen = Livewire::actingAs($this->moderator)->test(ReviewsAdmin::class)->set('tab', 'reported')
            ->assertSee('Never contacted him')
            ->call('keep', $kept->id)
            ->call('startRemove', $removed->id)->call('remove')->assertHasErrors(['reason'])
            ->set('reason', 'Not a real buyer')->call('remove')->assertHasNoErrors();

        $this->assertNull($kept->fresh()->reported_at);
        $this->assertSame(SellerReview::PUBLISHED, $kept->fresh()->status);
        $this->assertSame([SellerReview::REMOVED, 'Not a real buyer'], [$removed->fresh()->status, $removed->fresh()->removed_reason]);
        $this->assertSame(1, app(SellerReviews::class)->summary($this->seller)['count'], 'a removed review no longer counts');
        $screen->set('tab', 'removed')->assertSee('Not a real buyer');
    }

    public function test_a_review_is_reported_once_per_person_and_never_by_its_author(): void
    {
        $review = $this->review();
        $reporter = User::factory()->create();

        $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason' => 'abusive']);
        $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason' => 'abusive'])->assertSessionHasErrors('reason', null, 'report-'.$review->id);
        $this->actingAs($review->reviewer)->post(route('reviews.report', $review), ['reason' => 'other'])->assertSessionHasErrors('reason', null, 'report-'.$review->id);
        $this->actingAs($reporter)->post(route('reviews.report', $review), ['reason' => 'nonsense'])->assertSessionHasErrors('reason', null, 'report-'.$review->id);

        $this->assertSame(1, $review->reports()->count());
    }

    // ---- Trusted seller ------------------------------------------------------------------------

    private function makeTrustworthy(): void
    {
        $lk = Country::where('iso2', 'LK')->firstOrFail();
        $category = Category::factory()->create(['parent_id' => Category::factory()->create()->id]);
        foreach (range(1, 5) as $i) {
            Listing::factory()->inCountry($lk)->for($this->seller)->status(ListingStatus::Sold)->create(['category_id' => $category->id, 'sold_at' => now()->subDays($i)]);
        }
        foreach ([5, 5, 5, 4, 5] as $stars) {
            $this->review($stars);
        }
    }

    public function test_a_seller_meeting_every_rule_is_trusted(): void
    {
        $this->makeTrustworthy();

        $this->assertTrue(app(TrustBadges::class)->isTrusted($this->seller));
        $this->get($this->seller->sellerUrl())->assertSee(__('reviews.trusted'))->assertSee('data-trusted-seller', false);
    }

    public function test_an_upheld_report_or_too_few_reviews_withholds_the_badge(): void
    {
        $this->makeTrustworthy();
        $report = new Report(['reason' => 'scam']);
        $report->forceFill(['reporter_id' => User::factory()->create()->id, 'reportable_type' => 'user', 'reportable_id' => $this->seller->id,
            'status' => ReportStatus::Actioned, 'handled_at' => now()->subDays(10)])->save();

        $this->assertFalse(app(TrustBadges::class)->isTrusted($this->seller), 'upheld report in 6 months');

        $report->forceFill(['handled_at' => now()->subDays(200)])->save();
        Cache::flush();
        $this->assertTrue(app(TrustBadges::class)->isTrusted($this->seller), 'older than 6 months');

        config(['marketplace.reviews.trusted_min_reviews' => 6]);
        Cache::flush();
        $this->assertFalse(app(TrustBadges::class)->isTrusted($this->seller), 'the Owner raised the bar');
    }
}
