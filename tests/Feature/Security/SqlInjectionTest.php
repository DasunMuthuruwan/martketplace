<?php

namespace Tests\Feature\Security;

use App\Enums\StaffRole;
use App\Livewire\Admin\AttributesAdmin;
use App\Livewire\Admin\AuditLogViewer;
use App\Livewire\Admin\BlocklistAdmin;
use App\Livewire\Admin\CountriesAdmin;
use App\Livewire\Admin\ModerationQueue;
use App\Livewire\Admin\ReportsQueue;
use App\Livewire\Admin\UsersAdmin;
use App\Models\Category;
use App\Models\Country;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/**
 * S8.3: SQL injection probes. Every search parameter, attribute filter key and value, and every admin
 * search or filter field gets classic injection strings: the request must not fail (a database error
 * would show the string reached SQL unbound) and must not return rows it would not otherwise return.
 * The suite runs on SQLite and MariaDB (full-text boolean mode there).
 */
class SqlInjectionTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private const PROBES = [
        "' OR '1'='1",
        "1' OR 1=1 -- ",
        '1" OR "1"="1',
        "'; DROP TABLE listings; --",
        '1 UNION SELECT email, password FROM users -- ',
        "\\' OR 1=1#",
        '%',
        '_',
        '+toyota -honda* ~"x" @1 (a) <b> >c',
        '1) OR SLEEP(5)--',
        "\0",
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $country = Country::factory()->create(['iso2' => 'LK', 'is_active' => true]);
        $root = Category::factory()->create(['slug' => 'vehicles']);
        $cars = Category::factory()->create(['parent_id' => $root->id, 'slug' => 'cars']);
        Listing::factory()->count(2)->inCountry($country)->create(['category_id' => $cars->id, 'title' => 'Blue family hatchback']);
    }

    public function test_search_parameters_resist_injection(): void
    {
        $params = ['q', 'category', 'region', 'subregion', 'city', 'locality', 'type', 'condition', 'currency', 'min_price', 'max_price', 'sort', 'page', 'view'];
        foreach (['/ads', '/lk/ads', '/lk/ads/vehicles/cars'] as $url) {
            foreach (self::PROBES as $probe) {
                foreach ($params as $param) {
                    $response = $this->get($url.'?'.http_build_query([$param => $probe]));
                    $this->assertLessThan(500, $response->getStatusCode(), "{$url} {$param}={$probe}");
                }
                $response = $this->get($url.'?'.http_build_query(['a' => [$probe => $probe, 'make' => [$probe], 'year' => ['min' => $probe, 'max' => $probe]]]));
                $this->assertLessThan(500, $response->getStatusCode(), "{$url} attribute {$probe}");
            }
        }
        // Still exactly the two ads (no tautology widened the results, no table dropped).
        $this->assertSame(2, Listing::count());
    }

    public function test_keyword_tautology_finds_nothing(): void
    {
        $html = $this->get('/lk/ads?'.http_build_query(['q' => "zzz' OR '1'='1"]))->assertOk()->getContent();

        $this->assertStringNotContainsString('Blue family hatchback', $html);
    }

    public function test_admin_filters_resist_injection(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        $fields = [
            UsersAdmin::class => ['q', 'status'],
            BlocklistAdmin::class => ['q'],
            CountriesAdmin::class => ['q'],
            AttributesAdmin::class => ['q'],
            AuditLogViewer::class => ['action', 'actor', 'subjectType', 'subjectId', 'from', 'to'],
            ModerationQueue::class => [],
            ReportsQueue::class => [],
        ];
        foreach ($fields as $component => $names) {
            $test = Livewire::actingAs($owner)->test($component);
            foreach (array_unique(array_merge($names, $this->urlProperties($component))) as $name) {
                foreach (self::PROBES as $probe) {
                    $test->set($name, $probe)->assertOk();
                }
                $test->set($name, '');
            }
        }
        $this->assertSame(2, Listing::count());
    }

    /** @return list<string> the component's #[Url] string properties (filters kept in the address) */
    private function urlProperties(string $component): array
    {
        $names = [];
        foreach ((new \ReflectionClass($component))->getProperties() as $property) {
            $type = $property->getType();
            if ($property->getAttributes(Url::class) !== [] && $type instanceof \ReflectionNamedType && $type->getName() === 'string') {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    public function test_user_search_does_not_match_everyone(): void
    {
        $owner = $this->staff(StaffRole::Owner);
        User::factory()->create(['name' => 'Kamal Silva', 'email' => 'kamal@example.com']);

        Livewire::actingAs($owner)->test(UsersAdmin::class)->set('q', "' OR '1'='1")->assertDontSee('kamal@example.com');
        Livewire::actingAs($owner)->test(UsersAdmin::class)->set('q', '%')->assertDontSee('kamal@example.com');
    }
}
