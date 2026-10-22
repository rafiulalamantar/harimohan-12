<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:refresh-upload-cache-headers')]
#[Description('Re-upload every file on the public disk so it picks up the disk\'s current Cache-Control header')]
class RefreshUploadCacheHeaders extends Command
{
    /**
     * Files uploaded before the disk set Cache-Control were stored with "no-cache",
     * so browsers re-download them on every page load. Writing each file again
     * in place applies the header without changing any stored path.
     */
    public function handle(): int
    {
        $disk = Storage::disk('public');
        $refreshed = 0;

        foreach ($disk->allFiles() as $path) {
            $contents = $disk->get($path);

            if ($contents === null || ! $disk->put($path, $contents)) {
                $this->warn("Skipped {$path}");

                continue;
            }

            $refreshed++;
        }

        $this->info("Refreshed {$refreshed} file(s).");

        return self::SUCCESS;
    }
}
