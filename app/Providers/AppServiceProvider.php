<?php

namespace App\Providers;

use App\Models\User;
use App\Services\MainMenu;
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

        // The header menu: which links this visitor sees is decided in MainMenu, the layout only displays them.
        View::composer('layouts.app', function ($view): void {
            $view->with('menuSections', app(MainMenu::class)->sectionsFor(auth()->user()));
        });

        // Admins see a notification bell with the newest registrations on every page.
        View::composer('layouts.app', function ($view): void {
            if (! auth()->user()?->isAdmin()) {
                return;
            }

            $students = User::students();

            $view->with([
                'pendingRegistrations' => (clone $students)->where('status', User::STATUS_PENDING)->count(),
                'latestRegistrations' => (clone $students)->with('reviewer:id,name')->latest()->take(8)->get(),
            ]);
        });
    }
}
