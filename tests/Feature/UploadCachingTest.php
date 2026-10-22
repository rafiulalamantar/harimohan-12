<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadCachingTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_replace_photo_swaps_a_members_photo_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/old.heic', 'heic-bytes');
        $user = User::factory()->create(['photo' => 'photos/old.heic']);
        $jpeg = UploadedFile::fake()->image('converted.jpg');

        $this->artisan('app:replace-photo', ['photo' => 'photos/old.heic', 'file' => $jpeg->getRealPath()])->assertSuccessful();

        $newPhoto = $user->fresh()->photo;
        $this->assertStringStartsWith('photos/', $newPhoto);
        $this->assertStringEndsWith('.jpg', $newPhoto);
        Storage::disk('public')->assertExists($newPhoto);
        Storage::disk('public')->assertMissing('photos/old.heic');
    }

    public function test_replace_photo_refuses_unknown_photos_and_non_web_images(): void
    {
        Storage::fake('public');
        User::factory()->create(['photo' => 'photos/old.heic']);

        $this->artisan('app:replace-photo', ['photo' => 'photos/nobody.jpg', 'file' => base_path('tests/fixtures/photo.heic')])->assertFailed();
        $this->artisan('app:replace-photo', ['photo' => 'photos/old.heic', 'file' => base_path('tests/fixtures/photo.heic')])->assertFailed();
        $this->assertDatabaseHas('users', ['photo' => 'photos/old.heic']);
    }
}
