<?php

namespace Tests\Feature\Listings;

use App\Actions\Listings\ManageListingImages;
use App\Enums\ListingStatus;
use App\Exceptions\Listings\ListingNotEditable;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Support\ListingsDisk;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Photo pipeline (T4.3, security doc §4): fixtures are generated with GD for each test.
 */
class ListingImageTest extends TestCase
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
        $this->fixtures = sys_get_temp_dir().'/listing-image-fixtures-'.uniqid();
        mkdir($this->fixtures);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixtures);
        parent::tearDown();
    }

    // ---- Fixture helpers ------------------------------------------------------------------------

    /** A noisy image (so encoders cannot shrink it to nothing) in the given format. */
    private function bytes(string $format, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 30, 120, 200));
        for ($i = 0; $i < 500; $i++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image, null, 90),
            'gif' => imagegif($image),
            default => imagejpeg($image, null, 90),
        };

        return (string) ob_get_clean();
    }

    private function upload(string $name, string $bytes): UploadedFile
    {
        $path = $this->fixtures.'/'.uniqid().'-'.$name;
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function image(string $format = 'jpg', int $width = 1200, int $height = 900): UploadedFile
    {
        return $this->upload("photo.{$format}", $this->bytes($format, $width, $height));
    }

    private function add(UploadedFile $file, ?Listing $listing = null): ListingImage
    {
        return app(ManageListingImages::class)->add($this->seller, $listing ?? $this->listing, $file);
    }

    private function rejection(UploadedFile $file): string
    {
        try {
            $this->add($file);
        } catch (ValidationException $e) {
            return $e->errors()['image'][0];
        }
        $this->fail('Upload was accepted');
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::disk(ListingsDisk::NAME)->allFiles();
    }

    // ---- Accepted uploads -----------------------------------------------------------------------

    public function test_a_photo_becomes_two_webp_variants_and_the_cover(): void
    {
        $image = $this->add($this->image('jpg', 1200, 900));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $image->basename, 'Random name, never the upload name');
        $this->assertSame('webp', $image->format);
        $this->assertSame([1200, 900], [$image->width, $image->height]);
        $this->assertEqualsCanonicalizing([$image->path('lg'), $image->path('sm')], $this->storedFiles(), 'The original is not kept');
        $this->assertStringStartsWith(now()->format('Y/m').'/'.$this->listing->id.'/', $image->path('lg'));

        $disk = Storage::disk(ListingsDisk::NAME);
        $this->assertSame('image/webp', (new \finfo(FILEINFO_MIME_TYPE))->buffer($disk->get($image->path('sm'))));
        $this->assertSame([480, 360], array_slice(getimagesizefromstring($disk->get($image->path('sm'))), 0, 2));
        $this->assertSame(hash('sha256', $disk->get($image->path('lg'))), $image->sha256);
        $this->assertSame($image->id, $this->listing->fresh()->cover_image_id);
    }

    public function test_large_and_portrait_photos_are_scaled_within_the_limits(): void
    {
        $landscape = $this->add($this->image('png', 3000, 2000));
        $this->assertSame([1600, 1067], [$landscape->width, $landscape->height]);

        $portrait = $this->add($this->image('webp', 900, 1200));
        $this->assertSame([900, 1200], [$portrait->width, $portrait->height]);
        $this->assertSame([360, 480], array_slice(getimagesizefromstring(Storage::disk(ListingsDisk::NAME)->get($portrait->path('sm'))), 0, 2));
        $this->assertSame(2, $portrait->sort_order);
    }

    public function test_jpeg_output_when_webp_is_not_wanted(): void
    {
        config(['marketplace.images.format' => 'jpg']);

        $image = $this->add($this->image());

        $this->assertSame('jpg', $image->format);
        $this->assertStringEndsWith('-lg.jpg', $image->path('lg'));
    }

    public function test_metadata_and_hidden_payloads_do_not_survive(): void
    {
        // An EXIF segment with a GPS marker inserted right after the JPEG start marker.
        $exif = "Exif\0\0".'GPS-LAT-6.9271-LON-79.8612-SECRET';
        $jpeg = $this->bytes('jpg', 1000, 800);
        $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $polyglot = $withExif.'<?php system($_GET["c"]); ?>';

        $image = $this->add($this->upload('holiday.jpg', $polyglot));

        foreach ($this->storedFiles() as $path) {
            $stored = Storage::disk(ListingsDisk::NAME)->get($path);
            $this->assertStringNotContainsString('SECRET', $stored, 'EXIF/GPS removed');
            $this->assertStringNotContainsString('<?php', $stored, 'Appended script removed');
        }
        $this->assertNotNull($image->id);
    }

    // ---- Rejected uploads -----------------------------------------------------------------------

    public function test_a_php_file_disguised_as_a_jpeg_is_rejected(): void
    {
        $this->assertSame(__('images.type'), $this->rejection($this->upload('shell.jpg', '<?php echo "pwned"; ?>')));
        $this->assertSame([], $this->storedFiles());
        $this->assertSame(0, ListingImage::count());
    }

    public function test_other_formats_and_mismatched_content_are_rejected(): void
    {
        $this->assertSame(__('images.type'), $this->rejection($this->upload('anim.gif', $this->bytes('gif', 800, 600))));
        $this->assertSame(__('images.type'), $this->rejection($this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')));
        $this->assertSame(__('images.type'), $this->rejection($this->upload('photo.png.php', $this->bytes('png', 800, 600))));
        // A GIF renamed to .jpg: the content decides, not the name.
        $this->assertSame(__('images.type'), $this->rejection($this->upload('renamed.jpg', $this->bytes('gif', 800, 600))));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_size_dimension_and_pixel_limits(): void
    {
        $this->assertStringContainsString('8 MB', $this->rejection(UploadedFile::fake()->create('huge.jpg', 9000, 'image/jpeg')));
        $this->assertStringContainsString('400 × 300', $this->rejection($this->image('jpg', 380, 380)), 'Long edge under 400');
        $this->assertStringContainsString('400 × 300', $this->rejection($this->image('jpg', 299, 800)), 'Short edge under 300 (portrait)');

        config(['marketplace.images.max_megapixels' => 0.4]);
        $this->assertStringContainsString('0.4 megapixels', $this->rejection($this->image('jpg', 800, 600)));
        $this->assertSame([], $this->storedFiles());
    }

    public function test_the_per_category_photo_limit(): void
    {
        $this->listing->category->update(['max_images' => 2]);
        $this->add($this->image());
        $this->add($this->image());

        $this->assertStringContainsString('at most 2 photos', $this->rejection($this->image()));
        $this->assertCount(4, $this->storedFiles());
    }

    public function test_a_file_that_fails_to_decode_leaves_nothing_behind(): void
    {
        $png = $this->bytes('png', 800, 600);
        $at = strpos($png, 'IDAT') + 20;
        $corrupt = substr($png, 0, $at).str_repeat("\xAB", 400).substr($png, $at + 400);

        $this->assertStringContainsString('could not be processed', $this->rejection($this->upload('broken.png', $corrupt)));
        $this->assertSame([], $this->storedFiles());
        $this->assertSame(0, ListingImage::count());
        $this->assertNull($this->listing->fresh()->cover_image_id);
    }

    // ---- Managing photos ------------------------------------------------------------------------

    public function test_remove_reorder_and_cover(): void
    {
        $actions = app(ManageListingImages::class);
        [$first, $second, $third] = [$this->add($this->image()), $this->add($this->image()), $this->add($this->image())];

        $actions->reorder($this->seller, $this->listing, [$third->id, $first->id, $second->id]);
        $this->assertSame([$third->id, $first->id, $second->id], $this->listing->images()->pluck('id')->all());

        $actions->setCover($this->seller, $this->listing, $second->id);
        $this->assertSame($second->id, $this->listing->fresh()->cover_image_id);

        // Removing the cover deletes its files and promotes the first remaining photo.
        $actions->remove($this->seller, $this->listing->fresh(), $second->id);
        $this->assertSame($third->id, $this->listing->fresh()->cover_image_id);
        $this->assertCount(4, $this->storedFiles());
        $this->assertFalse(Storage::disk(ListingsDisk::NAME)->exists($second->path('lg')));
    }

    public function test_ids_must_belong_to_the_listing(): void
    {
        $actions = app(ManageListingImages::class);
        $mine = $this->add($this->image());
        $otherListing = Listing::factory()->draft()->for($this->seller)->create();
        $theirs = $this->add($this->image(), $otherListing);

        foreach ([
            fn () => $actions->setCover($this->seller, $this->listing, $theirs->id),
            fn () => $actions->remove($this->seller, $this->listing, $theirs->id),
            fn () => $actions->reorder($this->seller, $this->listing, [$mine->id, $theirs->id]),
            fn () => $actions->reorder($this->seller, $this->listing, []),
        ] as $call) {
            try {
                $call();
                $this->fail('Foreign image id accepted');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNotNull($theirs->fresh());
    }

    public function test_only_the_owner_and_only_drafts(): void
    {
        try {
            app(ManageListingImages::class)->add(User::factory()->create(), $this->listing, $this->image());
            $this->fail('Another user added a photo');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        $this->listing->forceFill(['status' => ListingStatus::Active])->save();
        $this->expectException(ListingNotEditable::class);
        $this->add($this->image());
    }

    // ---- Storage, URLs and housekeeping ---------------------------------------------------------

    public function test_urls_come_from_the_disk_configuration(): void
    {
        $image = (new ListingImage)->forceFill(['disk' => ListingsDisk::NAME, 'directory' => '2026/10/7', 'basename' => str_repeat('a', 32), 'format' => 'webp']);

        config(['filesystems.disks.listings' => ['driver' => 'local', 'root' => storage_path('app/public/listings'), 'url' => 'https://media.example.com/listings']]);
        Storage::forgetDisk(ListingsDisk::NAME);

        $this->assertSame('https://media.example.com/listings/2026/10/7/'.str_repeat('a', 32).'-sm.webp', $image->url('sm'));
    }

    public function test_uploads_folder_gets_a_script_guard(): void
    {
        $root = public_path('uploads/test-'.uniqid());
        config(['filesystems.disks.listings' => ['driver' => 'local', 'root' => $root, 'url' => 'http://localhost/uploads', 'throw' => true]]);
        Storage::forgetDisk(ListingsDisk::NAME);

        try {
            $this->add($this->image());

            $guard = (string) file_get_contents($root.'/.htaccess');
            $this->assertStringContainsString('Require all denied', $guard);
            $this->assertStringContainsString('RemoveHandler .php', $guard);
            $this->assertMatchesRegularExpression('/FilesMatch "[^"]*php/', $guard);
        } finally {
            (new Filesystem)->deleteDirectory($root);
        }
    }

    public function test_storage_folder_outside_public_gets_no_guard(): void
    {
        $this->add($this->image());

        $this->assertFalse(Storage::disk(ListingsDisk::NAME)->exists(ListingsDisk::GUARD_FILE));
    }

    public function test_orphan_photos_are_pruned_after_the_ttl(): void
    {
        $attached = $this->add($this->image());
        $orphan = $this->add($this->image());
        $orphan->forceFill(['listing_id' => null])->save();

        $this->artisan('marketplace:prune-images')->assertSuccessful();
        $this->assertNotNull($orphan->fresh(), 'Younger than 24 h');

        $this->travel(25)->hours();
        $this->artisan('marketplace:prune-images')->expectsOutputToContain('Deleted 1')->assertSuccessful();

        $this->assertNull($orphan->fresh());
        $this->assertNotNull($attached->fresh());
        $this->assertEqualsCanonicalizing([$attached->path('lg'), $attached->path('sm')], $this->storedFiles());
    }
}
