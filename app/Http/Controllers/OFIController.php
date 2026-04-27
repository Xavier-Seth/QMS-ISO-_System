<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\OFIFormGenerator;
use App\Services\QmsDynamicFieldValidator;
use App\Services\QmsTemplateResolver;
use App\Support\QmsTemplateModules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class OFIController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLogService,
        protected QmsTemplateResolver $templateResolver,
        protected QmsDynamicFieldValidator $dynamicFieldValidator
    ) {
    }

    public function generate(Request $request)
    {
        // 1. Validate incoming data
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|string',
            'refNo' => 'nullable|string',
            'to' => 'nullable|string',
            'ofiNo' => 'nullable|string',
            'from' => 'nullable|string',
            'followSig' => 'nullable|string',
            'dynamic' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->dynamicFieldValidator->validateRequiredFields(
            QmsTemplateModules::OFI,
            $request->all()
        );

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

            // Section 5
            'followSig' => $request->input('followSig', ''),
            'followUp' => $request->input('followUp', []),

            // Section 6
            'imrSig' => $request->input('imrSig', ''),
            'caseClosedDate' => $request->input('caseClosedDate', ''),
            'notedBy' => $request->input('notedBy', ''),

            // Additional fields configured in System Settings
            'dynamic' => $request->input('dynamic', []),
        ];

        // 3. Define paths
        $outputDir = storage_path('app/ofi_forms');
        $recordLabel = $request->input('ofiNo', '');
        $fileName = 'OFI_' . now()->format('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $fileName;

        // 4. Make sure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // 5. Generate the DOCX
        try {
            $templatePath = $this->templateResolver->getActiveOfiTemplatePath();

            $generator = new OFIFormGenerator($templatePath);
            $generator->generate($data, $outputPath);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to generate document: ' . $e->getMessage()], 500);
        }

        $this->activityLogService->log([
            'module' => 'ofi',
            'action' => 'downloaded',
            'record_label' => $recordLabel !== '' ? $recordLabel : $fileName,
            'file_type' => 'docx',
            'description' => $recordLabel !== ''
                ? 'Downloaded generated OFI form ' . $recordLabel
                : 'Downloaded generated OFI form ' . $fileName,
            'new_values' => [
                'file_name' => $fileName,
                'ref_no' => $request->input('refNo', ''),
                'to' => $request->input('to', ''),
                'from' => $request->input('from', ''),
            ],
        ]);

        // 6. Return the file as a download then delete it
        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}
