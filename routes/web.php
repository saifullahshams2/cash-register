<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    });
});
