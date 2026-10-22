<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:make-photo-thumbnails')]
#[Description('Create the small card/avatar copy for every profile photo that does not have one yet')]
class MakePhotoThumbnails extends Command
{
    /**
     * Photos uploaded before thumbnails existed are full size (some 2 MB+), which made the welcome page slow.
     */
    public function handle(): int
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

        $this->info("Made {$made} thumbnail(s).");

        return self::SUCCESS;
    }
}
