<?php

namespace Tests\Feature\Security;

use App\Enums\ListingStatus;
use App\Enums\ReportReason;
use App\Enums\StaffRole;
use App\Enums\UserStatus;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\ReportsQueue;
use App\Livewire\Admin\UsersAdmin;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Listing;
use App\Models\Page;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use App\Notifications\AccountStatusChanged;
use App\Notifications\ListingApproved;
use App\Notifications\ListingExpiringSoon;
use App\Notifications\ListingRejected;
use App\Notifications\ListingSuspended;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * S8.3: stored and reflected XSS. Script payloads are written straight to the database (as if they
 * had slipped past input validation, which rejects HTML in titles, descriptions and reports) into
 * every user-editable text, then every page that shows them must print them escaped: no live tag,
 * no event handler, no javascript: link, no attribute break-out.
 */
class StoredXssTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    // HTML, an attribute break-out and Markdown links (emails are rendered from Markdown).
    private const PAYLOAD = '<script>xss(1)</script><img src=x onerror=xss(2)>" autofocus onfocus="xss(3)<a href="javascript:xss(4)">x</a> [y](javascript:xss(5)) ![z](data:text/html,xss)';

    // For short columns (title 100, area text 80): the tag, handler and attribute break-out parts.
    private const SHORT = '<script>xss(1)</script><img src=x onerror=xss(2)>" onfocus="xss(3)';

    private Country $country;

    private User $seller;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $this->country = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $region = Region::factory()->create(['country_id' => $this->country->id, 'level' => 1, 'name' => 'Region '.self::PAYLOAD]);
        $city = City::factory()->create(['country_id' => $this->country->id, 'region_id' => $region->id, 'name' => 'Town '.self::PAYLOAD]);
        $root = Category::factory()->create(['slug' => 'vehicles']);
        $cars = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'cars']);

        $this->seller = User::factory()->create(['name' => 'Seller '.self::PAYLOAD, 'status_reason' => 'Reason '.self::PAYLOAD]);
        $this->listing = Listing::factory()->inCountry($this->country)->for($this->seller)->create([
            'category_id' => $cars->id,
            'title' => 'Title '.self::SHORT,
            'description' => "Line one\n".self::PAYLOAD,
            'region_id' => $region->id,
            'city_id' => $city->id,
            'locality_text' => 'Area '.self::SHORT,
        ]);
    }

    /** No live markup from the payload: a real tag (an unescaped "<") carrying it, or a broken-out attribute. */
    private function assertEscaped(string $html, string $where): void
    {
        $this->assertStringNotContainsString('<script>xss', $html, $where);
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*onerror=xss/i', $html, $where);
        // Inside a tag: the payload's quote closed an attribute and added a handler.
        $this->assertDoesNotMatchRegularExpression('/<[a-z][^<>]*\sonfocus="xss/i', $html, $where);
        $this->assertDoesNotMatchRegularExpression('/<a\s[^<>]*href=["\']?\s*(javascript|data):/i', $html, $where);
    }

    public function test_public_pages_escape_user_text(): void
    {
        $pages = [
            'listing' => $this->listing->publicUrl(),
            'seller' => $this->seller->sellerUrl(),
            'country home' => '/lk',
            'country search' => '/lk/ads',
            'category' => '/lk/ads/vehicles/cars',
            'global search' => '/ads',
        ];
        foreach ($pages as $name => $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('&lt;script&gt;xss(1)', $html, "{$name}: payload not shown at all, the check proves nothing");
            $this->assertEscaped($html, $name);
        }
    }

    public function test_reflected_search_input_is_escaped(): void
    {
        foreach (['/ads', '/lk/ads'] as $url) {
            $response = $this->get($url.'?'.http_build_query(['q' => self::PAYLOAD, 'sort' => self::PAYLOAD, 'view' => self::PAYLOAD, 'region' => self::PAYLOAD]));
            $this->assertContains($response->getStatusCode(), [200, 302], $url);
            $this->assertEscaped((string) $response->getContent(), $url);
            if ($response->isRedirect()) {
                $this->assertStringStartsWith(url('/'), (string) $response->headers->get('Location'));
            }
        }
        $this->assertEscaped($this->get('/lk/ads?q='.urlencode('toyota '.self::PAYLOAD))->getContent(), 'keyword');
    }

    public function test_owner_and_account_pages_escape_user_text(): void
    {
        $this->listing->forceFill(['status' => ListingStatus::Rejected, 'moderation_note' => 'Note '.self::PAYLOAD])->save();

        foreach (['account.overview', 'account.listings'] as $route) {
            $this->assertEscaped($this->actingAs($this->seller)->get(route($route))->assertOk()->getContent(), $route);
        }
        $this->assertEscaped($this->actingAs($this->seller)->get(route('listings.edit', [$this->listing, 'review']))->getContent(), 'review step');
    }

    public function test_admin_screens_escape_user_text(): void
    {
        $this->listing->forceFill(['status' => ListingStatus::PendingReview, 'published_at' => null, 'submitted_at' => now()])->save();
        $reporter = User::factory()->create(['name' => 'Reporter '.self::PAYLOAD]);
        $report = new Report(['reason' => ReportReason::Scam, 'details' => 'Details '.self::PAYLOAD]);
        $report->forceFill(['reporter_id' => $reporter->id, 'reportable_type' => $this->listing->getMorphClass(), 'reportable_id' => $this->listing->id])->save();
        $owner = $this->staff(StaffRole::Owner);

        $screens = [
            'moderation' => Livewire::actingAs($owner)->test(ModerationQueue::class)->call('select', $this->listing->id)->html(),
            'reports' => Livewire::actingAs($owner)->test(ReportsQueue::class)->call('select', $report->id)->html(),
            'users' => Livewire::actingAs($owner)->test(UsersAdmin::class)->call('select', $this->seller->id)->html(),
        ];
        foreach ($screens as $name => $html) {
            $this->assertStringContainsString('&lt;script&gt;xss(1)', $html, "{$name}: payload not shown");
            $this->assertEscaped($html, $name);
        }
    }

    public function test_emails_escape_user_text(): void
    {
        $this->listing->forceFill(['moderation_note' => 'Note '.self::PAYLOAD])->save();
        $mails = [
            new ListingRejected($this->listing),
            new ListingRejected($this->listing, changesRequested: true),
            new ListingApproved($this->listing),
            new ListingSuspended($this->listing),
            new ListingExpiringSoon($this->listing),
            new AccountStatusChanged(UserStatus::Suspended, 'Reason '.self::PAYLOAD, now()->addDays(7)),
            new AccountStatusChanged(UserStatus::Banned, 'Reason '.self::PAYLOAD),
        ];
        foreach ($mails as $notification) {
            $mail = $notification->toMail($this->seller);
            $html = (string) $mail->render();
            $this->assertEscaped($html, $notification::class);
            $this->assertStringNotContainsString('<script', (string) $mail->subject, $notification::class);
        }
    }

    public function test_static_pages_drop_html_and_unsafe_links(): void
    {
        Page::create([
            'slug' => 'about-test',
            'title' => 'About '.self::PAYLOAD,
            'body' => "# Hello\n\n".self::PAYLOAD."\n\n[click](javascript:xss(5)) [data](data:text/html;base64,PHNjcmlwdD4=) [ok](https://example.com)",
            'is_published' => true,
        ]);

        $html = $this->get(route('page.show', 'about-test'))->assertOk()->getContent();

        $this->assertEscaped($html, 'static page');
        $this->assertStringContainsString('href="https://example.com"', $html);
    }
}
