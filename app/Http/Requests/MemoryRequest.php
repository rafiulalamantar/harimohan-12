<?php

namespace App\Http\Requests;

use App\Models\Memory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Create / edit a memory post (admin/memories/form.blade.php). */
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
            'photos' => ['nullable', 'array', 'max:'.Memory::MAX_PHOTOS],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'photos.max' => 'A memory can have at most '.Memory::MAX_PHOTOS.' photos.',
            'photos.*.image' => 'Each file must be a photo.',
            'photos.*.mimes' => 'Photos must be JPG, PNG, WebP or GIF.',
            'photos.*.max' => 'Each photo must be 4 MB or smaller.',
        ];
    }

    /**
     * Checks that need the post's current photos: the total stays within MAX_PHOTOS,
     * and a post always has a story or at least one photo.
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

                $photoCount = $this->keptPhotoCount() + count($this->file('photos', []));

                if ($photoCount > Memory::MAX_PHOTOS) {
                    $validator->errors()->add('photos', 'A memory can have at most '.Memory::MAX_PHOTOS.' photos. Remove some first.');
                }

                if ($photoCount === 0 && blank($this->input('story'))) {
                    $validator->errors()->add('story', 'Write a story or add at least one photo.');
                }
            },
        ];
    }

    /**
     * The text columns. Photos are stored by the controller.
     *
     * @return array{kind: string, title: string, story: ?string}
     */
    public function memoryData(): array
    {
        return $this->safe()->only(['kind', 'title']) + ['story' => $this->validated('story')];
    }

    /** Photos the post already has that the admin did not tick to remove (0 for a new post). */
    private function keptPhotoCount(): int
    {
        $memory = $this->route('memory');

        if (! $memory instanceof Memory) {
            return 0;
        }

        return $memory->photos()->whereNotIn('id', $this->input('remove_photos', []))->count();
    }
}
