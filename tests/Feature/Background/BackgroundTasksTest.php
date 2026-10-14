<?php

namespace Tests\Feature\Background;

use App\Jobs\Middleware\RespectMailBudget;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Support\MailBudget;
use App\Support\MailGate;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/** T7.1: scheduler, web runner, mail budget and mail gate (ADR-006, ADR-009, CD-09). */
class BackgroundTasksTest extends TestCase
{
    use RefreshDatabase;

    // ---- Scheduler --------------------------------------------------------------------------

    public function test_schedule_has_every_housekeeping_task_without_overlap(): void
    {
        $events = collect(app(Schedule::class)->events())->mapWithKeys(fn (Event $event) => [
            trim((string) preg_replace('/^.*artisan\s+/', '', str_replace(['"', "'"], '', (string) $event->command))) => $event,
        ]);

        $expected = [
            'marketplace:expire-listings' => '0 * * * *',
            'marketplace:prune-images' => '0 0 * * *',
            'model:prune' => '0 0 * * *',
            'sitemap:generate' => '0 0 * * *',
            'queue:work --stop-when-empty --max-time=50 --tries=3' => '* * * * *',
        ];
        foreach ($expected as $command => $cron) {
            $this->assertTrue($events->has($command), "Missing scheduled command [{$command}]");
            $this->assertSame($cron, $events[$command]->expression, $command);
            $this->assertTrue($events[$command]->withoutOverlapping, "{$command} must not overlap");
        }
        $this->assertTrue($events->has('queue:prune-failed --hours=720'));
        $this->assertTrue($events->has('auth:clear-resets'));
    }

    public function test_scheduled_marketplace_commands_run(): void
    {
        $this->artisan('marketplace:expire-listings')->assertSuccessful();
        $this->artisan('marketplace:prune-images')->assertSuccessful();
    }

    // ---- Web runner -------------------------------------------------------------------------

    public function test_web_runner_drains_the_queue_once_per_interval_after_the_response(): void
    {
        config(['marketplace.web_runner.enabled' => true, 'marketplace.web_runner.max_seconds' => 7, 'queue.default' => 'database']);
        Artisan::shouldReceive('call')->once()->with('queue:work', Mockery::on(fn (array $options) => $options['--stop-when-empty'] === true && $options['--max-time'] === 7))->andReturn(0);

        $this->get('/countries')->assertOk();
        $this->get('/countries')->assertOk(); // within the interval: the lock is held
    }

    public function test_web_runner_runs_again_after_the_interval(): void
    {
        config(['marketplace.web_runner.enabled' => true, 'queue.default' => 'database']);
        Artisan::shouldReceive('call')->twice()->andReturn(0);

        $this->get('/countries');
        Cache::lock('web-runner')->forceRelease(); // the interval passed
        $this->get('/countries');
    }

    public function test_web_runner_is_off_by_default_and_with_the_sync_queue(): void
    {
        Artisan::shouldReceive('call')->never();

        config(['queue.default' => 'database']);
        $this->get('/countries')->assertOk();

        config(['marketplace.web_runner.enabled' => true, 'queue.default' => 'sync']);
        $this->get('/countries')->assertOk();
    }

    // ---- Mail gate --------------------------------------------------------------------------

    public function test_real_transports_go_to_the_log_until_sending_is_enabled(): void
    {
        config(['marketplace.mail.sending_enabled' => false, 'mail.default' => 'smtp']);
        MailGate::apply();
        $this->assertSame('log', config('mail.default'));

        config(['marketplace.mail.sending_enabled' => true, 'mail.default' => 'smtp']);
        MailGate::apply();
        $this->assertSame('smtp', config('mail.default'));

        config(['marketplace.mail.sending_enabled' => false, 'mail.default' => 'array']);
        MailGate::apply();
        $this->assertSame('array', config('mail.default'));
    }

    // ---- Mail budget ------------------------------------------------------------------------

    public function test_every_message_counts_against_the_hourly_budget(): void
    {
        config(['marketplace.mail.hourly_limit' => 2]);

        Mail::raw('one', fn ($m) => $m->to('a@example.com'));
        $this->assertSame([1, 1], [MailBudget::used(), MailBudget::remaining()]);
        Mail::raw('two', fn ($m) => $m->to('a@example.com'));
        $this->assertSame(0, MailBudget::remaining());

        $this->travel(1)->hours();
        $this->assertSame(2, MailBudget::remaining()); // a new hour, a new budget
    }

    public function test_queued_mail_waits_for_the_next_hour_when_the_budget_is_spent(): void
    {
        config(['marketplace.mail.hourly_limit' => 1]);
        $job = new class
        {
            public ?int $releasedFor = null;

            public function release(int $delay): void
            {
                $this->releasedFor = $delay;
            }
        };
        $ran = 0;
        $next = function () use (&$ran) {
            $ran++;

            return null;
        };

        (new RespectMailBudget)->handle($job, $next);
        $this->assertSame([1, null], [$ran, $job->releasedFor]);

        MailBudget::record();
        (new RespectMailBudget)->handle($job, $next);
        $this->assertSame(1, $ran);
        $this->assertNotNull($job->releasedFor);
        $this->assertGreaterThan(0, $job->releasedFor);
        $this->assertLessThanOrEqual(3600 + 120, $job->releasedFor);
    }

    public function test_queued_notifications_apply_the_budget_to_mail_only(): void
    {
        $notification = new ListingApproved(Listing::factory()->create());
        $user = User::factory()->create();

        $this->assertInstanceOf(RespectMailBudget::class, $notification->middleware($user, 'mail')[0]);
        $this->assertSame([], $notification->middleware($user, 'database'));
        $this->assertTrue($notification->retryUntil()->getTimestamp() > now()->addHours(2)->getTimestamp());
    }
}
