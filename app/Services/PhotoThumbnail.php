<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes the small JPEG copy of a profile photo that directory cards and avatars show.
 * Cards are about 230 px wide, so 480 px stays sharp on phone screens at a fraction of the size.
 */
class PhotoThumbnail
{
    public const WIDTH = 480;

    private const QUALITY = 80;

    /**
     * Store the thumbnail the browser made (resources/js/app.js) next to the photo. Vercel's PHP has no GD,
     * so on the live site this is how new uploads get thumbnails; {@see create()} is the fallback.
     */
    public static function storeUploaded(UploadedFile $thumbnail, string $photoPath): ?string
    {
        return Storage::disk('public')->putFileAs(self::directory($photoPath), $thumbnail, self::fileName($photoPath)) ?: null;
    }

    /** The browser's thumbnail when it sent one, otherwise one made here with GD (null when that isn't possible). */
    public static function forUpload(?UploadedFile $thumbnail, string $photoPath): ?string
    {
        return $thumbnail ? self::storeUploaded($thumbnail, $photoPath) : self::create($photoPath);
    }

    /**
     * Store a thumbnail next to the photo (photos/thumbs/…jpg) and return its path,
     * or null when the photo can't be read, so callers fall back to the full photo.
     */
    public static function create(string $photoPath): ?string
    {
        try {
            $bytes = Storage::disk('public')->get($photoPath);
            $image = $bytes === null ? false : @imagecreatefromstring($bytes);

            if ($image === false) {
                return null;
            }

            $image = self::applyExifOrientation($image, $bytes);
            $width = imagesx($image);
            $height = imagesy($image);
            $scale = min(1, self::WIDTH / $width);

            $thumb = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            // White behind transparent PNGs instead of black.
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);

            ob_start();
            imagejpeg($thumb, null, self::QUALITY);
            $jpeg = ob_get_clean();

            $thumbPath = self::directory($photoPath).'/'.self::fileName($photoPath);

            return Storage::disk('public')->put($thumbPath, $jpeg) ? $thumbPath : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function directory(string $photoPath): string
    {
        return dirname($photoPath).'/thumbs';
    }

    private static function fileName(string $photoPath): string
    {
        return pathinfo($photoPath, PATHINFO_FILENAME).'.jpg';
    }

    /**
     * Phone photos are often stored sideways with an EXIF "rotate me" flag that browsers obey but GD ignores.
     */
    private static function applyExifOrientation(\GdImage $image, string $bytes): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));

        return match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
