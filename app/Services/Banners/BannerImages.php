<?php

namespace App\Services\Banners;

use App\Support\ListingsDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Stores a banner image the way listing photos are stored (security doc §4): the real content type
 * and the header are checked before decoding, and the image is re-encoded without metadata, so the
 * uploaded file itself is never kept. Staff upload these, but a file from outside is still a file
 * from outside.
 */
class BannerImages
{
    public const FIELD = 'image';

    /** finfo MIME => getimagesize() type that must match it. */
    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * @return array{path: string, width: int, height: int}
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file): array
    {
        $this->check($file);

        try {
            $webp = config('marketplace.images.format') === 'webp'
                && (config('marketplace.images.driver') !== 'gd' || function_exists('imagewebp'));
            $driver = config('marketplace.images.driver') === 'imagick' ? ImagickDriver::class : GdDriver::class;
            $image = (new ImageManager($driver, autoOrientation: true, decodeAnimation: false))
                ->decodePath((string) $file->getRealPath())
                ->scaleDown((int) config('marketplace.banners.max_width'));
            $quality = (int) config('marketplace.banners.quality');
            $bytes = $image->encode($webp ? new WebpEncoder(quality: $quality, strip: true) : new JpegEncoder(quality: $quality, progressive: true, strip: true))->toString();

            $path = 'banners/'.bin2hex(random_bytes(16)).'.'.($webp ? 'webp' : 'jpg');
            ListingsDisk::ensureGuard();
            ListingsDisk::disk()->put($path, $bytes);

            return ['path' => $path, 'width' => $image->width(), 'height' => $image->height()];
        } catch (Throwable $e) {
            Log::warning('Banner image could not be processed', ['bytes' => $file->getSize(), 'error' => $e::class]);

            throw ValidationException::withMessages([self::FIELD => __('admin.banners.unreadable')]);
        }
    }

    public function delete(string $path): void
    {
        try {
            ListingsDisk::disk()->delete($path);
        } catch (Throwable) {
            // Best effort: a leftover file has no database row and is never shown.
        }
    }

    /** @throws ValidationException */
    private function check(UploadedFile $file): void
    {
        $path = (string) $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: null;
        $info = @getimagesize($path);
        if (! isset(self::ALLOWED[$mime]) || $info === false || $info[2] !== self::ALLOWED[$mime]) {
            throw ValidationException::withMessages([self::FIELD => __('images.type')]);
        }
        $megapixels = (float) config('marketplace.images.max_megapixels');
        if ($megapixels * 1_000_000 < $info[0] * $info[1]) {
            throw ValidationException::withMessages([self::FIELD => __('images.too_many_pixels', ['mp' => $megapixels])]);
        }
    }
}
