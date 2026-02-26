<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentSeries;
use App\Models\DocumentType;

class RQmsDocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $series = DocumentSeries::where('code_prefix', 'R-QMS')->firstOrFail();

        $rows = [
            ['code' => 'R-QMS-001', 'title' => 'Filing Chart', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-002', 'title' => 'Masterlist of Forms', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-003', 'title' => 'List of References', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-004', 'title' => 'NRRIP', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-005', 'title' => 'SWOT', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-006', 'title' => 'Risk Assessment/Risk Treatment Plans', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-007', 'title' => 'Opportunity Assessment/ Plans', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-008', 'title' => 'Communication Plan', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-009', 'title' => 'Inventory of Data to Analyze and Evaluate', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-010', 'title' => 'UPCR', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-011', 'title' => 'Minutes of the Meeting', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-012', 'title' => 'Correspondences', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-013', 'title' => 'Document Change Request', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-014', 'title' => 'Document Change Request Register', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-015', 'title' => 'Document Control Log', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-016', 'title' => 'Document Approval and Distribution Matrix', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-017', 'title' => 'Corrective Action Request', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-018', 'title' => 'Opportunities for Improvement', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-019', 'title' => 'CAR Status Log', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-020', 'title' => 'OFI Status Log', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-021', 'title' => 'Superseded Master Copy of Manual', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-022', 'title' => 'QMS Plan', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-023', 'title' => 'QMS Core Team', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-024', 'title' => 'Organizational Chart', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-025', 'title' => 'Position Chart', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-026', 'title' => 'LNU Directory', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-027', 'title' => 'Office Codes', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-028', 'title' => 'Client Satisfaction Report', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-029', 'title' => 'QMS Reports', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-030', 'title' => 'Board Resolution', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-031', 'title' => 'Designations', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-032', 'title' => 'Attendance Sheets', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-033', 'title' => 'Office Orders and Memos', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-034', 'title' => 'Special Orders', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-035', 'title' => 'Travel Orders', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-036', 'title' => 'Financial Plan', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-037', 'title' => 'Strategic Plan', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-038', 'title' => 'PPMP & Purchase Requests', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-039', 'title' => 'ISO Procurement Consultancy', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-040', 'title' => 'Supplies/M.R.', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-041', 'title' => 'Complaints/Grievance', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-042', 'title' => 'Contract of Service', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-043', 'title' => 'Request Letters', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-044', 'title' => 'Requests Letters Approved-Outgoing', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-045', 'title' => 'ISO Certification Body Reports', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-046', 'title' => 'QMS Training Materials', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-047', 'title' => 'QMS Training Certificates', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-048', 'title' => '5S Housekeeping', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-049', 'title' => 'Notice of Meetings', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-050', 'title' => 'ISO Activities', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-051', 'title' => 'Request for Data (QMS-ISO)', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-052', 'title' => 'Request for Materials and Sevices', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-053', 'title' => 'Request for Data (SUC Levelling)', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-054', 'title' => 'Billing & Accomplishments Report', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-055', 'title' => 'Student Satisfaction Feedback Report', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-056', 'title' => 'Preventive Maintenance Schedule', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-057', 'title' => 'Analysis and Evaluation Reports of Office', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-058', 'title' => 'ISO Certificates', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-059', 'title' => 'NAP Files', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-060', 'title' => 'Monitoring', 'storage' => 'Physical, Electronic'],
            ['code' => 'R-QMS-061', 'title' => 'Turnover Documents', 'storage' => 'Physical, Electronic'],

            ['code' => 'R-QMS-100', 'title' => 'Internal Quality Audit', 'storage' => '-'],
            ['code' => 'R-QMS-101', 'title' => 'IQA Special Orders and Board Resolutions', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-102', 'title' => 'IQA Training Certificates', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-103', 'title' => 'IQA Schedule', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-104', 'title' => 'IQA Plan', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-105', 'title' => 'IQA Checklists', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-106', 'title' => 'IQA Audit Report', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-107', 'title' => 'IQA Auditor Evaluation', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-108', 'title' => 'CAR and CAR Status Log', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-109', 'title' => 'OFI and OFI Status Log', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-110', 'title' => 'IQA Notice of Meetings', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-111', 'title' => 'IQA Attendance Sheets', 'storage' => 'Physical/Electronic'],
            ['code' => 'R-QMS-112', 'title' => 'IQA Minutes of Meeting', 'storage' => 'Physical/Electronic'],
        ];

        foreach ($rows as $row) {
            DocumentType::updateOrCreate(
                ['code' => $row['code']],
                [
                    'series_id' => $series->id,
                    'title' => $row['title'],
                    'storage' => $row['storage'],
                ]
            );
        }
    }
}