<?php

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function change(User $user, array $input): TestResponse
    {
        return $this->actingAs($user)
            ->from(route('account.settings.security'))
            ->put(route('user-password.update'), $input);
    }

    public function test_security_page_renders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('account.settings.security'))
            ->assertOk()
            ->assertSee(route('user-password.update'))
            ->assertSee(route('account.destroy'));
    }

    public function test_password_can_be_changed_with_the_current_password(): void
    {
        $user = User::factory()->create();

        $this->change($user, [
            'current_password' => 'password',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertRedirect(route('account.settings.security'))->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('a-brand-new-passphrase', $user->fresh()->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->change($user, [
            'current_password' => 'not-my-password',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_new_password_follows_the_password_rules(): void
    {
        $user = User::factory()->create();

        $this->change($user, ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
        $this->change($user, ['current_password' => 'password', 'password' => 'a-brand-new-passphrase', 'password_confirmation' => 'different-passphrase'])
            ->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_other_devices_are_signed_out(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'laptop', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'phone', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'someone-else', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);
        $rememberToken = $user->remember_token;

        $this->change($user, [
            'current_password' => 'password',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNotSame($rememberToken, $user->fresh()->remember_token, '"Remember me" cookies stop working');
    }
}
