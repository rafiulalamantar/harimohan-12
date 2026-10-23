<?php

namespace App\Http\Requests;

use App\Models\StudentField;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / edit an extra student field (admin/student-fields/form.blade.php). */
class StudentFieldRequest extends FormRequest
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
        $type = $this->input('type');

        return [
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(array_keys(StudentField::TYPES))],
            'options' => ['nullable', Rule::requiredIf(in_array($type, StudentField::CHOICE_TYPES, true)), 'string', 'max:2000'],
            'help_text' => ['nullable', 'string', 'max:500'],
            // An image-only field is nothing without its picture.
            'image' => ['nullable', Rule::requiredIf($type === 'image' && ! $this->keepsCurrentImage()), 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'options.required' => 'Add at least one choice for a dropdown, radio button or checkbox field.',
            'image.required' => 'Upload the image students should see.',
        ];
    }

    /**
     * The field's columns. Choices are typed one per line; an image-only field is never required.
     * The image file is stored by the controller.
     *
     * @return array<string, mixed>
     */
    public function fieldData(): array
    {
        $data = $this->safe()->except('image');

        $data['options'] = in_array($data['type'], StudentField::CHOICE_TYPES, true)
            ? collect(preg_split('/\r\n|\r|\n/', $data['options'] ?? ''))->map(fn ($option) => trim($option))->filter()->unique()->values()->all()
            : null;
        $data['is_required'] = $data['type'] !== 'image' && $this->boolean('is_required');
        $data['is_active'] = $this->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /** Editing a field that already has an image, and not removing it. */
    private function keepsCurrentImage(): bool
    {
        $field = $this->route('student_field');

        return $field instanceof StudentField && $field->image && ! $this->boolean('remove_image');
    }
}
