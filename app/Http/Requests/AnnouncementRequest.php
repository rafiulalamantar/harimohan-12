<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Create / edit a notice (admin/announcements/form.blade.php). */
class AnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:200'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'body' => ['required', 'string', 'max:10000'],
            'published_at' => ['nullable', 'date'],
            'is_pinned' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The notice's text columns. The banner file is stored by the controller.
     *
     * @return array<string, mixed>
     */
    public function announcementData(): array
    {
        return ['is_pinned' => $this->boolean('is_pinned')] + $this->safe()->except('banner');
    }
}
