<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (! file_exists(storage_path('installed'))) {
            // Guarantee file-based session and cache during installation (no DB queries before migration)
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);

            // Guarantee valid encryption key if missing
            if (empty(config('app.key')) || config('app.key') === 'base64:YOUR_APP_KEY_HERE') {
                config(['app.key' => 'base64:r8X7kL2mP9vN3qW6tY1uI4oE0aZ5sD8fG2hJ6kL9xP0=']);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
