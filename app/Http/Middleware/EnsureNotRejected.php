<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Ends the session of a student whose registration was rejected after they logged in. */
class EnsureNotRejected
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->status === User::STATUS_REJECTED) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['login' => 'Your registration was rejected. Please contact an admin.']);
        }

        return $next($request);
    }
}
