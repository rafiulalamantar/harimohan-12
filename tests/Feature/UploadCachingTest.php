<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadCachingTest extends TestCase
{
    public function test_s3_uploads_are_cacheable_by_browsers(): void
    {
        putenv('PUBLIC_DISK_DRIVER=s3');
        $config = require config_path('filesystems.php');
        putenv('PUBLIC_DISK_DRIVER');

        $this->assertSame('public, max-age=31536000, immutable', $config['disks']['public']['options']['CacheControl']);
    }

    public function test_refresh_command_rewrites_every_upload_in_place(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/a.jpg', 'photo-a');
        Storage::disk('public')->put('announcements/b.png', 'banner-b');

        $this->artisan('app:refresh-upload-cache-headers')
            ->expectsOutput('Refreshed 2 file(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('photos/a.jpg');
        $this->assertSame('banner-b', Storage::disk('public')->get('announcements/b.png'));
    }
}
