<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\StaffRole;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BlocklistAdmin;
use App\Livewire\Admin\StaffAdmin;
use App\Livewire\Admin\UsersAdmin;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** F5.1 part 2: designed users, staff, audit log and blocklist screens. */
class AdminPeopleScreensTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $owner;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
        $this->owner = $this->staff(StaffRole::Owner, attributes: ['name' => 'Olivia Owner']);
        $this->member = User::factory()->create(['name' => 'Nimal Perera', 'email' => 'nimal@example.com']);
        session()->put('auth.password_confirmed_at', time());
    }

    public function test_account_pane_shows_ads_by_status_and_grouped_actions(): void
    {
        Listing::factory()->count(2)->create(['user_id' => $this->member->id]);
        Listing::factory()->status(ListingStatus::PendingReview)->create(['user_id' => $this->member->id, 'published_at' => null]);

        $html = Livewire::actingAs($this->owner)->test(UsersAdmin::class)
            ->assertSee(__('admin.users.choose'))
            ->call('select', $this->member->id)
            ->html();

        $this->assertStringContainsString(__('admin.listing_statuses.active').' · 2', $html);
        $this->assertStringContainsString(__('admin.listing_statuses.pending_review').' · 1', $html);
        $this->assertStringContainsString('nimal@example.com', $html);
        $this->assertStringContainsString(__('admin.users.danger_help'), $html);
        $this->assertStringContainsString('wire:confirm="'.e(__('admin.users.confirm_delete', ['name' => 'Nimal Perera'])).'"', $html);
        $this->assertStringContainsString(__('staff.accounts.set_hold'), $html);
        $this->assertMatchesRegularExpression('/data-queue-item\s+aria-current="true"/', $html);
    }

    public function test_close_returns_to_the_user_list(): void
    {
        Livewire::actingAs($this->owner)->test(UsersAdmin::class)
            ->call('select', $this->member->id)->set('reason', 'x')
            ->call('close')
            ->assertSet('selectedId', null)->assertSet('reason', '');
    }

    public function test_staff_table_marks_you_and_two_factor(): void
    {
        $moderator = $this->staff(StaffRole::Moderator, attributes: ['name' => 'Mo Moderator']);

        $html = Livewire::actingAs($this->owner)->test(StaffAdmin::class)->html();

        $this->assertStringContainsString('('.__('admin.staff.you').')', $html);
        $this->assertStringContainsString(__('admin.staff.two_factor_on'), $html);
        // Reset is offered for others only, and asks first.
        $this->assertStringContainsString('wire:click="resetTwoFactor('.$moderator->id.')"', $html);
        $this->assertStringNotContainsString('wire:click="resetTwoFactor('.$this->owner->id.')"', $html);
        $this->assertStringContainsString(e(__('admin.staff.confirm_reset', ['name' => 'Mo Moderator'])), $html);
    }

    public function test_audit_entries_fold_their_changes(): void
    {
        AuditLog::create(['actor_id' => $this->owner->id, 'action' => 'settings.updated', 'reason' => 'Holiday notice',
            'changes' => ['before' => ['notice' => ''], 'after' => ['notice' => 'Closed']]]);

        Livewire::actingAs($this->owner)->test(AuditLogViewer::class)
            ->assertSee('settings.updated')
            ->assertSee('Holiday notice')
            ->assertSee(__('admin.audit.show_changes'))
            ->assertSeeHtml('<details')
            ->assertSee('Olivia Owner');
    }

    public function test_blocklist_shows_actions_as_badges_with_a_named_remove_button(): void
    {
        Livewire::actingAs($this->owner)->test(BlocklistAdmin::class)
            ->set('term', 'replica')->set('action', 'block')->call('add')
            ->assertSee(__('admin.blocklist.actions.block'))
            ->assertSeeHtml('aria-label="'.e(__('admin.blocklist.remove', ['term' => 'replica'])).'"')
            ->assertSee(__('admin.blocklist.add_title'));
    }
}
