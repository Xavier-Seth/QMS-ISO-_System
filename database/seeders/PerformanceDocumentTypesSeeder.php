<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class PerformanceDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $seriesMap = [
            'IPCR' => [
                'series_name' => 'IPCR',
                'type_code' => 'PERF-IPCR',
                'type_title' => 'IPCR Performance Files',
            ],
            'DPCR' => [
                'series_name' => 'Department Performance Commitment and Review',
                'type_code' => 'PERF-DPCR',
                'type_title' => 'DPCR Performance Files',
            ],
            'UPCR' => [
                'series_name' => 'University Performance Commitment and Review',
                'type_code' => 'PERF-UPCR',
                'type_title' => 'UPCR Performance Files',
            ],
        ];

        foreach ($seriesMap as $codePrefix => $config) {
            $series = DocumentSeries::firstOrCreate(
                ['code_prefix' => $codePrefix],
                ['name' => $config['series_name']]
            );

            DocumentType::updateOrCreate(
                ['code' => $config['type_code']],
                [
                    'series_id' => $series->id,
                    'title' => $config['type_title'],
                    'storage' => 'Electronic',
                    'status' => 'Active',
                    'requires_revision' => false,
                ]
            );
        }
    }
}