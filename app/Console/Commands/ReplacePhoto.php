<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

#[Signature('app:replace-photo {photo : Stored path of the photo to replace, e.g. photos/abc.heic} {file : Local image file to use instead}')]
#[Description('Swap a member\'s stored photo for a local image file (e.g. a HEIC photo converted to JPEG)')]
class ReplacePhoto extends Command
{
    /**
     * Stores the new file under a fresh random name, points the member at it,
     * and only then deletes the old file, so a failure never leaves them without a photo.
     */
    public function handle(): int
    {
        $user = User::where('photo', $this->argument('photo'))->first();
        $file = $this->argument('file');

        if (! $user) {
            $this->error('No member has that photo.');

            return self::FAILURE;
        }

        if (! is_file($file) || ! in_array(mime_content_type($file), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->error('The file must be a JPG, PNG or WebP image.');

            return self::FAILURE;
        }

        $oldPhotos = array_filter([$user->photo, $user->photo_thumb]);
        $newPhoto = Storage::disk('public')->putFile('photos', new File($file));

        if (! $newPhoto) {
            $this->error('Upload failed; nothing was changed.');

            return self::FAILURE;
        }

        $user->update(['photo' => $newPhoto, 'photo_thumb' => PhotoThumbnail::create($newPhoto)]);
        Storage::disk('public')->delete($oldPhotos);

        $this->info("{$user->name}: {$oldPhotos[0]} → {$newPhoto}");

        return self::SUCCESS;
    }
}
