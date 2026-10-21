<?php

namespace App\Http\Controllers;

use App\Models\StudentField;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register', ['fields' => StudentField::active()->get()]);
    }

    public function register(Request $request)
    {
        $fields = StudentField::active()->get();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
            'ssc_batch' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
            'roll_no' => ['nullable', 'string', 'max:30'],
            'profession' => ['nullable', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'present_address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'password' => ['required', 'confirmed', Password::min(8)],
            ...StudentField::rulesFor($fields),
        ], [
            'phone.regex' => 'Enter a valid Bangladeshi mobile number (01XXXXXXXXX).',
        ], StudentField::attributeNamesFor($fields));

        $answers = $data['fields'] ?? [];
        unset($data['fields']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
        }

        $user = User::create($data); // role=student, status=pending by DB default
        $user->saveFieldValues($answers);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('success', 'Registration complete! You can pay for events once an admin approves you.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Allow login with either email or phone number.
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (! Auth::attempt([$field => $request->login, 'password' => $request->password], $request->boolean('remember'))) {
            return back()->withErrors(['login' => 'Incorrect email/phone or password.'])->onlyInput('login');
        }

        if (Auth::user()->status === User::STATUS_REJECTED) {
            Auth::logout();

            return back()->withErrors(['login' => 'Your registration was rejected. Please contact an admin.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->isAdmin() ? route('admin.dashboard') : route('portal.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
