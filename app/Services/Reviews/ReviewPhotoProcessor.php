<?php

namespace App\Services\Reviews;

use App\Models\Review;
use App\Models\ReviewMedia;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a customer upload into a safe review photo.
 *
 * The file is decoded with GD and re-encoded as WebP, so nothing from the original survives: no EXIF/GPS, no
 * embedded payloads. The long edge is capped at 2000 px (with a 600 px thumbnail) and the EXIF orientation is
 * applied first, so phone photos are not stored sideways. Files get a random UUID name under reviews/{review_id}/.
 */
class ReviewPhotoProcessor
{
    public const MAX_PHOTOS = 4;

    public const MAX_EDGE = 2000;

    public const THUMB_EDGE = 600;

    /** Decoding cost guard: GD needs ~4 bytes per pixel, so 50 MP is ~200 MB (a 48 MP phone photo still fits). */
    public const MAX_PIXELS = 50_000_000;

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    private const QUALITY = 82;

    private const THUMB_QUALITY = 75;

    /**
     * Decode and re-encode one upload. Nothing is written yet.
     *
     * @return array{full: string, thumbnail: string, width: int, height: int}
     *
     * @throws InvalidReviewPhoto when the file is not a readable JPEG, PNG or WebP image
     */
    public function prepare(UploadedFile $file): array
    {
        if (! function_exists('imagewebp')) {
            throw new RuntimeException('GD with WebP support is required for review photos.');
        }

        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        // getimagesize() reads the real header, whatever the file is called or claims to be
        if ($info === false || ! in_array($info['mime'] ?? null, self::MIME_TYPES, true)) {
            throw new InvalidReviewPhoto('Not a JPEG, PNG or WebP image.');
        }

        if ($info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidReviewPhoto('Image dimensions out of range.');
        }

        $image = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            throw new InvalidReviewPhoto('The image could not be decoded.');
        }

        $full = $this->fit($image, self::MAX_EDGE);
        unset($image);

        if ($info['mime'] === 'image/jpeg') {
            $full = $this->orient($full, $this->exifOrientation($path));
        }

        $thumbnail = $this->fit($full, self::THUMB_EDGE);

        return [
            'full' => $this->encode($full, self::QUALITY),
            'thumbnail' => $this->encode($thumbnail, self::THUMB_QUALITY),
            'width' => imagesx($full),
            'height' => imagesy($full),
        ];
    }

    /**
     * Write prepared photos for a review and create their (pending) rows.
     *
     * @param  list<array{full: string, thumbnail: string, width: int, height: int}>  $photos
     * @param  list<string>  $written  receives every path written, so the caller can clean up on failure
     */
    public function store(Review $review, array $photos, array &$written = []): void
    {
        $disk = Storage::disk(ReviewMedia::DISK);

        foreach (array_values($photos) as $position => $photo) {
            $name = (string) Str::uuid();
            $path = "reviews/{$review->id}/{$name}.webp";
            $thumbnailPath = "reviews/{$review->id}/{$name}-thumb.webp";

            foreach ([$path => $photo['full'], $thumbnailPath => $photo['thumbnail']] as $target => $bytes) {
                if (! $disk->put($target, $bytes)) {
                    throw new RuntimeException("Could not write review photo {$target}.");
                }
                $written[] = $target;
            }

            $review->media()->create([
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'width' => $photo['width'],
                'height' => $photo['height'],
                'sort_order' => $position,
                'status' => ReviewMedia::STATUS_PENDING,
            ]);
        }
    }

    /** Scale down (never up) so the long edge is at most $edge. */
    private function fit(GdImage $image, int $edge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $edge / max($width, $height));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // Always draw onto a fresh canvas: a copy, even at 1:1, is what guarantees nothing but pixels survives
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function exifOrientation(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);

        return is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    }

    /** Apply an EXIF orientation (1–8) to the pixels, since the tag itself is dropped. */
    private function orient(GdImage $image, int $orientation): GdImage
    {
        $rotate = fn (GdImage $img, int $degrees): GdImage => imagerotate($img, $degrees, 0) ?: $img;

        switch ($orientation) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = $rotate($image, 180);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = $rotate($image, -90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = $rotate($image, -90);
                break;
            case 7:
                $image = $rotate($image, 90);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = $rotate($image, 90);
                break;
        }

        return $image;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $bytes = (string) ob_get_clean();

        if (! $ok || $bytes === '') {
            throw new RuntimeException('WebP encoding failed.');
        }

        return $bytes;
    }
}
