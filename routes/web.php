<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelescopeAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Telescope login routes
Route::get('/telescope/login', [TelescopeAuthController::class, 'showLogin'])->name('telescope.login');
Route::post('/telescope/login', [TelescopeAuthController::class, 'login'])->name('telescope.login.post');
Route::get('/telescope/login-callback', [TelescopeAuthController::class, 'handleGoogleCallback'])->name('telescope.login.callback');

// Keep old route for backward compatibility
Route::get('/telescope-login', [TelescopeAuthController::class, 'showLogin']);
