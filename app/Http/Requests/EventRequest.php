<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / edit an event (admin/events/form.blade.php). */
class EventRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'event_date' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:200'],
            'fee' => ['required', 'numeric', 'min:1', 'max:999999'],
            'payment_deadline' => ['nullable', 'date', Rule::when($this->filled('event_date'), ['before_or_equal:event_date'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['payment_deadline.before_or_equal' => 'The payment deadline must be on or before the event date.'];
    }

    /**
     * The event's columns; an unticked "Active" checkbox is sent as nothing, so it becomes false here.
     *
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return ['is_active' => $this->boolean('is_active')] + $this->validated();
    }
}
