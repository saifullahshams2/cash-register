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

            // Generate dynamic ephemeral encryption key during initial setup wizard if missing
            if (empty(config('app.key')) || config('app.key') === 'base64:YOUR_APP_KEY_HERE') {
                config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (file_exists(storage_path('installed')) && app()->isProduction()) {
            if (empty(config('app.key')) || str_contains((string) config('app.key'), 'YOUR_APP_KEY_HERE')) {
                throw new \RuntimeException('Application encryption key [APP_KEY] is not set. Run "php artisan key:generate" before starting in production.');
            }
        }
    }
}
