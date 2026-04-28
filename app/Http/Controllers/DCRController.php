<?php

namespace App\Http\Controllers;

use App\Models\QmsDynamicField;
use App\Services\DCRFormGenerator;
use App\Services\DcrDynamicFieldValidator;
use App\Services\QmsTemplateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class DCRController extends Controller
{
    public function __construct(
        protected QmsTemplateResolver $templateResolver,
        protected DcrDynamicFieldValidator $dynamicFieldValidator
    ) {
    }

    public function dynamicFields()
    {
        $fields = QmsDynamicField::query()
            ->forModule('DCR')
            ->active()
            ->sorted()
            ->get()
            ->map(fn(QmsDynamicField $field) => [
                'id' => $field->id,
                'label' => $field->label,
                'field_key' => $field->field_key,
                'field_type' => $field->field_type,
                'is_required' => (bool) $field->is_required,
                'sort_order' => (int) $field->sort_order,
            ])
            ->values();

        return response()->json([
            'fields' => $fields,
        ]);
    }

    public function generate(Request $request)
    {
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

            'requestDecision' => 'nullable|string',
            'imrSigDate' => 'nullable|string',

            'approvingSigName' => 'nullable|string',
            'approvingDate' => 'nullable|string',

            'statusNo' => 'nullable|string',
            'statusVersion' => 'nullable|string',
            'statusRevision' => 'nullable|string',
            'effectivityDate' => 'nullable|string',
            'idsDateUpdated' => 'nullable|string',
            'updatedBy' => 'nullable|string',

            'dynamic' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->dynamicFieldValidator->validateRequiredFields($request->all());

        $data = [
            'date' => $request->input('date', ''),
            'dcrNo' => $request->input('dcrNo', ''),
            'toFor' => $request->input('toFor', ''),
            'from' => $request->input('from', ''),

            'amend' => $request->boolean('amend'),
            'newDoc' => $request->boolean('newDoc'),
            'deleteDoc' => $request->boolean('deleteDoc'),

            'documentNumber' => $request->input('documentNumber', ''),
            'documentTitle' => $request->input('documentTitle', ''),
            'revisionStatus' => $request->input('revisionStatus', ''),

            'changesRequested' => $request->input('changesRequested', ''),
            'reason' => $request->input('reason', ''),
            'requestedBy' => $request->input('requestedBy', ''),
            'deptUnitHead' => $request->input('deptUnitHead', ''),

            'requestDenied' => $request->input('requestDecision') === 'DENIED',
            'requestAccepted' => $request->input('requestDecision') === 'ACCEPTED',
            'requestDecision' => $request->input('requestDecision', ''),
            'imrSigDate' => $request->input('imrSigDate', ''),

            'approvingSigName' => $request->input('approvingSigName', ''),
            'approvingDate' => $request->input('approvingDate', ''),

            'statusNo' => $request->input('statusNo', ''),
            'statusVersion' => $request->input('statusVersion', ''),
            'statusRevision' => $request->input('statusRevision', ''),
            'effectivityDate' => $request->input('effectivityDate', ''),
            'idsDateUpdated' => $request->input('idsDateUpdated', ''),
            'updatedBy' => $request->input('updatedBy', ''),

            'dynamic' => $request->input('dynamic', []),
        ];

        $outputDir = storage_path('app/dcr_forms');
        $fileName = 'DCR_' . now()->format('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $fileName;

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        try {
            $templatePath = $this->templateResolver->getActiveDcrTemplatePath();

            $generator = new DCRFormGenerator($templatePath);
            $generator->generate($data, $outputPath);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::error('DCRController@generate failed', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'error' => 'Failed to generate document. Please try again or contact support.',
            ], 500);
        }

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }
}
