<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OFIController;
use App\Http\Controllers\OfiRecordController;
use App\Http\Controllers\DCRController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\UsersController;
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

    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/dcr', fn() => Inertia::render('DCR'))->name('dcr');

    // =========================
    // OFI
    // =========================
    Route::get('/ofi-form', fn() => Inertia::render('OFIForm'))->name('ofi.form');
    Route::post('/ofi/generate', [OFIController::class, 'generate'])->name('ofi.generate');

    Route::post('/ofi/records', [OfiRecordController::class, 'store'])->name('ofi.records.store');
    Route::get('/ofi/records/{ofiRecord}', [OfiRecordController::class, 'show'])->name('ofi.records.show');
    Route::put('/ofi/records/{ofiRecord}', [OfiRecordController::class, 'update'])->name('ofi.records.update');
    Route::get('/ofi/records/{ofiRecord}/download', [OfiRecordController::class, 'download'])->name('ofi.records.download');

    Route::post('/ofi/records/{ofiRecord}/publish', [OfiRecordController::class, 'publish'])
        ->name('ofi.records.publish');

    // =========================
    // DCR
    // =========================
    Route::post('/dcr/generate', [DCRController::class, 'generate'])->name('dcr.generate');

    // =========================
    // Documents (Masterlist)
    // =========================
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{documentType}', [DocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{documentType}/upload', [DocumentController::class, 'upload'])->name('documents.upload');

    // =========================
    // Preview & Download Routes
    // =========================
    Route::get('/documents/uploads/{upload}/preview', [DocumentController::class, 'preview'])
        ->name('documents.uploads.preview');

    Route::get('/documents/uploads/{upload}/download', [DocumentController::class, 'download'])
        ->name('documents.uploads.download');

    // =========================
    // Admin Only Routes
    // =========================
    Route::middleware('can:admin-only')->group(function () {
        Route::get('/admin/dashboard', fn() => Inertia::render('Dashboard'))->name('admin.dashboard');

        // Users tab (ADMIN ONLY)
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
    });
});