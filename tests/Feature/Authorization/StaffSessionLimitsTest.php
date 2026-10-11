<?php

namespace Tests\Feature\Authorization;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

class StaffSessionLimitsTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['marketplace.staff_session.idle_minutes' => 30, 'marketplace.staff_session.absolute_minutes' => 480]);
    }

    /** @return array<string, array{StaffRole}> */
    public static function staffRoles(): array
    {
        return ['owner' => [StaffRole::Owner], 'admin' => [StaffRole::Admin], 'moderator' => [StaffRole::Moderator]];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_are_signed_out_after_the_idle_timeout(StaffRole $role): void
    {
        $this->actingAs($this->staff($role))->get('/admin')->assertOk();

        $this->travel(31)->minutes();

        $this->get('/admin')->assertRedirect('/login')->assertSessionHas('status', __('staff.session_expired'));
        $this->assertGuest();
    }

    public function test_activity_within_the_idle_timeout_keeps_the_session(): void
    {
        $this->actingAs($this->staff(StaffRole::Admin))->get('/admin')->assertOk();

        foreach (range(1, 3) as $step) {
            $this->travel(25)->minutes();
            $this->get('/admin')->assertOk();
        }

        $this->assertAuthenticated();
    }

    public function test_staff_are_signed_out_after_the_absolute_lifetime_even_when_active(): void
    {
        $this->actingAs($this->staff(StaffRole::Owner))->get('/admin')->assertOk();

        // Active every 20 minutes for 8 hours.
        foreach (range(1, 24) as $step) {
            $this->travel(20)->minutes();
            $this->get('/admin')->assertOk();
        }

        $this->travel(20)->minutes();
        $this->get('/admin')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_json_requests_get_401(): void
    {
        $this->actingAs($this->staff(StaffRole::Moderator))->get('/admin')->assertOk();
        $this->travel(31)->minutes();

        $this->getJson('/admin')->assertUnauthorized()->assertJson(['message' => __('staff.session_expired')]);
    }

    public function test_limits_are_configurable(): void
    {
        config(['marketplace.staff_session.idle_minutes' => 5]);
        $this->actingAs($this->staff(StaffRole::Admin))->get('/admin')->assertOk();

        $this->travel(6)->minutes();

        $this->get('/admin')->assertRedirect('/login');
    }

    public function test_regular_users_are_not_affected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('account.settings.profile'))->assertOk();

        $this->travel(10)->hours();

        $this->get(route('account.settings.profile'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_cannot_be_signed_in_by_a_remember_me_cookie(): void
    {
        $moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
        $moderator->forceFill(['remember_token' => Str::random(60)])->save();
        $recaller = $moderator->id.'|'.$moderator->remember_token.'|'.$moderator->password;

        $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->get('/admin')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_regular_users_can_still_use_remember_me(): void
    {
        $user = User::factory()->create(['remember_token' => Str::random(60)]);
        $recaller = $user->id.'|'.$user->remember_token.'|'.$user->password;

        $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->get(route('account.settings.profile'))
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }
}
