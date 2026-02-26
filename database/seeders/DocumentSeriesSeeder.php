<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentSeries;

class DocumentSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code_prefix' => 'R-QMS', 'name' => 'Records'],
            ['code_prefix' => 'F-QMS', 'name' => 'Forms'],
            ['code_prefix' => 'IPCR', 'name' => 'IPCR'],
            ['code_prefix' => 'MANUAL', 'name' => 'Manuals'],
        ];

        foreach ($rows as $row) {
            DocumentSeries::updateOrCreate(
                ['code_prefix' => $row['code_prefix']],
                $row
            );
        }
    }
}