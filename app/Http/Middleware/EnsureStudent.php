<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudent
{
    /**
     * The student portal is for alumni accounts. Admin-only accounts that open a portal page
     * are sent to the admin panel; any other request from them is refused.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isStudent()) {
            return $next($request);
        }

        if ($request->isMethod('GET')) {
            return redirect()->route('admin.dashboard')->with('error', 'The student portal is only for alumni accounts.');
        }

        abort(403);
    }
}
