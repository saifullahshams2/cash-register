<?php

use App\Http\Controllers\Admin\ExportController;
use App\Installer\InstallerController;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Production Installer Routes (Safe & deletable: if app/Installer is deleted, routes simply vanish)
if (file_exists(app_path('Installer/InstallerController.php'))) {
    Route::get('/install', [InstallerController::class, 'index'])->name('installer.index');
    Route::post('/install/test-db', [InstallerController::class, 'testDatabase'])->middleware('throttle:15,1')->name('installer.test-db');
    Route::post('/install/process', [InstallerController::class, 'process'])->middleware('throttle:5,1')->name('installer.process');
}

// Dynamic Web Manifest
Route::get('/manifest.json', function () {
    $siteTitle = Setting::get('site_title', config('app.name', 'Cash Register POS'));
    $pwaTitle = Setting::get('pwa_title') ?: $siteTitle;
    $pwaIcon = Setting::get('pwa_icon') ?: Setting::get('site_logo');

    $icons = [];

    if ($pwaIcon) {
        $extension = strtolower(pathinfo(parse_url($pwaIcon, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $pwaMime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        $icons[] = [
            'src' => $pwaIcon,
            'sizes' => '192x192',
            'type' => $pwaMime,
            'purpose' => 'any',
        ];
        $icons[] = [
            'src' => $pwaIcon,
            'sizes' => '512x512',
            'type' => $pwaMime,
            'purpose' => 'any',
        ];
        $icons[] = [
            'src' => $pwaIcon,
            'sizes' => '512x512',
            'type' => $pwaMime,
            'purpose' => 'maskable',
        ];
    } else {
        $icons[] = [
            'src' => asset('icons/icon-192x192.png'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any',
        ];
        $icons[] = [
            'src' => asset('icons/icon-512x512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ];
        $icons[] = [
            'src' => asset('icons/icon-maskable-512x512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ];
    }

    return response()->json([
        'id' => '/?source=pwa',
        'name' => $pwaTitle,
        'short_name' => $pwaTitle,
        'description' => 'Fast, offline-ready Cash Register POS application',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'any',
        'theme_color' => '#0f172a',
        'background_color' => '#0f172a',
        'icons' => $icons,
    ], 200, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
    ]);
})->name('manifest');

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Cashier Only Routes (Admin cannot use POS, redirected to admin.dashboard)
    Route::get('/', Pos::class)->middleware('cashier');
    Route::get('/pos', Pos::class)->middleware('cashier')->name('pos');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Admin Only Routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', Dashboard::class)->name('dashboard');
        Route::get('/dashboard', Dashboard::class);
        Route::get('/cashiers', function () {
            return redirect()->route('admin.dashboard', ['tab' => 'users']);
        })->name('cashiers');
        Route::get('/products', function () {
            return redirect()->route('admin.dashboard', ['tab' => 'products']);
        })->name('products');

        // Sales Report Exports (Throttled to protect CPU / memory)
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('/export/pdf', [ExportController::class, 'exportPdf'])->name('export.pdf');
            Route::get('/export/xlsx', [ExportController::class, 'exportXlsx'])->name('export.xlsx');
        });
    });
});
