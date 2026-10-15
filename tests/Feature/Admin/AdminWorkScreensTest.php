<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\ReportsQueue;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** F5.1 part 1: designed dashboard, moderation review pane (reason step, shortcuts) and reports. */
class AdminWorkScreensTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $this->moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
    }

    private function pending(array $attributes = []): Listing
    {
        return Listing::factory()->status(ListingStatus::PendingReview)->create(['published_at' => null, 'submitted_at' => now()] + $attributes);
    }

    public function test_queue_items_and_pane_carry_the_shortcut_hooks(): void
    {
        $listing = $this->pending(['title' => 'Queue item ad here']);

        $html = Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->assertSee(__('admin.moderation.choose'))
            ->call('select', $listing->id)
            ->html();

        $this->assertStringContainsString('data-shortcuts', $html);
        $this->assertMatchesRegularExpression('/data-queue-item\s+aria-current="true"/', $html);
        $this->assertStringContainsString('data-shortcut="a"', $html);
        $this->assertStringContainsString('data-shortcut="r"', $html);
        $this->assertStringContainsString(__('admin.shortcuts.title'), $html);
        $this->assertStringContainsString(__('admin.moderation.back'), $html);
    }

    public function test_reason_step_opens_and_cancels(): void
    {
        $listing = $this->pending();

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $listing->id)
            ->call('startDecision', 'reject')
            ->assertSet('decision', 'reject')
            ->assertSee(__('admin.moderation.reason_step_reject'))
            ->assertSee(__('admin.moderation.templates.photos'))
            ->assertSee(__('admin.moderation.confirm_reject'))
            ->assertDontSee('data-shortcut="a"', false)
            ->call('cancelDecision')
            ->assertSet('decision', null)
            ->assertSee(__('admin.moderation.approve'));

        $this->assertSame(ListingStatus::PendingReview, $listing->fresh()->status);
    }

    public function test_reason_step_only_for_known_kinds_and_a_chosen_ad(): void
    {
        $listing = $this->pending();

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)->call('startDecision', 'reject')->assertNotFound();
        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)->call('select', $listing->id)->call('startDecision', 'approve')->assertNotFound();
    }

    public function test_close_returns_to_the_list(): void
    {
        $listing = $this->pending();

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $listing->id)->call('startDecision', 'changes')
            ->call('close')
            ->assertSet('selectedId', null)->assertSet('decision', null);
    }

    public function test_last_decision_leaves_the_empty_state(): void
    {
        $listing = $this->pending();

        Livewire::actingAs($this->moderator)->test(ModerationQueue::class)
            ->call('select', $listing->id)->call('approve')
            ->assertSet('selectedId', null)
            ->assertSee(__('admin.moderation.empty'));
    }

    public function test_reports_pane_shows_the_reported_ad_and_closes(): void
    {
        $listing = Listing::factory()->create(['title' => 'Reported ad here']);
        $report = new Report(['reason' => 'scam', 'details' => 'Asked for money in advance.']);
        $report->forceFill(['reporter_id' => User::factory()->create()->id, 'reportable_type' => 'listing', 'reportable_id' => $listing->id])->save();

        Livewire::actingAs($this->moderator)->test(ReportsQueue::class)
            ->call('select', $report->id)
            ->assertSee('Reported ad here')
            ->assertSee(__('admin.reports.reported_item'))
            ->assertSee(__('admin.reports.action'))
            ->call('close')
            ->assertSet('selectedId', null)
            ->assertSee(__('admin.reports.choose'));
    }

    public function test_dashboard_shows_tiles_and_country_names(): void
    {
        $listing = Listing::factory()->create();
        $this->pending();

        Livewire::actingAs($this->moderator)->test(Dashboard::class)
            ->assertSee(__('admin.dashboard.pending'))
            ->assertSee(route('admin.moderation'))
            ->assertSee($listing->country->name)
            ->assertSee('share-bar');
    }
}
