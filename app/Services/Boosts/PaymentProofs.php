<?php

namespace App\Services\Boosts;

use App\Models\BoostOrder;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Proof of payment for promotion orders (B7, owner 2026-10-24). The seller attaches up to
 * `boosts.proof_max_files` slips (JPG, PNG, WebP or PDF, `proof_max_kb` each) to an unpaid order.
 * Files go to the private disk and are never given a public address:
 * - images are decoded and re-encoded as JPEG without metadata (no EXIF/GPS, no hidden payload);
 * - PDFs are checked by content and header, refused when they carry scripts or embedded files, and
 *   are only ever sent as downloads.
 * A proof helps staff find the transfer; it never confirms a payment by itself.
 */
class PaymentProofs
{
    public const FIELD = 'proof';

    public const DISK = 'local';

    /** finfo MIME => kind. Images must also pass getimagesize() with the same type. */
    private const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
        'application/pdf' => null,
    ];

    /** PDF features that can run code or carry other files when opened in a reader. */
    private const PDF_REFUSED = ['/JavaScript', '/JS', '/Launch', '/EmbeddedFile', '/RichMedia', '/XFA'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws ValidationException
     */
    public function store(User $seller, BoostOrder $order, UploadedFile $file, ?string $transferDate = null, ?string $transferNote = null): PaymentProof
    {
        $this->assertOpen($seller, $order);
        $this->checkCount($order);
        $this->validateFile($file);
        $mime = $this->detectedMime($file);
        [$kind, $bytes] = $mime === 'application/pdf' ? [PaymentProof::PDF, $this->checkPdf($file)] : [PaymentProof::IMAGE, $this->reencode($file, $mime)];

        $path = 'payment-proofs/'.$order->id.'/'.bin2hex(random_bytes(16)).($kind === PaymentProof::PDF ? '.pdf' : '.jpg');
        $this->disk()->put($path, $bytes);

        try {
            return DB::transaction(function () use ($seller, $order, $file, $kind, $bytes, $path, $transferDate, $transferNote): PaymentProof {
                // Count again under a lock, so parallel uploads cannot exceed the limit.
                $locked = BoostOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                $this->assertOpen($seller, $locked);
                $this->checkCount($locked);

                $proof = new PaymentProof;
                $proof->forceFill([
                    'boost_order_id' => $locked->id,
                    'user_id' => $seller->id,
                    'kind' => $kind,
                    'path' => $path,
                    'original_name' => self::displayName($file->getClientOriginalName(), $kind),
                    'bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes),
                ])->save();

                $locked->forceFill(array_filter([
                    'proof_at' => now(),
                    'transfer_date' => $transferDate,
                    'transfer_note' => $transferNote,
                ], fn ($value) => $value !== null))->save();
                $this->audit->record($seller, 'boost_order.proof_added', $locked, null, ['order' => $locked->reference, 'proof' => $proof->id, 'kind' => $kind]);

                return $proof;
            });
        } catch (Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }
    }

    /** The seller removes a proof while the order is unpaid. */
    public function remove(User $seller, PaymentProof $proof): void
    {
        $order = $proof->order;
        $this->assertOpen($seller, $order);

        DB::transaction(function () use ($seller, $proof, $order): void {
            $proof->delete();
            $order->forceFill(['proof_at' => $order->proofs()->max('created_at')])->save();
            $this->audit->record($seller, 'boost_order.proof_removed', $order, null, ['order' => $order->reference, 'proof' => $proof->id]);
        });
        $this->disk()->delete($proof->path);
    }

    /** Images are shown in the page; PDFs are always downloaded, never rendered by the site. */
    public function response(PaymentProof $proof): StreamedResponse
    {
        abort_unless($this->disk()->exists($proof->path), 404);

        return $this->disk()->response($proof->path, $proof->original_name, [
            'Content-Type' => $proof->isPdf() ? 'application/pdf' : 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ], $proof->isPdf() ? 'attachment' : 'inline');
    }

    /** Retention: removes an order's files and rows (returns how many). */
    public function purge(BoostOrder $order): int
    {
        $count = 0;
        foreach ($order->proofs as $proof) {
            $this->disk()->delete($proof->path);
            $proof->delete();
            $count++;
        }

        return $count;
    }

    /** "Bank slip 12-10.jpg" without paths or control characters, at most 120 characters. */
    public static function displayName(string $name, string $kind): string
    {
        $base = (string) preg_replace('/[^\p{L}\p{N} ._()-]+/u', '', pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME));
        $base = trim(mb_substr($base, 0, 100), ' .');

        return ($base === '' ? 'proof' : $base).($kind === PaymentProof::PDF ? '.pdf' : '.jpg');
    }

    /** @throws ValidationException */
    private function assertOpen(User $seller, BoostOrder $order): void
    {
        if ($order->user_id !== $seller->id || ! $order->status->isOpen()) {
            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.not_open')]);
        }
    }

    /** @throws ValidationException */
    private function checkCount(BoostOrder $order): void
    {
        $max = (int) config('marketplace.boosts.proof_max_files');
        if ($order->proofs()->count() >= $max) {
            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.limit', ['max' => $max])]);
        }
    }

    /** @throws ValidationException */
    private function validateFile(UploadedFile $file): void
    {
        $maxKb = (int) config('marketplace.boosts.proof_max_kb');
        Validator::make([self::FIELD => $file], [
            self::FIELD => ['required', 'file', 'extensions:jpg,jpeg,png,webp,pdf', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$maxKb],
        ], [
            self::FIELD.'.extensions' => __('boosts.proof.type'),
            self::FIELD.'.mimes' => __('boosts.proof.type'),
            self::FIELD.'.max' => __('boosts.proof.too_large', ['mb' => intdiv($maxKb, 1024)]),
        ])->validate();

        if (! array_key_exists((string) $this->detectedMime($file), self::ALLOWED)) {
            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.type')]);
        }
    }

    /**
     * Checks the header and pixel count before decoding, then re-encodes without metadata.
     *
     * @throws ValidationException
     */
    private function reencode(UploadedFile $file, ?string $mime): string
    {
        $info = @getimagesize((string) $file->getRealPath());
        if ($mime === null || $info === false || $info[2] !== self::ALLOWED[$mime]) {
            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.type')]);
        }
        if ((float) config('marketplace.images.max_megapixels') * 1_000_000 < $info[0] * $info[1]) {
            throw ValidationException::withMessages([self::FIELD => __('images.too_many_pixels', ['mp' => config('marketplace.images.max_megapixels')])]);
        }

        try {
            $max = (int) config('marketplace.boosts.proof_max_px');

            return $this->manager()->decodePath((string) $file->getRealPath())->scaleDown($max, $max)
                ->encode(new JpegEncoder(quality: 85, strip: true))->toString();
        } catch (Throwable $e) {
            Log::warning('Payment proof image could not be processed', ['bytes' => $file->getSize(), 'mime' => $mime, 'error' => $e::class]);

            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.unreadable')]);
        }
    }

    /**
     * A real PDF without active content. Compressed object streams are not unpacked, so this is a
     * filter, not a guarantee; PDFs are therefore also only ever downloaded.
     *
     * @throws ValidationException
     */
    private function checkPdf(UploadedFile $file): string
    {
        $bytes = (string) file_get_contents((string) $file->getRealPath());
        if (! str_starts_with($bytes, '%PDF-') || ! str_contains(substr($bytes, -1024), '%%EOF')) {
            throw ValidationException::withMessages([self::FIELD => __('boosts.proof.unreadable')]);
        }
        foreach (self::PDF_REFUSED as $marker) {
            if (preg_match('#'.preg_quote($marker, '#').'(?![A-Za-z])#', $bytes) === 1) {
                throw ValidationException::withMessages([self::FIELD => __('boosts.proof.pdf_active')]);
            }
        }

        return $bytes;
    }

    private function manager(): ImageManager
    {
        return new ImageManager(config('marketplace.images.driver') === 'imagick' ? ImagickDriver::class : GdDriver::class,
            autoOrientation: true, decodeAnimation: false);
    }

    private function detectedMime(UploadedFile $file): ?string
    {
        $path = $file->getRealPath();

        return $path === false ? null : ((new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: null);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }
}
