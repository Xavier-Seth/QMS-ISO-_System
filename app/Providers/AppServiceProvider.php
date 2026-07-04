<?php

namespace App\Providers;

use App\Models\CarRecord;
use App\Models\DcrRecord;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Models\User;
use App\Observers\CarRecordObserver;
use App\Observers\DcrRecordObserver;
use App\Observers\DocumentUploadObserver;
use App\Observers\OfiRecordObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DocumentUpload::observe(DocumentUploadObserver::class);
        OfiRecord::observe(OfiRecordObserver::class);
        DcrRecord::observe(DcrRecordObserver::class);
        CarRecord::observe(CarRecordObserver::class);
        User::observe(UserObserver::class);
    }
}
