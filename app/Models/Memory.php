<?php

namespace App\Models;

use App\Exceptions\PhotoNotSavedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A post in the welcome page's Memories section: a type, a title, an optional story and up to MAX_PHOTOS pictures.
 * Each picture is a MemoryPhoto row; its file lives on the "public" disk under memories/.
 */
class Memory extends Model
{
    public const KIND_MEMORY = 'memory';

    public const KIND_FUNNY = 'funny';

    public const KIND_STORY = 'story';

    /** In memory of a friend who has passed away: shown in a calm style (grey badge, slower slideshow, no zoom). */
    public const KIND_REMEMBERING = 'remembering';

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_MEMORY => '📸 Memory',
        self::KIND_FUNNY => '😂 Funny moment',
        self::KIND_STORY => '📝 Story',
        self::KIND_REMEMBERING => '🕊️ Remembering',
    ];

    /**
     * Pictures one post may have. Vercel rejects request bodies over 4.5 MB, and the browser
     * shrinks each picture to about 0.5 MB, so six fit in one save.
     */
    public const MAX_PHOTOS = 6;

    /** How long each photo stays in the slideshow, in milliseconds. */
    private const SLIDE_MS_CARD = 4000;

    private const SLIDE_MS_POST_PAGE = 5000;

    /** Remembering posts slide more slowly, so the page feels calm. */
    private const SLIDE_MS_EXTRA_REMEMBERING = 2000;

    protected $fillable = ['user_id', 'kind', 'title', 'story'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<MemoryPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(MemoryPhoto::class)->orderBy('id');
    }

    /** Newest posts first, with their pictures loaded in one query. */
    public function scopeFeed(Builder $query): Builder
    {
        return $query->with('photos')->latest()->orderByDesc('id');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS[self::KIND_MEMORY];
    }

    public function isRemembering(): bool
    {
        return $this->kind === self::KIND_REMEMBERING;
    }

    /** Milliseconds each photo is shown: on a welcome-page card, or on the post's own page. */
    public function slideInterval(bool $onPostPage): int
    {
        $interval = $onPostPage ? self::SLIDE_MS_POST_PAGE : self::SLIDE_MS_CARD;

        return $this->isRemembering() ? $interval + self::SLIDE_MS_EXTRA_REMEMBERING : $interval;
    }

    /**
     * Saves uploaded pictures as new photos of this post. Files get random names, so a browser's
     * year-long cached copy of an old picture can never show up in place of a new one.
     *
     * All or nothing: if one picture can't be stored, the pictures already stored in this call are
     * deleted again and PhotoNotSavedException is thrown (call it inside a DB transaction, so the rows go too).
     *
     * @param  array<int, UploadedFile>  $files
     *
     * @throws PhotoNotSavedException
     */
    public function addPhotos(array $files): void
    {
        $storedPaths = [];

        foreach ($files as $file) {
            $path = $file->store('memories', 'public');

            if ($path === false) {
                Storage::disk('public')->delete($storedPaths);

                throw new PhotoNotSavedException;
            }

            $storedPaths[] = $path;
            $this->photos()->create(['path' => $path]);
        }
    }

    /**
     * Deletes the given photos and their files. Only this post's photos are looked up,
     * so an id belonging to another post is simply ignored.
     *
     * @param  array<int, int>  $photoIds
     */
    public function removePhotos(array $photoIds): void
    {
        $this->photos()->whereIn('id', $photoIds)->get()
            ->each(fn (MemoryPhoto $photo) => $photo->deleteWithFile());
    }

    /** Deletes the post together with all its photo files. */
    public function deleteWithPhotos(): void
    {
        $this->photos->each(fn (MemoryPhoto $photo) => $photo->deleteWithFile());
        $this->delete();
    }
}
