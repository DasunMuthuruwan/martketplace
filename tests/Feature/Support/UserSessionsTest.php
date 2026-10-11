<?php

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\UserSessions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function sessionsFor(User $user, string ...$ids): void
    {
        foreach ($ids as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        }
    }

    public function test_end_others_keeps_the_current_session(): void
    {
        $user = User::factory()->create();
        $this->sessionsFor($user, 'current', 'laptop', 'phone');

        UserSessions::endOthers($user, 'current');

        $this->assertSame(['current'], DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all());
    }

    public function test_end_all_removes_every_session_and_cycles_the_remember_token(): void
    {
        $user = User::factory()->create();
        $bystander = User::factory()->create();
        $this->sessionsFor($user, 'a', 'b');
        $this->sessionsFor($bystander, 'c');
        $token = $user->remember_token;

        UserSessions::endAll($user);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $bystander->id)->count());
        $this->assertNotSame($token, $user->fresh()->remember_token);
    }
}
