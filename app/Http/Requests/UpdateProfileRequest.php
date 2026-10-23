<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * The My Profile form (portal/profile.blade.php). Email and SSC batch can't be changed here:
 * an admin changes the batch, and the email is the login.
 */
class UpdateProfileRequest extends StudentProfileRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules(Rule::unique('users')->ignore($this->user()->id));
    }
}
