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

    public function test_remembering_card_shows_whole_tribute_without_linking_to_a_page(): void
    {
        Storage::fake('public');
        $tribute = "1995 – 2019\nLine two\nLine three\nLine four\nForever in our hearts.";

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.memories.store'), [
            'kind' => Memory::KIND_REMEMBERING,
            'title' => 'Remembering our friend',
            'story' => $tribute,
            'photos' => [UploadedFile::fake()->image('friend.jpg')],
        ])->assertRedirect(route('admin.memories.index'));

        $memory = Memory::sole();

        $this->get(route('home'))
            ->assertSee('🕊️ Remembering')
            ->assertSee(e($tribute), false)
            ->assertDontSee(route('memories.show', $memory))
            ->assertDontSee('line-clamp-4', false);
    }

    public function test_remembering_posts_are_always_listed_first(): void
    {
        $this->travelTo(now()->subWeek());
        Memory::create(['kind' => Memory::KIND_REMEMBERING, 'title' => 'Remembering our friend', 'story' => 'Forever in our hearts']);
        $this->travelBack();
        Memory::create(['kind' => Memory::KIND_FUNNY, 'title' => 'Newest funny moment', 'story' => 'Ha']);

        $this->get(route('home'))->assertSeeInOrder(['Remembering our friend', 'Newest funny moment']);
        $this->get(route('memories.index'))->assertSeeInOrder(['Remembering our friend', 'Newest funny moment']);
    }

    public function test_remembering_post_allows_only_one_photo(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.memories.store'), [
            'kind' => Memory::KIND_REMEMBERING,
            'title' => 'Remembering our friend',
            'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])->assertSessionHasErrors(['photos' => 'A Remembering post can have only one photo.']);

        $this->assertDatabaseCount('memories', 0);
    }

    public function test_post_with_several_photos_cannot_become_remembering_until_photos_are_removed(): void
    {
        Storage::fake('public');
        $memory = $this->memoryWithPhotos(['one.jpg', 'two.jpg']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.memories.update', $memory), ['kind' => Memory::KIND_REMEMBERING, 'title' => 'Remembering our friend'])
            ->assertSessionHasErrors(['photos' => 'A Remembering post can have only one photo. Remove some first.']);

        $this->assertSame(Memory::KIND_MEMORY, $memory->fresh()->kind);
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
