<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/** The public registration form (auth/register.blade.php). */
class RegisterRequest extends StudentProfileRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules('unique:users,phone'),
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'ssc_batch' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
