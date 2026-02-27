<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OFIController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DashboardController;
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
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/dcr', fn() => Inertia::render('DCR'))->name('dcr');
    Route::get('/ofi-form', fn() => Inertia::render('OFIForm'))->name('ofi.form');

    Route::post('/ofi/generate', [OFIController::class, 'generate'])->name('ofi.generate');

    // =========================
    // Documents (Masterlist)
    // =========================
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{documentType}', [DocumentController::class, 'show'])->name('documents.show');

    // Upload under a document type (used by Show.vue modal)
    Route::post('/documents/{documentType}/upload', [DocumentController::class, 'upload'])
        ->name('documents.upload');

    // =========================
    // NEW: Preview & Download Routes
    // =========================
    Route::get('/documents/uploads/{upload}/preview', [DocumentController::class, 'preview'])
        ->name('documents.uploads.preview');

    Route::get('/documents/uploads/{upload}/download', [DocumentController::class, 'download'])
        ->name('documents.uploads.download');

    Route::middleware('can:admin-only')->group(function () {
        Route::get('/admin/dashboard', fn() => Inertia::render('Dashboard'))->name('admin.dashboard');
    });
});