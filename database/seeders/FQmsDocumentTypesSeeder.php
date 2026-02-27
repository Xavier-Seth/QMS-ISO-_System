<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentSeries;
use App\Models\DocumentType;

class FQmsDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $series = DocumentSeries::where('code_prefix', 'F-QMS')->firstOrFail();

        $rows = [
            ['code' => 'F-QMS-001', 'title' => 'Document Change Request'],
            ['code' => 'F-QMS-002', 'title' => 'Document Change Request Register'],
            ['code' => 'F-QMS-003', 'title' => 'Document Control Log'],
            ['code' => 'F-QMS-004', 'title' => 'Internal Quality Audit Schedule'],
            ['code' => 'F-QMS-005', 'title' => 'Internal Quality Auditor Evaluation'],
            ['code' => 'F-QMS-006', 'title' => 'Corrective Action Request Form'],
            ['code' => 'F-QMS-007', 'title' => 'Opportunity for Improvement Form'],
            ['code' => 'F-QMS-008', 'title' => 'CAR Status Log'],
            ['code' => 'F-QMS-009', 'title' => 'OFI Status Log'],
            ['code' => 'F-QMS-010', 'title' => 'Preventive Maintenance Schedule'],
            ['code' => 'F-QMS-011', 'title' => 'Calibration Schedule'],
            ['code' => 'F-QMS-012', 'title' => 'Request for Data (SUC Levelling)'],
            ['code' => 'F-QMS-013', 'title' => 'Request for Data (QMS-ISO)'],
            ['code' => 'F-QMS-014', 'title' => 'Request for Data (IPED)'],
            ['code' => 'F-QMS-015', 'title' => 'Request for Data (ECCD)'],
            ['code' => 'F-QMS-016', 'title' => "Borrower's Slip (Ins. Equipment & Materials)"],
            ['code' => 'F-QMS-017', 'title' => 'Donation Slip for IPED'],
            ['code' => 'F-QMS-018', 'title' => 'Student Consultation Program'],
            ['code' => 'F-QMS-019', 'title' => 'Document Retention Request Register'],
            ['code' => 'F-QMS-020', 'title' => 'Output Monitoring Form (Admin. Aide)'],
            ['code' => 'F-QMS-021', 'title' => 'Output Monitoring Form (SUC Levelling Assistant)'],
            ['code' => 'F-QMS-022', 'title' => 'Output Monitoring Form (QMS Volunteer)'],
            ['code' => 'F-QMS-023', 'title' => 'Output Monitoring Form (Student Assistant)'],
            ['code' => 'F-QMS-024', 'title' => 'Request for Materials and Services'],
            ['code' => 'F-QMS-025', 'title' => 'Client Satisfaction Measurement (CSM)'],
            ['code' => 'F-QMS-026', 'title' => 'Student Satisfaction Survey'],
            ['code' => 'F-QMS-027', 'title' => 'Client Satisfaction Measurement (CSM) Online Version for Online Transactions'],
            ['code' => 'F-QMS-028', 'title' => 'Outgoing Documents Log'],
            ['code' => 'F-QMS-029', 'title' => 'Incoming Documents Log'],
        ];

        foreach ($rows as $r) {
            DocumentType::updateOrCreate(
                ['code' => $r['code']], // prevents duplicates if you re-run seeder
                [
                    'series_id' => $series->id,
                    'title' => $r['title'],
                    'storage' => 'Electronic', // change to '-' or 'Physical, Electronic' if you want
                    'status' => 'active',
                ]
            );
        }
    }
}