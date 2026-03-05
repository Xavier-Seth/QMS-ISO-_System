<?php

namespace App\Http\Controllers;

use App\Models\OfiRecord;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\OFIFormGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OfiRecordController extends Controller
{
    private function rQms018TypeId(): int
    {
        return DocumentType::where('code', 'R-QMS-018')->value('id')
            ?? abort(404, 'DocumentType R-QMS-018 not found.');
    }

    /** Create new record (draft) */
    public function store(Request $request)
    {
        $payload = $request->all();

        $record = OfiRecord::create([
            'document_type_id' => $this->rQms018TypeId(),
            'ofi_no' => $payload['ofiNo'] ?? null,
            'ref_no' => $payload['refNo'] ?? null,
            'to' => $payload['to'] ?? null,
            'status' => $payload['status'] ?? 'draft',
            'data' => $payload,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['id' => $record->id]);
    }

    /** Load saved record data */
    public function show(OfiRecord $ofiRecord)
    {
        return response()->json([
            'id' => $ofiRecord->id,
            'status' => $ofiRecord->status,
            'data' => $ofiRecord->data,
        ]);
    }

    /** Update existing record */
    public function update(Request $request, OfiRecord $ofiRecord)
    {
        $payload = $request->all();

        $ofiRecord->update([
            'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
            'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
            'to' => $payload['to'] ?? $ofiRecord->to,
            'status' => $payload['status'] ?? $ofiRecord->status,
            'data' => $payload,
            'updated_by' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Download DOCX from saved record (temporary file) */
    public function download(OfiRecord $ofiRecord)
    {
        $templatePath = base_path('templates/F-QMS-007_template_fixed_v6.docx');

        $tmpDir = storage_path('app/ofi_forms_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $fileName = 'OFI_' . ($ofiRecord->ofi_no ?: now()->format('Ymd_His')) . '.docx';
        $outputPath = $tmpDir . '/' . $fileName;

        $generator = new OFIFormGenerator($templatePath);
        $generator->generate($ofiRecord->data, $outputPath);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Publish saved record to Uploads list:
     * - generates docx
     * - stores in public disk
     * - creates document_uploads row with ofi_record_id
     */
    public function publish(Request $request, OfiRecord $ofiRecord)
    {
        // Optional: update record with latest data before publishing
        if ($request->has('data')) {
            $payload = (array) $request->input('data', []);

            $ofiRecord->update([
                'ofi_no' => $payload['ofiNo'] ?? $ofiRecord->ofi_no,
                'ref_no' => $payload['refNo'] ?? $ofiRecord->ref_no,
                'to' => $payload['to'] ?? $ofiRecord->to,
                'status' => $request->input('status', $ofiRecord->status),
                'data' => $payload ?: $ofiRecord->data,
                'updated_by' => auth()->id(),
            ]);
        }

        $templatePath = base_path('templates/F-QMS-007_template_fixed_v6.docx');
        $generator = new OFIFormGenerator($templatePath);

        // 1) Generate DOCX to temp
        $tmpDir = storage_path('app/ofi_forms_tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $safeNo = $ofiRecord->ofi_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $ofiRecord->ofi_no)
            : now()->format('Ymd_His');

        // If you publish multiple times, this will overwrite same file name.
        // If you want unique each time, append: '_' . now()->format('His')
        $fileName = "OFI_{$safeNo}.docx";

        $tmpPath = $tmpDir . '/' . $fileName;
        $generator->generate($ofiRecord->data, $tmpPath);

        // 2) Store in public disk
        $publicPath = 'documents/ofi/' . $fileName;
        Storage::disk('public')->put($publicPath, file_get_contents($tmpPath));

        // 3) Create upload row
        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms018TypeId(),
            'uploaded_by' => auth()->id(),
            'ofi_record_id' => $ofiRecord->id,
            'revision' => null,         // R-QMS no revision
            'status' => null,           // R-QMS no Active/Obsolete
            'file_name' => $fileName,
            'file_path' => $publicPath,
            'remarks' => $request->input('remarks'),
        ]);

        @unlink($tmpPath);

        return response()->json([
            'ok' => true,
            'upload_id' => $upload->id,
            'ofi_record_id' => $ofiRecord->id,
        ]);
    }
}