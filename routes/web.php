<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OFIController;          // ← ADD THIS
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Landing Page
Route::get('/', fn() => Inertia::render('Home'));

// Guest Only
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Shared Auth Routes
    Route::get('/dashboard', fn() => Inertia::render('Dashboard'));
    Route::get('/dcr', fn() => Inertia::render('DCR'));
    Route::get('/ofi-form', fn() => Inertia::render('OFIForm'));

    Route::post('/ofi/generate', [OFIController::class, 'generate']); // ← ADD THIS

    // Admin Only
    Route::middleware('can:admin-only')->group(function () {
        Route::get('/admin/dashboard', fn() => Inertia::render('Dashboard'));
    });
});