<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // En local, alinear URLs (Ziggy/CSRF) con el host real (127.0.0.1, localhost o subcarpeta).
        if (! $this->app->runningInConsole() && $this->app->environment('local')) {
            $request = request();
            URL::forceRootUrl($request->getSchemeAndHttpHost().$request->getBasePath());
        }
    }
}
