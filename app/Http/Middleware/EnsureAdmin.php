<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Students who open an admin page (e.g. one whose admin access was just removed) go back to their portal;
     * any other request without admin access is refused.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin()) {
            return $next($request);
        }

        if ($user?->isStudent() && $request->isMethod('GET')) {
            return redirect()->route('portal.dashboard')->with('error', "You don't have access to the admin panel.");
        }

        abort(403);
    }
}
