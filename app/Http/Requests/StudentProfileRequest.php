<?php

namespace App\Http\Requests;

use App\Models\StudentField;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The profile details a student fills in on both the registration form and the My Profile page:
 * name, phone, optional details, photo, and answers to the admin's extra student fields.
 *
 * {@see RegisterRequest} and {@see UpdateProfileRequest} add what differs between the two forms.
 */
abstract class StudentProfileRequest extends FormRequest
{
    /** @var Collection<int, StudentField>|null */
    private ?Collection $activeFields = null;

    /** Who may submit is decided by the route middleware (guest / auth). */
    public function authorize(): bool
    {
        return true;
    }

    /** People type phone numbers as "+880 1712-345678"; store them as 01712345678. */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => User::normalizePhone($this->input('phone'))]);
    }

    /**
     * Rules shared by both forms. The phone rule's uniqueness check differs, so subclasses pass it in.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function profileRules(mixed $uniquePhone): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:'.User::NAME_PATTERN],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', $uniquePhone],
            'blood_group' => ['nullable', Rule::in(User::BLOOD_GROUPS)],
            'profession' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            // The small card copy the browser makes from the photo (resources/js/photos/upload.js).
            'photo_thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg', 'max:512'],
            ...StudentField::rulesFor($this->studentFields()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Mobile number must be 11 digits, like 01XXXXXXXXX.',
            'name.regex' => User::NAME_MESSAGE,
        ];
    }

    /**
     * Error messages name the admin's field, e.g. "The Polo T-shirt size field is required."
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return StudentField::attributeNamesFor($this->studentFields());
    }

    /**
     * The extra student fields shown on the form (loaded once per request).
     *
     * @return Collection<int, StudentField>
     */
    public function studentFields(): Collection
    {
        return $this->activeFields ??= StudentField::active()->get();
    }

    /**
     * Answers to the extra fields, ready for {@see User::saveFieldValues()}.
     *
     * @return array<int, ?string>
     */
    public function fieldAnswers(): array
    {
        return StudentField::answersFrom($this->studentFields(), $this->validated('fields') ?? []);
    }

    /**
     * The validated columns of the users table (no field answers, no browser-made thumbnail).
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        return collect($this->validated())->except(['fields', 'photo', 'photo_thumbnail'])->all();
    }
}
