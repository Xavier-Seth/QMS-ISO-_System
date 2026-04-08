<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CarRecordController;
use App\Http\Controllers\DCRController;
use App\Http\Controllers\DcrRecordController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LogsController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\OFIController;
use App\Http\Controllers\OfiRecordController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UsersController;
use App\Models\DocumentType;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => Inertia::render('Home'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', fn() => Inertia::render('Dashboard'))->name('dashboard');
    Route::get('/dcr', fn() => Inertia::render('DCR'))->name('dcr');
    Route::get('/ofi-form', fn() => Inertia::render('OFIForm'))->name('ofi.form');

    Route::get('/car', function () {
        $carType = DocumentType::where('code', 'F-QMS-006')->first();

        return Inertia::render('CARForm', [
            'documentTypeId' => $carType?->id,
        ]);
    })->name('car.form');

    Route::get('/settings', fn() => Inertia::render('Settings/Index'))->name('settings');

    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])
        ->name('settings.profile.update');

    /*
    |--------------------------------------------------------------------------
    | OFI
    |--------------------------------------------------------------------------
    */
    Route::post('/ofi/generate', [OFIController::class, 'generate'])->name('ofi.generate');

    Route::post('/ofi/records', [OfiRecordController::class, 'store'])->name('ofi.records.store');
    Route::get('/ofi/records/{ofiRecord}', [OfiRecordController::class, 'show'])->name('ofi.records.show');
    Route::put('/ofi/records/{ofiRecord}', [OfiRecordController::class, 'update'])->name('ofi.records.update');
    Route::get('/ofi/records/{ofiRecord}/download', [OfiRecordController::class, 'download'])->name('ofi.records.download');
    Route::post('/ofi/records/{ofiRecord}/publish', [OfiRecordController::class, 'publish'])
        ->name('ofi.records.publish');

    Route::post('/ofi/records/{ofiRecord}/submit', [OfiRecordController::class, 'submitForApproval'])
        ->name('ofi.records.submit');

    Route::get('/ofi/my-records', [OfiRecordController::class, 'myRecords'])
        ->name('ofi.records.mine');

    /*
    |--------------------------------------------------------------------------
    | DCR
    |--------------------------------------------------------------------------
    */
    Route::post('/dcr/generate', [DCRController::class, 'generate'])->name('dcr.generate');

    Route::post('/dcr/records', [DcrRecordController::class, 'store'])->name('dcr.records.store');
    Route::get('/dcr/records/{dcrRecord}', [DcrRecordController::class, 'show'])->name('dcr.records.show');
    Route::put('/dcr/records/{dcrRecord}', [DcrRecordController::class, 'update'])->name('dcr.records.update');
    Route::get('/dcr/records/{dcrRecord}/download', [DcrRecordController::class, 'download'])->name('dcr.records.download');
    Route::post('/dcr/records/{dcrRecord}/publish', [DcrRecordController::class, 'publish'])
        ->name('dcr.records.publish');

    Route::post('/dcr/records/{dcrRecord}/submit', [DcrRecordController::class, 'submitForApproval'])
        ->name('dcr.records.submit');

    Route::get('/dcr/my-records', [DcrRecordController::class, 'myRecords'])
        ->name('dcr.records.mine');

    /*
    |--------------------------------------------------------------------------
    | CAR
    |--------------------------------------------------------------------------
    */
    Route::post('/car/records', [CarRecordController::class, 'store'])->name('car.records.store');
    Route::get('/car/records/{carRecord}', [CarRecordController::class, 'show'])->name('car.records.show');
    Route::put('/car/records/{carRecord}', [CarRecordController::class, 'update'])->name('car.records.update');
    Route::post('/car/records/{carRecord}/submit', [CarRecordController::class, 'submitForApproval'])->name('car.records.submit');
    Route::get('/car/records/{carRecord}/download', [CarRecordController::class, 'download'])->name('car.records.download');
    Route::post('/car/records/{carRecord}/publish', [CarRecordController::class, 'publish'])->name('car.records.publish');

    /*
    |--------------------------------------------------------------------------
    | My Records (Unified User Inbox)
    |--------------------------------------------------------------------------
    */
    Route::get('/my-records', [InboxController::class, 'myRecords'])
        ->name('inbox.my-records');

    /*
    |--------------------------------------------------------------------------
    | Manuals
    |--------------------------------------------------------------------------
    */
    Route::get('/manual/{category}', [ManualController::class, 'show'])
        ->where('category', 'asm|qsm|hrm|riem|rem')
        ->name('manual.show');

    Route::post('/manual/{category}/{access}/upload', [ManualController::class, 'upload'])
        ->where('category', 'asm|qsm|hrm|riem|rem')
        ->where('access', 'controlled|uncontrolled')
        ->name('manual.upload');

    Route::get('/manual/uploads/{upload}/preview', [ManualController::class, 'preview'])
        ->name('manual.uploads.preview');

    Route::get('/manual/uploads/{upload}/download', [ManualController::class, 'download'])
        ->name('manual.uploads.download');

    /*
    |--------------------------------------------------------------------------
    | Admin Only
    |--------------------------------------------------------------------------
    */
    Route::middleware('can:admin-only')->group(function () {
        Route::get('/admin/dashboard', fn() => Inertia::render('Dashboard'))->name('admin.dashboard');

        Route::get('/logs', [LogsController::class, 'index'])->name('logs');

        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');

        /*
        |--------------------------------------------------------------------------
        | Documents
        |--------------------------------------------------------------------------
        */
        Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{documentType}', [DocumentController::class, 'show'])->name('documents.show');
        Route::post('/documents/{documentType}/upload', [DocumentController::class, 'upload'])->name('documents.upload');

        Route::post('/documents/types', [DocumentController::class, 'storeType'])
            ->name('documents.types.store');

        Route::patch('/documents/types/{documentType}/obsolete', [DocumentController::class, 'markObsolete'])
            ->name('documents.types.obsolete');

        Route::delete('/documents/types/{documentType}', [DocumentController::class, 'destroyType'])
            ->name('documents.types.destroy');

        Route::get('/documents/uploads/{upload}/preview', [DocumentController::class, 'preview'])
            ->name('documents.uploads.preview');

        Route::get('/documents/uploads/{upload}/download', [DocumentController::class, 'download'])
            ->name('documents.uploads.download');

        /*
        |--------------------------------------------------------------------------
        | Performance
        |--------------------------------------------------------------------------
        */
        Route::get('/performance', [PerformanceController::class, 'index'])
            ->name('performance.index');

        Route::post('/performance/upload', [PerformanceController::class, 'upload'])
            ->name('performance.upload');

        Route::get('/performance/uploads/{upload}/preview', [PerformanceController::class, 'preview'])
            ->name('performance.uploads.preview');

        Route::get('/performance/uploads/{upload}/download', [PerformanceController::class, 'download'])
            ->name('performance.uploads.download');

        Route::get('/upload', fn() => Inertia::render('Upload'))->name('upload');

        /*
        |--------------------------------------------------------------------------
        | Unified Admin Inbox
        |--------------------------------------------------------------------------
        */
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');

        /*
        |--------------------------------------------------------------------------
        | ACTION ROUTES (KEEP THESE)
        |--------------------------------------------------------------------------
        */
        Route::post('/inbox/ofi/{ofiRecord}/approve', [OfiRecordController::class, 'approve'])->name('ofi.inbox.approve');
        Route::post('/inbox/ofi/{ofiRecord}/reject', [OfiRecordController::class, 'reject'])->name('ofi.inbox.reject');

        Route::patch('/ofi/records/{ofiRecord}/resolution-status', [OfiRecordController::class, 'updateResolutionStatus'])
            ->name('ofi.records.resolution-status');

        Route::post('/inbox/car/{carRecord}/approve', [CarRecordController::class, 'approve'])->name('car.inbox.approve');
        Route::post('/inbox/car/{carRecord}/reject', [CarRecordController::class, 'reject'])->name('car.inbox.reject');

        Route::post('/inbox/dcr/{dcrRecord}/approve', [DcrRecordController::class, 'approve'])->name('dcr.inbox.approve');
        Route::post('/inbox/dcr/{dcrRecord}/reject', [DcrRecordController::class, 'reject'])->name('dcr.inbox.reject');
    });
});