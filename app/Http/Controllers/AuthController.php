<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\StudentField;
use App\Models\User;
use App\Services\PhotoThumbnail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Registration, login and logout for members and admins. */
class AuthController extends Controller
{
    public function showRegister(): View
    {
        return view('auth.register', ['fields' => StudentField::active()->get()]);
    }

    /** New members start as pending (database default) until an admin approves them. */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $data = $request->profileData();

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('photos', 'public');
            $data['photo_thumb'] = PhotoThumbnail::forUpload($request->file('photo_thumbnail'), $data['photo']);
        }

        $user = User::create($data);
        $user->saveFieldValues($request->fieldAnswers());

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard')
            ->with('success', 'Registration complete! You can pay for events once an admin approves you.');
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Allow login with either email or phone number (+880 / spaces are accepted for phone).
        $field = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $login = $field === 'phone' ? User::normalizePhone($request->login) : $request->login;

        // Always remembered: members stay logged in on this device for 10 days (config auth.guards.web.remember) or until Logout.
        if (! Auth::attempt([$field => $login, 'password' => $request->password], remember: true)) {
            return back()->withErrors(['login' => 'Incorrect email/phone or password.'])->onlyInput('login');
        }

        if (Auth::user()->status === User::STATUS_REJECTED) {
            Auth::logout();

            return back()->withErrors(['login' => 'Your registration was rejected. Please contact an admin.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(Auth::user()->isAdmin() ? route('admin.dashboard') : route('portal.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
