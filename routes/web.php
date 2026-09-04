<?php

use App\Http\Controllers\Admin\ExportController;
use App\Installer\InstallerController;
use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Production Installer Routes (Safe & deletable: if app/Installer is deleted, routes simply vanish)
if (file_exists(app_path('Installer/InstallerController.php'))) {
    Route::get('/install', [InstallerController::class, 'index'])->name('installer.index');
    Route::post('/install/test-db', [InstallerController::class, 'testDatabase'])->name('installer.test-db');
    Route::post('/install/process', [InstallerController::class, 'process'])->name('installer.process');
}

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

        // Sales Report Exports
        Route::get('/export/pdf', [ExportController::class, 'exportPdf'])->name('export.pdf');
        Route::get('/export/xlsx', [ExportController::class, 'exportXlsx'])->name('export.xlsx');
    });
});
