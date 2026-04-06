<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentSeries;
use App\Models\DocumentType;

class PerformanceDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'IPCR' => 'PERF-IPCR',
            'DPCR' => 'PERF-DPCR',
            'UPCR' => 'PERF-UPCR',
        ];

        foreach ($map as $seriesCode => $typeCode) {
            $series = DocumentSeries::where('code_prefix', $seriesCode)->first();

            if (!$series) {
                continue;
            }

            DocumentType::updateOrCreate(
                ['code' => $typeCode],
                [
                    'series_id' => $series->id,
                    'title' => "{$seriesCode} Performance Files",
                    'storage' => 'Electronic',
                ]
            );
        }
    }
}