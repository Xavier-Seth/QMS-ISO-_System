<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class PerformanceOpcrDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $reference = DocumentType::where('code', 'PERF-IPCR')->first();

        $series = DocumentSeries::firstOrCreate(
            ['code_prefix' => 'OPCR'],
            ['name' => 'Office Performance Commitment and Review']
        );

        DocumentType::firstOrCreate(
            ['code' => 'PERF-OPCR'],
            [
                'series_id' => $series->id,
                'title' => 'OPCR Performance Files',
                'storage' => $reference?->storage ?? 'Electronic',
                'status' => $reference?->status ?? 'Active',
                'requires_revision' => $reference?->requires_revision ?? false,
            ]
        );
    }
}
