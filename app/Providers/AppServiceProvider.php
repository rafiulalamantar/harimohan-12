<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every generated link (bKash callback, password reset emails) uses https:// in production.
        URL::forceHttps($this->app->isProduction());

        // Admins see a notification bell with the newest registrations on every page.
        View::composer('layouts.app', function ($view): void {
            if (! auth()->user()?->isAdmin()) {
                return;
            }

            $students = User::where('role', User::ROLE_STUDENT);

            $view->with([
                'pendingRegistrations' => (clone $students)->where('status', User::STATUS_PENDING)->count(),
                'latestRegistrations' => (clone $students)->with('reviewer:id,name')->latest()->take(8)->get(),
            ]);
        });
    }
}
