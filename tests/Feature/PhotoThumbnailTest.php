<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoThumbnailTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_a_browser_thumbnail_the_server_makes_a_480px_one_for_the_directory(): void
    {
        Storage::fake('public');

        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'photo' => UploadedFile::fake()->image('me.jpg', 1200, 1600),
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'karim@example.com')->first();
        Storage::disk('public')->assertExists($user->photo_thumb);
        $this->assertSame([480, 640], array_slice(getimagesizefromstring(Storage::disk('public')->get($user->photo_thumb)), 0, 2));

        $user->forceFill(['status' => User::STATUS_APPROVED])->save();
        $this->get('/')->assertSee($user->photoThumbUrl(), false)->assertDontSee($user->photoUrl(), false);
    }

    public function test_the_thumbnail_made_by_the_browser_is_stored_next_to_the_photo(): void
    {
        // Vercel's PHP has no GD, so on the live site the browser's thumbnail is the one that gets used.
        Storage::fake('public');
        $thumbnail = UploadedFile::fake()->image('thumbnail.jpg', 480, 640);

        $this->post('/register', [
            'name' => 'Karim', 'email' => 'karim@example.com', 'phone' => '01712345679', 'ssc_batch' => 2012,
            'photo' => UploadedFile::fake()->image('me.jpg', 1200, 1600),
            'photo_thumbnail' => $thumbnail,
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect(route('portal.dashboard'));

        $user = User::where('email', 'karim@example.com')->first();
        $this->assertSame('photos/thumbs/'.pathinfo($user->photo, PATHINFO_FILENAME).'.jpg', $user->photo_thumb);
        $this->assertSame(file_get_contents($thumbnail->getRealPath()), Storage::disk('public')->get($user->photo_thumb));
    }

    public function test_a_new_profile_photo_replaces_the_old_photo_and_thumbnail(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/old.jpg', 'old');
        Storage::disk('public')->put('photos/thumbs/old.jpg', 'old-thumb');
        $student = User::factory()->create(['photo' => 'photos/old.jpg', 'photo_thumb' => 'photos/thumbs/old.jpg']);

        $this->actingAs($student)->put(route('portal.profile.update'), [
            'name' => $student->name, 'phone' => $student->phone,
            'photo' => UploadedFile::fake()->image('new.png', 800, 800),
        ])->assertSessionHas('success');

        $student->refresh();
        Storage::disk('public')->assertMissing(['photos/old.jpg', 'photos/thumbs/old.jpg']);
        Storage::disk('public')->assertExists([$student->photo, $student->photo_thumb]);
        $this->assertStringEndsWith('.jpg', $student->photo_thumb);
    }

    public function test_the_backfill_command_thumbnails_existing_photos_and_skips_unreadable_ones(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('photos', UploadedFile::fake()->image('big.jpg', 3000, 2000), 'big.jpg');
        Storage::disk('public')->put('photos/broken.jpg', 'not an image');
        $withPhoto = User::factory()->create(['photo' => 'photos/big.jpg']);
        $broken = User::factory()->create(['photo' => 'photos/broken.jpg']);

        $this->artisan('app:make-photo-thumbnails')->expectsOutput('Made 1 thumbnail(s).')->assertSuccessful();

        $this->assertSame('photos/thumbs/big.jpg', $withPhoto->fresh()->photo_thumb);
        $this->assertSame([480, 320], array_slice(getimagesizefromstring(Storage::disk('public')->get('photos/thumbs/big.jpg')), 0, 2));
        // No thumbnail: cards fall back to the full photo instead of a broken image.
        $this->assertNull($broken->fresh()->photo_thumb);
        $this->assertSame($broken->photoUrl(), $broken->fresh()->photoThumbUrl());
    }
}
