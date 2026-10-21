<?php

namespace Tests\Feature\Security;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Support\ListingsDisk;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * S8.4: upload abuse through the real endpoint (`listings.photos.store`), as a signed-in seller.
 * The pipeline itself is unit-tested in ListingImageTest; here: decompression bomb rejected from
 * the header before decoding, path traversal in the file name, polyglot, many files in one request,
 * odd request shapes, someone else's ad, and Livewire's own upload endpoint.
 */
class UploadAbuseTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Listing $listing;

    private string $fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ListingsDisk::NAME);
        $this->seller = User::factory()->create();
        $this->listing = Listing::factory()->draft()->for($this->seller)->create();
        $this->fixtures = sys_get_temp_dir().'/upload-abuse-'.uniqid();
        mkdir($this->fixtures);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixtures);
        parent::tearDown();
    }

    private function file(string $name, string $bytes): UploadedFile
    {
        $path = $this->fixtures.'/'.uniqid().'.bin';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function jpeg(int $width = 800, int $height = 600): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($i = 0; $i < 300; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), 90));
        }
        ob_start();
        imagejpeg($image, null, 85);

        return (string) ob_get_clean();
    }

    /** A valid PNG of a few dozen bytes whose header claims $width × $height (decodes to gigabytes). */
    private function pngBomb(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress(''))
            .$chunk('IEND', '');
    }

    /** @param  list<UploadedFile>|mixed  $photos */
    private function upload(mixed $photos, ?Listing $listing = null): TestResponse
    {
        return $this->actingAs($this->seller)->postJson(route('listings.photos.store', $listing ?? $this->listing), ['photos' => $photos]);
    }

    /** @return list<string> */
    private function stored(): array
    {
        return Storage::disk(ListingsDisk::NAME)->allFiles();
    }

    public function test_a_decompression_bomb_is_rejected_from_its_header(): void
    {
        $bomb = $this->pngBomb(30000, 30000);
        $this->assertLessThan(200, strlen($bomb));
        $memory = memory_get_peak_usage(true);

        $this->upload([$this->file('bomb.png', $bomb)])->assertStatus(422)->assertJsonPath('added', 0);

        $this->assertSame([], $this->stored());
        // Never decoded: 900 MP would need gigabytes; the peak barely moves.
        $this->assertLessThan(64 * 1024 * 1024, memory_get_peak_usage(true) - $memory);
    }

    public function test_the_client_file_name_is_never_used_for_storage(): void
    {
        $this->upload([$this->file('../../../public/evil.php.jpg', $this->jpeg())])->assertOk();

        $paths = $this->stored();
        $this->assertCount(2, $paths);
        foreach ($paths as $path) {
            $this->assertMatchesRegularExpression('#^\d{4}/\d{2}/'.$this->listing->id.'/[0-9a-f]{32}-(lg|sm)\.(webp|jpg)$#', $path);
            $this->assertStringNotContainsString('evil', $path);
            $this->assertStringNotContainsString('..', $path);
        }
    }

    public function test_scripts_and_polyglots_are_rejected_or_neutralised(): void
    {
        $response = $this->upload([
            $this->file('shell.php', '<?php system($_GET["c"]); ?>'),
            $this->file('shell.jpg', '<?php system($_GET["c"]); ?>'),
            $this->file('shell.phtml', $this->jpeg()),
            $this->file('image.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'),
            $this->file('page.html', '<html><script>alert(1)</script></html>'),
            $this->file('polyglot.jpg', $this->jpeg().'<?php system($_GET["c"]); ?>'),
        ])->assertOk();

        $this->assertSame(1, $response->json('added'), 'only the real JPEG with trailing bytes is accepted');
        $this->assertCount(5, $response->json('errors'));
        foreach ($this->stored() as $path) {
            $this->assertStringNotContainsString('<?php', Storage::disk(ListingsDisk::NAME)->get($path));
            $this->assertMatchesRegularExpression('/\.(webp|jpg)$/', $path);
        }
    }

    public function test_many_files_in_one_request_stop_at_the_photo_limit(): void
    {
        $this->listing->category->update(['max_images' => 3]);
        $files = array_map(fn () => $this->file('p.jpg', $this->jpeg(400, 300)), range(1, 12));

        $response = $this->upload($files)->assertOk();

        $this->assertSame(3, $response->json('added'));
        $this->assertCount(9, $response->json('errors'));
        $this->assertSame(3, ListingImage::where('listing_id', $this->listing->id)->count());
        $this->assertCount(6, $this->stored());
    }

    public function test_odd_request_shapes_are_handled(): void
    {
        $this->upload('not-a-file')->assertStatus(422)->assertJsonPath('added', 0);
        $this->upload(['nested' => ['deeper' => 'x']])->assertStatus(422);
        $this->upload([])->assertStatus(422);
        $this->actingAs($this->seller)->post(route('listings.photos.store', $this->listing), ['photos' => $this->file('p.jpg', $this->jpeg())])
            ->assertRedirect(); // a single file instead of a list
        $this->assertSame([], array_filter($this->stored(), fn ($p) => ! preg_match('/[0-9a-f]{32}-(lg|sm)/', $p)));
    }

    public function test_only_the_owner_of_a_draft_can_upload(): void
    {
        $this->post(route('listings.photos.store', $this->listing), ['photos' => [$this->file('p.jpg', $this->jpeg())]])
            ->assertRedirect(route('login'));

        $other = User::factory()->create();
        $theirs = Listing::factory()->draft()->for($other)->create();
        $this->upload([$this->file('p.jpg', $this->jpeg())], $theirs)->assertForbidden();
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->post(route('listings.photos.store', Listing::factory()->draft()->for($unverified)->create()), ['photos' => [$this->file('p.jpg', $this->jpeg())]])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame([], $this->stored());
    }

    public function test_one_photo_per_request_batches_show_one_combined_outcome(): void
    {
        // S-03: posting.js sends each photo on its own; the reloaded page must show all outcomes.
        $this->upload([$this->file('one.jpg', $this->jpeg())])->assertOk();
        $this->actingAs($this->seller)->postJson(route('listings.photos.store', $this->listing), ['photos' => [$this->file('bad.jpg', 'not an image')], 'batch_continue' => '1'])->assertStatus(422);
        $this->actingAs($this->seller)->postJson(route('listings.photos.store', $this->listing), ['photos' => [$this->file('two.jpg', $this->jpeg())], 'batch_continue' => '1'])
            ->assertOk()->assertJsonPath('added', 1);

        $this->assertSame(trans_choice('posting.photos.added', 2, ['count' => 2]), session('status'));
        $this->assertCount(1, session('photo_errors'));
        $this->assertStringStartsWith('bad.jpg: ', session('photo_errors')[0]);

        // A new batch starts fresh.
        $this->upload([$this->file('three.jpg', $this->jpeg())])->assertOk();
        $this->assertSame(trans_choice('posting.photos.added', 1, ['count' => 1]), session('status'));
        $this->assertSame([], session('photo_errors'));
    }

    public function test_a_request_over_the_post_size_limit_gets_a_clear_page(): void
    {
        config(['app.debug' => false]);
        $tooBig = ['CONTENT_LENGTH' => (string) (64 * 1024 * 1024)];

        $this->actingAs($this->seller)->call('POST', route('listings.photos.store', $this->listing), [], [], [], $tooBig)
            ->assertStatus(413)->assertSee(__('errors.title.413'))->assertSee(__('errors.message.413'));
        $this->assertSame([], $this->stored());
    }

    public function test_the_livewire_upload_endpoint_is_unusable(): void
    {
        $route = app('router')->getRoutes()->getByName('livewire.upload-file');
        $this->assertNotNull($route);

        $response = $this->actingAs($this->seller)->post('/'.$route->uri(), ['files' => [$this->file('p.jpg', $this->jpeg())]]);

        $this->assertContains($response->getStatusCode(), [401, 403], 'no signed URL, no upload');
    }
}
