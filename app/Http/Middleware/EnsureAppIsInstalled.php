<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureAppIsInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. If installer directory does not exist (user deleted app/Installer), bypass all checks
        if (! file_exists(app_path('Installer'))) {
            return $next($request);
        }

        $isInstalled = file_exists(storage_path('installed'));

        // 2. If lockfile is absent, check if database already has an administrator
        if (! $isInstalled) {
            try {
                if (User::where('role', User::ROLE_ADMIN)->exists()) {
                    @file_put_contents(storage_path('installed'), 'INSTALLED_AT='.now()->toIso8601String()."\n");
                    $isInstalled = true;
                }
            } catch (Throwable) {
                // Database or tables do not exist yet -> not installed
                $isInstalled = false;
            }
        }

        // 3. If application is installed and user tries to access /install, redirect to /login
        if ($isInstalled && $request->is('install*')) {
            return redirect()->route('login')->with('info', 'Application is already installed.');
        }

        // 4. If application is NOT installed and request is not for /install or Livewire endpoints, redirect to /install
        if (! $isInstalled && ! $request->is('install*') && ! $request->is('livewire*') && ! $request->is('build*')) {
            return redirect()->route('installer.index');
        }

        return $next($request);
    }
}
