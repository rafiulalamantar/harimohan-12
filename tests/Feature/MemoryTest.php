<?php

namespace Tests\Feature;

use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class MemoryTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<int, string>  $photoNames */
    private function memoryWithPhotos(array $photoNames = ['one.jpg']): Memory
    {
        $memory = Memory::create(['kind' => Memory::KIND_MEMORY, 'title' => 'Picnic at Sonargaon', 'story' => 'Bus broke down']);

        foreach ($photoNames as $name) {
            $memory->photos()->create(['path' => UploadedFile::fake()->image($name)->store('memories', 'public')]);
        }

        return $memory;
    }

    public function test_admin_shares_memory_with_photos_and_story_lines_on_welcome_page(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.memories.store'), [
            'kind' => Memory::KIND_FUNNY,
            'title' => 'Sir caught us',
            'story' => "Line one\nLine two",
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect(route('admin.memories.index'));

        $memory = Memory::with('photos')->sole();
        $this->assertSame($admin->id, $memory->user_id);
        $this->assertSame("Line one\nLine two", $memory->story);
        $this->assertCount(2, $memory->photos);
        $memory->photos->each(fn ($photo) => Storage::disk('public')->assertExists($photo->path));

        $this->get(route('home'))
            ->assertSee('Sir caught us')
            ->assertSee('😂 Funny moment')
            ->assertSee('data-slideshow', false)
            ->assertSee($memory->photos[0]->url())
            ->assertSee($memory->photos[1]->url());
    }

    public function test_remembering_post_shows_in_the_calm_style(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.memories.store'), [
            'kind' => Memory::KIND_REMEMBERING,
            'title' => 'Remembering our friend',
            'story' => "1995 – 2019\nForever in our hearts.",
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertRedirect(route('admin.memories.index'));

        $this->get(route('memories.show', Memory::sole()))
            ->assertSee('🕊️ Remembering')
            ->assertSee('slides-calm', false)
            ->assertSee('data-interval="7000"', false);
    }

    public function test_guest_can_open_memory_and_see_every_photo(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos(['one.jpg', 'two.jpg']);

        $this->get(route('memories.show', $memory))
            ->assertOk()
            ->assertSee('Bus broke down')
            ->assertSee($memory->photos[0]->url())
            ->assertSee($memory->photos[1]->url());
    }

    /** Storage that saves the first file, then fails like an unreachable Supabase (the disk returns false). */
    private function storageFailsAfterFirstPhoto(): void
    {
        $realDisk = Storage::fake('public');
        $savedOnce = false;

        $failingDisk = Mockery::mock($realDisk)->makePartial();
        $failingDisk->shouldReceive('putFileAs')->andReturnUsing(function (...$arguments) use ($realDisk, &$savedOnce) {
            if ($savedOnce) {
                return false;
            }
            $savedOnce = true;

            return $realDisk->putFileAs(...$arguments);
        });

        Storage::set('public', $failingDisk);
    }

    public function test_failed_photo_upload_saves_nothing_and_keeps_what_admin_typed(): void
    {
        $this->storageFailsAfterFirstPhoto();

        $this->actingAs(User::factory()->admin()->create())
            ->from(route('admin.memories.create'))
            ->post(route('admin.memories.store'), [
                'kind' => Memory::KIND_MEMORY, 'title' => 'Sports day', 'story' => 'We won',
                'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ])
            ->assertRedirect(route('admin.memories.create'))
            ->assertSessionHasErrors(['photos' => "Couldn't save the photos. Please try again in a minute."])
            ->assertSessionHasInput('title', 'Sports day');

        $this->assertDatabaseCount('memories', 0);
        $this->assertDatabaseCount('memory_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('memories'));
    }

    public function test_failed_photo_upload_on_edit_keeps_photos_ticked_for_removal(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos(['keep.jpg']);
        $tickedPhoto = $memory->photos()->sole();
        $this->storageFailsAfterFirstPhoto();
        Storage::disk('public')->put($tickedPhoto->path, 'photo');

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.memories.update', $memory), [
                'kind' => Memory::KIND_MEMORY, 'title' => 'Picnic at Sonargaon',
                'remove_photos' => [$tickedPhoto->id],
                'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
            ])
            ->assertSessionHasErrors('photos');

        $this->assertModelExists($tickedPhoto);
        Storage::disk('public')->assertExists($tickedPhoto->path);
        $this->assertCount(1, $memory->photos()->get());
    }

    public function test_memory_story_is_escaped(): void
    {
        Memory::create(['kind' => Memory::KIND_STORY, 'title' => 'Tiffin', 'story' => '<script>alert(1)</script>']);

        $this->get(route('home'))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_memory_needs_a_story_or_a_photo(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.memories.store'), ['kind' => Memory::KIND_MEMORY, 'title' => 'Empty'])
            ->assertSessionHasErrors(['story' => 'Write a story or add at least one photo.']);

        $this->assertDatabaseCount('memories', 0);
    }

    public function test_memory_photo_limit_counts_photos_already_saved(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos(array_fill(0, Memory::MAX_PHOTOS, 'old.jpg'));

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.memories.update', $memory), [
                'kind' => Memory::KIND_MEMORY, 'title' => 'Picnic at Sonargaon',
                'photos' => [UploadedFile::fake()->image('new.jpg')],
            ])
            ->assertSessionHasErrors(['photos' => 'A memory can have at most 6 photos. Remove some first.']);

        $this->assertCount(Memory::MAX_PHOTOS, $memory->photos()->get());
    }

    public function test_admin_removes_one_photo_but_not_another_memorys_photo(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos(['keep.jpg', 'remove.jpg']);
        $otherPhoto = $this->memoryWithPhotos()->photos()->sole();
        [$kept, $removed] = $memory->photos;

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.memories.update', $memory), [
                'kind' => Memory::KIND_MEMORY, 'title' => 'Picnic at Sonargaon',
                'remove_photos' => [$removed->id, $otherPhoto->id],
            ])->assertRedirect(route('admin.memories.index'));

        $this->assertModelMissing($removed);
        Storage::disk('public')->assertMissing($removed->path);
        $this->assertModelExists($kept);
        $this->assertModelExists($otherPhoto);
        Storage::disk('public')->assertExists($otherPhoto->path);
    }

    public function test_deleting_memory_deletes_its_photo_files(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos();
        $path = $memory->photos()->sole()->path;

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.memories.destroy', $memory));

        $this->assertModelMissing($memory);
        $this->assertDatabaseCount('memory_photos', 0);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_student_cannot_post_memory(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.memories.store'), ['kind' => Memory::KIND_STORY, 'title' => 'Hack', 'story' => 'x'])
            ->assertForbidden();

        $this->assertDatabaseCount('memories', 0);
    }
}
