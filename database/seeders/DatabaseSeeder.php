<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DocumentSeriesSeeder::class,
            RQmsDocumentTypesSeeder::class,
            FQmsDocumentTypesSeeder::class,
            ManualDocumentTypesSeeder::class,
            PerformanceDocumentTypesSeeder::class,
        ]);
    }
}