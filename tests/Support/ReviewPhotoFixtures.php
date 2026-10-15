<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/**
 * Real image uploads for the review-photo tests. They are written to temp files and wrapped in a real UploadedFile
 * (not UploadedFile::fake()), because a fake file reports its MIME type from the file name and would hide whether
 * the server sniffs the content.
 */
final class ReviewPhotoFixtures
{
    public static function jpeg(int $width = 2400, int $height = 1200, bool $withExif = false, int $orientation = 0, string $name = 'photo.jpg'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = (string) ob_get_clean();

        if ($withExif || $orientation > 0) {
            $bytes = self::injectExif($bytes, $orientation);
        }

        return self::upload($bytes, $name, 'image/jpeg');
    }

    public static function png(int $width = 800, int $height = 600, string $name = 'photo.png'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return self::upload((string) ob_get_clean(), $name, 'image/png');
    }

    public static function webp(int $width = 640, int $height = 480, string $name = 'photo.webp'): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagewebp($image);

        return self::upload((string) ob_get_clean(), $name, 'image/webp');
    }

    /** Arbitrary bytes under an image name: what a disguised upload looks like. */
    public static function disguised(string $content = "<?php echo 'not an image';", string $name = 'photo.jpg'): UploadedFile
    {
        return self::upload($content, $name, 'image/jpeg');
    }

    public static function upload(string $bytes, string $name, string $clientMime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'review-photo-');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $clientMime, null, true);
    }

    /**
     * Insert an APP1 Exif segment (camera make "SpyCam", GPS 45°30'N and, optionally, an Orientation tag) right after
     * the JPEG start-of-image marker.
     */
    public static function injectExif(string $jpeg, int $orientation = 0): string
    {
        $make = "SpyCam\0";
        $entries = $orientation > 0 ? 3 : 2;
        $ifd0End = 8 + 2 + $entries * 12 + 4;
        $makeOffset = $ifd0End;
        $gpsOffset = $makeOffset + 8;
        $rationalOffset = $gpsOffset + 2 + 2 * 12 + 4;

        $tiff = 'II'.pack('v', 42).pack('V', 8);
        $tiff .= pack('v', $entries);
        $tiff .= pack('vvVV', 0x010F, 2, strlen($make), $makeOffset);
        if ($orientation > 0) {
            $tiff .= pack('vvVvv', 0x0112, 3, 1, $orientation, 0);
        }
        $tiff .= pack('vvVV', 0x8825, 4, 1, $gpsOffset);
        $tiff .= pack('V', 0);
        $tiff .= $make."\0";
        $tiff .= pack('v', 2);
        $tiff .= pack('vvV', 0x0001, 2, 2)."N\0\0\0";
        $tiff .= pack('vvVV', 0x0002, 5, 3, $rationalOffset);
        $tiff .= pack('V', 0);
        $tiff .= pack('VVVVVV', 45, 1, 30, 1, 0, 1);

        $app1 = "Exif\0\0".$tiff;

        return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
    }
}
