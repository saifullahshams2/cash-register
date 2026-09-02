<?php

use App\Livewire\Admin\Cashiers;
use App\Livewire\Auth\Login;
use App\Livewire\Pos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated Routes (Admin & Cashier)
Route::middleware('auth')->group(function () {
    Route::get('/', Pos::class)->name('pos');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Admin Only Routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/cashiers', Cashiers::class)->name('cashiers');
    });
});
