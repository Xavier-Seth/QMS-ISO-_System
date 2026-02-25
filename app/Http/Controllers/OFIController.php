<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\OFIFormGenerator;

class OFIController extends Controller
{
    public function generate(Request $request)
    {
        // 1. Validate incoming data
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|string',
            'refNo' => 'nullable|string',
            'to' => 'nullable|string',
            'ofiNo' => 'nullable|string',
            'from' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Build the data array from the request
        $data = [
            // Header
            'date' => $request->input('date', ''),
            'refNo' => $request->input('refNo', ''),
            'to' => $request->input('to', ''),
            'ofiNo' => $request->input('ofiNo', ''),
            'from' => $request->input('from', ''),
            'isoClause' => $request->input('isoClause', ''),

            // Source checkboxes
            'sourceIqa' => $request->boolean('sourceIqa'),
            'sourceFeedback' => $request->boolean('sourceFeedback'),
            'sourceSurvey' => $request->boolean('sourceSurvey'),
            'sourceSystem' => $request->boolean('sourceSystem'),
            'sourceOthersCheck' => $request->boolean('sourceOthersCheck'),
            'sourceOthersText' => $request->input('sourceOthersText', ''),

            // Section 1
            'suggestion' => $request->input('suggestion', ''),
            'deptRepSig1' => $request->input('deptRepSig1', ''),
            'requestedBySig' => $request->input('requestedBySig', ''),
            'agreedDate' => $request->input('agreedDate', ''),

            // Section 2
            'beneficialImpact' => $request->input('beneficialImpact', ''),
            'associatedRisks' => $request->input('associatedRisks', ''),
            'action' => $request->input('action', ''),
            'deptRepDate2' => $request->input('deptRepDate2', ''),
            'deptHeadDate2' => $request->input('deptHeadDate2', ''),

            // Section 3
            'assessmentUpdateNo' => $request->input('assessmentUpdate') === 'NO',
            'assessmentUpdateYes' => $request->input('assessmentUpdate') === 'YES',
            'dateUpdated' => $request->input('dateUpdated', ''),
            'verifiedBy1' => $request->input('verifiedBy1', ''),

            // Section 4
            'qmsUpdateNo' => $request->input('imsUpdate') === 'NO',
            'qmsUpdateYes' => $request->input('imsUpdate') === 'YES',
            'dcrUpdated' => $request->input('dcrNo', ''),
            'verifiedBy2' => $request->input('verifiedBy2', ''),

            // Section 5 - Follow-up rows
            'followUp' => $request->input('followUp', []),

            // Section 6
            'imrSig' => $request->input('imrSig', ''),
            'caseClosedDate' => $request->input('caseClosedDate', ''),
            'notedBy' => $request->input('notedBy', ''),
        ];

        // 3. Define paths
        $templatePath = base_path('templates/F-QMS-007_template_fixed_v6.docx');
        $outputDir = storage_path('app/ofi_forms');
        $fileName = 'OFI_' . now()->format('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $fileName;

        // 4. Make sure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 5. Generate the DOCX
        try {
            $generator = new OFIFormGenerator($templatePath);
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