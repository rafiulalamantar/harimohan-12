<?php

namespace App\Console\Commands;

use App\Models\MemoryPhoto;
use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-photo-thumbnails')]
#[Description('Create the small card copy for every profile photo and memory photo that does not have one yet')]
class MakePhotoThumbnails extends Command
{
    /**
     * Photos uploaded before card copies existed are full size (some 2 MB+), which made pages slow.
     * Needs PHP's GD extension, so run it from a computer (Vercel's PHP has none), e.g. with --env=production.
     */
    public function handle(): int
    {
        $this->makeProfilePhotoThumbnails();
        $this->makeMemoryPhotoThumbnails();

        return self::SUCCESS;
    }

    private function makeProfilePhotoThumbnails(): void
    {
        $made = 0;

        User::whereNotNull('photo')->whereNull('photo_thumb')->each(function (User $user) use (&$made) {
            $thumb = PhotoThumbnail::create($user->photo);

            if (! $thumb) {
                $this->warn("Skipped {$user->name}: {$user->photo} could not be read.");

                return;
            }

            $user->update(['photo_thumb' => $thumb]);
            $made++;
        });

        $this->info("Made {$made} profile photo thumbnail(s).");
    }

    private function makeMemoryPhotoThumbnails(): void
    {
        $made = 0;

        MemoryPhoto::whereNull('thumb_path')->each(function (MemoryPhoto $photo) use (&$made) {
            $thumb = PhotoThumbnail::create($photo->path, PhotoThumbnail::MEMORY_CARD_WIDTH);

            if (! $thumb) {
                $this->warn("Skipped memory photo {$photo->id}: {$photo->path} could not be read.");

                return;
            }

            $photo->update(['thumb_path' => $thumb]);
            $made++;
        });

        $this->info("Made {$made} memory photo card copies.");
    }
}
