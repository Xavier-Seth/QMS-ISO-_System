<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\DCRFormGenerator;

class DCRController extends Controller
{
    public function generate(Request $request)
    {
        // 1. Validate incoming data
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|string',
            'dcrNo' => 'nullable|string',
            'toFor' => 'nullable|string',
            'from' => 'nullable|string',

            'amend' => 'nullable|boolean',
            'newDoc' => 'nullable|boolean',
            'deleteDoc' => 'nullable|boolean',

            'documentNumber' => 'nullable|string',
            'documentTitle' => 'nullable|string',
            'revisionStatus' => 'nullable|string',

            'changesRequested' => 'nullable|string',
            'reason' => 'nullable|string',

            'requestedBy' => 'nullable|string',
            'deptUnitHead' => 'nullable|string',

            'requestDecision' => 'nullable|string', // DENIED | ACCEPTED
            'imrSigDate' => 'nullable|string',

            'approvingSigName' => 'nullable|string',
            'approvingDate' => 'nullable|string',

            'statusNo' => 'nullable|string',
            'statusVersion' => 'nullable|string',
            'statusRevision' => 'nullable|string',
            'effectivityDate' => 'nullable|string',
            'idsDateUpdated' => 'nullable|string',
            'updatedBy' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Convert requestDecision into two checkbox placeholders
        $decision = $request->input('requestDecision', '');

        $data = [
            // Header
            'date' => $request->input('date', ''),
            'dcrNo' => $request->input('dcrNo', ''),
            'toFor' => $request->input('toFor', ''),
            'from' => $request->input('from', ''),

            // Document type (checkboxes)
            'amend' => $request->boolean('amend'),
            'newDoc' => $request->boolean('newDoc'),
            'deleteDoc' => $request->boolean('deleteDoc'),

            // Section 1
            'documentNumber' => $request->input('documentNumber', ''),
            'documentTitle' => $request->input('documentTitle', ''),
            'revisionStatus' => $request->input('revisionStatus', ''),

            // Section 2
            'changesRequested' => $request->input('changesRequested', ''),
            'reason' => $request->input('reason', ''),
            'requestedBy' => $request->input('requestedBy', ''),
            'deptUnitHead' => $request->input('deptUnitHead', ''),

            // Section 3
            'requestDenied' => ($decision === 'DENIED'),
            'requestAccepted' => ($decision === 'ACCEPTED'),
            'imrSigDate' => $request->input('imrSigDate', ''),

            // Section 4
            'approvingSigName' => $request->input('approvingSigName', ''),
            'approvingDate' => $request->input('approvingDate', ''),

            // Section 5
            'statusNo' => $request->input('statusNo', ''),
            'statusVersion' => $request->input('statusVersion', ''),
            'statusRevision' => $request->input('statusRevision', ''),
            'effectivityDate' => $request->input('effectivityDate', ''),
            'idsDateUpdated' => $request->input('idsDateUpdated', ''),
            'updatedBy' => $request->input('updatedBy', ''),
        ];

        // 3. Define paths
        // ✅ IMPORTANT: Use .docx template (TemplateProcessor works best with docx)
        $templatePath = base_path('templates/F-QMS-001 _template.docx');

        $outputDir = storage_path('app/dcr_forms');
        $fileName = 'DCR_' . now()->format('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $fileName;

        // 4. Make sure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 5. Generate the DOCX
        try {
            $generator = new DCRFormGenerator($templatePath);
            $generator->generate($data, $outputPath);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate document: ' . $e->getMessage()], 500);
        }

        // 6. Return the file as a download then delete it
        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}