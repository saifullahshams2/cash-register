<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

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

        if ($isInstalled) {
            try {
                if (! Schema::hasTable('users')) {
                    $isInstalled = false;
                }
            } catch (\Throwable) {
                $isInstalled = false;
            }
        }

        // 3. If application is installed and user tries to access /install, reject or redirect
        if ($isInstalled && $request->is('install*')) {
            if ($request->expectsJson() || $request->is('install/test-db') || $request->isMethod('POST')) {
                abort(403, 'Application is already installed.');
            }

            return redirect()->route('login')->with('info', 'Application is already installed.');
        }

        // 4. If application is NOT installed and request is not for /install or Livewire endpoints, redirect to /install
        if (! $isInstalled && ! $request->is('install*') && ! $request->is('livewire*') && ! $request->is('build*')) {
            return redirect()->route('installer.index');
        }

        return $next($request);
    }
}
