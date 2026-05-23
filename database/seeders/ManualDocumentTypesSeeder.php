<?php

namespace Database\Seeders;

use App\Models\DocumentSeries;
use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class ManualDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $series = DocumentSeries::where('code_prefix', 'MANUAL')->firstOrFail();

        $rows = [
            [
                'code' => 'MANUAL-ASM-CONTROLLED',
                'title' => 'ASM Controlled Manual',
                'manual_category' => 'ASM',
                'manual_access' => 'controlled',
            ],
            [
                'code' => 'MANUAL-ASM-UNCONTROLLED',
                'title' => 'ASM Uncontrolled Manual',
                'manual_category' => 'ASM',
                'manual_access' => 'uncontrolled',
            ],
            [
                'code' => 'MANUAL-QSM-CONTROLLED',
                'title' => 'QSM Controlled Manual',
                'manual_category' => 'QSM',
                'manual_access' => 'controlled',
            ],
            [
                'code' => 'MANUAL-QSM-UNCONTROLLED',
                'title' => 'QSM Uncontrolled Manual',
                'manual_category' => 'QSM',
                'manual_access' => 'uncontrolled',
            ],
            [
                'code' => 'MANUAL-HRM-CONTROLLED',
                'title' => 'HRM Controlled Manual',
                'manual_category' => 'HRM',
                'manual_access' => 'controlled',
            ],
            [
                'code' => 'MANUAL-HRM-UNCONTROLLED',
                'title' => 'HRM Uncontrolled Manual',
                'manual_category' => 'HRM',
                'manual_access' => 'uncontrolled',
            ],
            [
                'code' => 'MANUAL-RIEM-CONTROLLED',
                'title' => 'RIEM Controlled Manual',
                'manual_category' => 'RIEM',
                'manual_access' => 'controlled',
            ],
            [
                'code' => 'MANUAL-RIEM-UNCONTROLLED',
                'title' => 'RIEM Uncontrolled Manual',
                'manual_category' => 'RIEM',
                'manual_access' => 'uncontrolled',
            ],
            [
                'code' => 'MANUAL-REM-CONTROLLED',
                'title' => 'REM Controlled Manual',
                'manual_category' => 'REM',
                'manual_access' => 'controlled',
            ],
            [
                'code' => 'MANUAL-REM-UNCONTROLLED',
                'title' => 'REM Uncontrolled Manual',
                'manual_category' => 'REM',
                'manual_access' => 'uncontrolled',
            ],
            [
                'code' => 'MANUAL-ASM-MASTER-COPY',
                'title' => 'ASM Master Copy Manual',
                'manual_category' => 'ASM',
                'manual_access' => 'master_copy',
            ],
            [
                'code' => 'MANUAL-QSM-MASTER-COPY',
                'title' => 'QSM Master Copy Manual',
                'manual_category' => 'QSM',
                'manual_access' => 'master_copy',
            ],
            [
                'code' => 'MANUAL-HRM-MASTER-COPY',
                'title' => 'HRM Master Copy Manual',
                'manual_category' => 'HRM',
                'manual_access' => 'master_copy',
            ],
            [
                'code' => 'MANUAL-RIEM-MASTER-COPY',
                'title' => 'RIEM Master Copy Manual',
                'manual_category' => 'RIEM',
                'manual_access' => 'master_copy',
            ],
            [
                'code' => 'MANUAL-REM-MASTER-COPY',
                'title' => 'REM Master Copy Manual',
                'manual_category' => 'REM',
                'manual_access' => 'master_copy',
            ],
        ];

        foreach ($rows as $row) {
            DocumentType::updateOrCreate(
                ['code' => $row['code']],
                [
                    'series_id' => $series->id,
                    'title' => $row['title'],
                    'storage' => 'Electronic',
                    'manual_category' => $row['manual_category'],
                    'manual_access' => $row['manual_access'],
                    'requires_revision' => true,
                    'status' => 'active',
                    'status_note' => null,
                ]
            );
        }
    }
}
