<?php

namespace App\Services\Images;

use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Support\ListingsDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Listing photo pipeline (security doc §4, LST-02, CD-10). The upload is validated (extension,
 * MIME from content, image header, size, dimensions, pixel count before decoding), decoded,
 * auto-oriented and re-encoded into two variants without metadata. The original is never kept,
 * which removes EXIF/GPS and any payload hidden in the file. Any failure leaves nothing behind.
 */
class ListingImageProcessor
{
    public const FIELD = 'image';

    /** finfo MIME => getimagesize() type that must match it. */
    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * @throws ValidationException
     */
    public function store(User $user, Listing $listing, UploadedFile $file): ListingImage
    {
        $this->validateFile($file);
        $this->checkCount($listing);
        [$width, $height] = $this->checkHeader($file);

        $written = [];
        try {
            $format = $this->format();
            $basename = bin2hex(random_bytes(16));
            $directory = now()->format('Y/m').'/'.$listing->id;
            $source = $this->manager()->decodePath($file->getRealPath());

            ListingsDisk::ensureGuard();
            $variants = [];
            foreach ((array) config('marketplace.images.variants') as $name => $settings) {
                $variants[$name] = $this->variant($source, (int) $settings['max'], (int) $settings['quality'], $format);
                $path = "{$directory}/{$basename}-{$name}.{$format}";
                ListingsDisk::disk()->put($path, $variants[$name]['bytes']);
                $written[] = $path;
            }
        } catch (Throwable $e) {
            $this->discard($written);
            Log::warning('Listing image could not be processed', [
                'listing_id' => $listing->id,
                'bytes' => $file->getSize(),
                'mime' => $this->detectedMime($file),
                'header_size' => "{$width}x{$height}",
                'error' => $e::class,
            ]);

            throw ValidationException::withMessages([self::FIELD => __('images.processing_failed', ['mb' => intdiv((int) config('marketplace.images.max_upload_kb'), 1024)])]);
        }

        // Attach under a lock and count again, so parallel uploads cannot exceed the limit.
        try {
            return DB::transaction(function () use ($user, $listing, $directory, $basename, $format, $variants): ListingImage {
                Listing::whereKey($listing->id)->lockForUpdate()->first();
                $this->checkCount($listing);

                $large = $variants['lg'];
                $image = new ListingImage;
                $image->forceFill([
                    'listing_id' => $listing->id,
                    'user_id' => $user->id,
                    'disk' => ListingsDisk::NAME,
                    'directory' => $directory,
                    'basename' => $basename,
                    'format' => $format,
                    'width' => $large['width'],
                    'height' => $large['height'],
                    'bytes' => array_sum(array_map(fn (array $variant) => strlen($variant['bytes']), $variants)),
                    'sha256' => hash('sha256', $large['bytes']),
                    'sort_order' => (int) $listing->images()->max('sort_order') + 1,
                ])->save();

                if ($listing->cover_image_id === null) {
                    $listing->forceFill(['cover_image_id' => $image->id])->save();
                }

                return $image;
            });
        } catch (Throwable $e) {
            $this->discard($written);

            throw $e;
        }
    }

    /** @throws ValidationException */
    private function validateFile(UploadedFile $file): void
    {
        Validator::make([self::FIELD => $file], [
            self::FIELD => ['required', 'file', 'extensions:jpg,jpeg,png,webp', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('marketplace.images.max_upload_kb')],
        ], [
            self::FIELD.'.extensions' => __('images.type'),
            self::FIELD.'.mimes' => __('images.type'),
            self::FIELD.'.max' => __('images.too_large', ['mb' => intdiv((int) config('marketplace.images.max_upload_kb'), 1024)]),
        ])->validate();
    }

    /**
     * Checks the real content type and the header before anything is decoded (decompression bombs).
     *
     * @return array{0: int, 1: int}
     *
     * @throws ValidationException
     */
    private function checkHeader(UploadedFile $file): array
    {
        $mime = $this->detectedMime($file);
        $info = @getimagesize((string) $file->getRealPath());

        if (! isset(self::ALLOWED[$mime]) || $info === false || $info[2] !== self::ALLOWED[$mime]) {
            throw ValidationException::withMessages([self::FIELD => __('images.type')]);
        }

        [$width, $height] = $info;
        $minWidth = (int) config('marketplace.images.min_width');
        $minHeight = (int) config('marketplace.images.min_height');
        // Portrait photos count too: compare the long and short edges.
        if (max($width, $height) < max($minWidth, $minHeight) || min($width, $height) < min($minWidth, $minHeight)) {
            throw ValidationException::withMessages([self::FIELD => __('images.too_small', ['width' => $minWidth, 'height' => $minHeight])]);
        }
        if ($width * $height > (float) config('marketplace.images.max_megapixels') * 1_000_000) {
            throw ValidationException::withMessages([self::FIELD => __('images.too_many_pixels', ['mp' => config('marketplace.images.max_megapixels')])]);
        }

        return [$width, $height];
    }

    /** @throws ValidationException */
    private function checkCount(Listing $listing): void
    {
        $max = $listing->category->max_images;

        if ($listing->images()->count() >= $max) {
            throw ValidationException::withMessages([self::FIELD => __('images.limit', ['max' => $max])]);
        }
    }

    /**
     * @return array{bytes: string, width: int, height: int}
     */
    private function variant(ImageInterface $source, int $max, int $quality, string $format): array
    {
        $image = (clone $source)->scaleDown($max, $max);
        $encoder = $format === 'webp'
            ? new WebpEncoder(quality: $quality, strip: true)
            : new JpegEncoder(quality: $quality, progressive: true, strip: true);

        return ['bytes' => $image->encode($encoder)->toString(), 'width' => $image->width(), 'height' => $image->height()];
    }

    /** WebP unless configured otherwise or unsupported by GD on this host (then JPEG). */
    private function format(): string
    {
        $webp = config('marketplace.images.format') === 'webp'
            && (config('marketplace.images.driver') !== 'gd' || function_exists('imagewebp'));

        return $webp ? 'webp' : 'jpg';
    }

    private function manager(): ImageManager
    {
        return new ImageManager(config('marketplace.images.driver') === 'imagick'
            ? Driver::class
            : \Intervention\Image\Drivers\Gd\Driver::class, autoOrientation: true, decodeAnimation: false);
    }

    private function detectedMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return null;
        }

        return (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: null;
    }

    /** @param  list<string>  $paths */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                ListingsDisk::disk()->delete($path);
            } catch (Throwable) {
                // Best effort: a leftover variant has no database row and is never served.
            }
        }
    }
}
