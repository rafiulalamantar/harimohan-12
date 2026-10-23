<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /**
     * Store a thumbnail an admin re-framed from the original photo. It gets a new file name because uploads are
     * cached as immutable for a year, so reusing the old name would keep showing the old framing.
     */
    public static function storeReframed(UploadedFile $thumbnail, string $photoPath): ?string
    {
        $fileName = pathinfo($photoPath, PATHINFO_FILENAME).'-'.Str::lower(Str::random(8)).'.jpg';

        return Storage::disk('public')->putFileAs(self::directory($photoPath), $thumbnail, $fileName) ?: null;
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

            // Shrink first and rotate the small copy: rotating a full 12 MP photo needs more memory than PHP allows.
            $orientation = self::exifOrientation($bytes);
            $width = imagesx($image);
            $height = imagesy($image);
            $sideways = in_array($orientation, [6, 8], true);
            $scale = min(1, self::WIDTH / ($sideways ? $height : $width));

            $thumb = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            // White behind transparent PNGs instead of black.
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, imagesx($thumb), imagesy($thumb), $width, $height);
            unset($image);

            $thumb = match ($orientation) {
                3 => imagerotate($thumb, 180, 0),
                6 => imagerotate($thumb, -90, 0),
                8 => imagerotate($thumb, 90, 0),
                default => $thumb,
            };

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
    private static function exifOrientation(string $bytes): int
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($bytes, "\xFF\xD8")) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));

        return (int) ($exif['Orientation'] ?? 1);
    }
}
