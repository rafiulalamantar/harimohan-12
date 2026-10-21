<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * In production, send any plain-HTTP request to the HTTPS version of the same URL.
 *
 * bKash's live callback only works over HTTPS, and logins/passwords must never travel unencrypted.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
