<?php

use App\Livewire\Pos;
use Illuminate\Support\Facades\Route;

Route::get('/', Pos::class)->name('pos');
