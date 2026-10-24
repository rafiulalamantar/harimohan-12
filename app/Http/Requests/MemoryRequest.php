<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit a memory post (admin/memories/form.blade.php).
 *
 * The form sends: kind, title, story, photos[] (new pictures), photo_thumbnails[] (their card-size copies, made by
 * the browser) and, when editing, remove_photos[] (ids of ticked photos).
 */
class MemoryRequest extends FormRequest
{
    /** Only admins reach this request (the "admin" route middleware). */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(Memory::KINDS))],
            'title' => ['required', 'string', 'max:150'],
            'story' => ['nullable', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:'.$this->maxPhotos()],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'photo_thumbnails' => ['nullable', 'array'],
            'photo_thumbnails.*' => ['image', 'mimes:jpg,jpeg', 'max:512'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.max' => $this->tooManyPhotosMessage(),
            'photos.*.image' => 'Each file must be a photo.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, WebP or GIF.',
            'photos.*.max' => 'Each photo must be 4 MB or smaller.',
        ];
    }

    /**
     * Checks that need the post's saved photos, run after the rules above pass:
     * 1. the post will not have more photos than its type allows (6, or 1 for Remembering);
     * 2. the post will have a story or at least one photo.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $photoCount = $this->photoCountAfterSave();

                if ($photoCount > $this->maxPhotos()) {
                    $validator->errors()->add('photos', $this->tooManyPhotosMessage().' Remove some first.');
                }

                if ($photoCount === 0 && blank($this->input('story'))) {
                    $validator->errors()->add('story', 'Write a story or add at least one photo.');
                }
            },
        ];
    }

    /**
     * The post's text columns.
     *
     * @return array{kind: string, title: string, story: ?string}
     */
    public function memoryData(): array
    {
        return [
            'kind' => $this->validated('kind'),
            'title' => $this->validated('title'),
            'story' => $this->validated('story'),
        ];
    }

    /** @return array<int, UploadedFile> */
    public function newPhotos(): array
    {
        return $this->file('photos', []);
    }

    /**
     * The browser's card-size copies of newPhotos(), in the same order. The browser sends one for every photo or
     * none at all, so a count that doesn't match means something went wrong: then no copies are used.
     *
     * @return array<int, UploadedFile>
     */
    public function newPhotoCardCopies(): array
    {
        $copies = $this->file('photo_thumbnails', []);

        return count($copies) === count($this->newPhotos()) ? array_values($copies) : [];
    }

    /**
     * Ids of the saved photos the admin ticked "Remove" on. Read from the input (not validated()),
     * because after() calls this too, and validated() inside after() would run the validation again.
     *
     * @return array<int, int>
     */
    public function photoIdsToRemove(): array
    {
        return array_map('intval', (array) $this->input('remove_photos', []));
    }

    /** The photo limit for the type chosen on the form. */
    private function maxPhotos(): int
    {
        return Memory::maxPhotosFor((string) $this->input('kind'));
    }

    private function tooManyPhotosMessage(): string
    {
        return $this->input('kind') === Memory::KIND_REMEMBERING
            ? 'A Remembering post can have only one photo.'
            : 'A memory can have at most '.$this->maxPhotos().' photos.';
    }

    /** Photos the post will have once saved: the ones it keeps plus the new uploads. */
    private function photoCountAfterSave(): int
    {
        $memory = $this->route('memory'); // null when creating a new post

        $keptPhotos = $memory instanceof Memory
            ? $memory->photos()->whereNotIn('id', $this->photoIdsToRemove())->count()
            : 0;

        return $keptPhotos + count($this->newPhotos());
    }
}
