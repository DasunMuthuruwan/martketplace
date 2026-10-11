<?php

namespace Tests\Feature\Account;

use App\Enums\StaffRole;
use App\Models\User;
use App\Notifications\AccountDeletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Notification::fake();
    }

    private function deleteAs(User $user, ?string $password = 'password'): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('account.settings.security'))
            ->delete(route('account.destroy'), $password === null ? [] : ['password' => $password]);
    }

    public function test_deletion_needs_login(): void
    {
        $this->delete(route('account.destroy'))->assertRedirect(route('login'));
    }

    public function test_account_is_soft_deleted_signed_out_everywhere_and_emailed(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'phone', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->deleteAs($user)->assertRedirect(route('home'))->assertSessionHas('status');

        $this->assertGuest();
        $this->assertSoftDeleted($user);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        Notification::assertSentTo($user, AccountDeletedNotification::class);
    }

    public function test_deleted_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);
        $this->deleteAs($user);

        $this->post('/login', ['email' => 'gone@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_deleted_accounts_email_stays_reserved(): void
    {
        $user = User::factory()->create(['email' => 'gone@example.com']);
        $this->deleteAs($user);

        $this->assertDatabaseHas('users', ['email' => 'gone@example.com']);
        $this->assertSame(0, User::where('email', 'gone@example.com')->count(), 'Hidden from normal queries');
    }

    public function test_wrong_or_missing_password_keeps_the_account(): void
    {
        $user = User::factory()->create();

        $this->deleteAs($user, 'not-my-password')->assertSessionHasErrorsIn('deleteAccount', 'password');
        $this->deleteAs($user, null)->assertSessionHasErrorsIn('deleteAccount', 'password');

        $this->assertNotSoftDeleted($user);
        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent();
    }

    public function test_the_last_owner_cannot_delete_their_account(): void
    {
        $owner = $this->staff(StaffRole::Owner);

        $this->deleteAs($owner)->assertSessionHasErrorsIn('deleteAccount', 'password');

        $this->assertNotSoftDeleted($owner);
        $this->assertSame(StaffRole::Owner, $owner->fresh()->staffRole());
    }

    public function test_staff_lose_their_role_when_deleting_their_account(): void
    {
        $this->staff(StaffRole::Owner);
        $secondOwner = $this->staff(StaffRole::Owner);
        $moderator = $this->staff(StaffRole::Moderator);

        $this->deleteAs($secondOwner)->assertRedirect(route('home'));
        $this->deleteAs($moderator)->assertRedirect(route('home'));

        $this->assertSoftDeleted($secondOwner);
        $this->assertSame([], User::withTrashed()->find($secondOwner->id)->getRoleNames()->all());
        $this->assertSame([], User::withTrashed()->find($moderator->id)->getRoleNames()->all());
        $this->assertSame(1, User::role(StaffRole::Owner->value)->count());
    }

    public function test_unverified_users_can_delete_their_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->deleteAs($user)->assertRedirect(route('home'));

        $this->assertSoftDeleted($user);
    }
}
