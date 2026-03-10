<?php

namespace App\Http\Controllers;

use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Models\OfiRecord;
use App\Services\OFIFormGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OfiRecordController extends Controller
{
    private function rQms018TypeId(): int
    {
        return DocumentType::where('code', 'R-QMS-018')->value('id')
            ?? abort(404, 'DocumentType R-QMS-018 not found.');
    }

    private function templatePath(): string
    {
        $path = base_path('templates/F-QMS-007_template_fixed_v6.docx');

        if (!file_exists($path)) {
            abort(500, 'OFI template file not found.');
        }

        return $path;
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/ofi_forms_tmp');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }

    private function sanitizeBaseFileName(?string $raw, OfiRecord $ofiRecord): string
    {
        $raw = trim((string) $raw);

        if ($raw !== '') {
            $raw = preg_replace('/\.docx$/i', '', $raw);
            $raw = preg_replace('/[^A-Za-z0-9 _\-\(\)]/', '', $raw);
            $raw = trim(preg_replace('/\s+/', ' ', $raw));
        }

        if ($raw !== '') {
            return $raw;
        }

        $fallbackBase = $ofiRecord->ofi_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $ofiRecord->ofi_no)
            : now()->format('Ymd_His');

        return "OFI_{$fallbackBase}";
    }

    private function generateDocxToPath(OfiRecord $ofiRecord, string $outputPath): void
    {
        $generator = new OFIFormGenerator($this->templatePath());
        $generator->generate($ofiRecord->data ?? [], $outputPath);
    }

    private function syncPublishedUploads(OfiRecord $ofiRecord): void
    {
        /** @var Collection<int, DocumentUpload> $uploads */
        $uploads = DocumentUpload::query()
            ->where('ofi_record_id', $ofiRecord->id)
            ->get();

        if ($uploads->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        $tmpDir = $this->ensureTmpDir();

        /** @var DocumentUpload $upload */
        foreach ($uploads as $upload) {
            $fileName = $upload->file_name ?: ('OFI_' . ($ofiRecord->ofi_no ?: $ofiRecord->id) . '.docx');
            $tmpPath = $tmpDir . '/' . uniqid('ofi_sync_', true) . '_' . $fileName;

            $this->generateDocxToPath($ofiRecord, $tmpPath);

            $publicPath = $upload->file_path ?: ('documents/ofi/' . $fileName);
            $disk->put($publicPath, file_get_contents($tmpPath));

            $upload->update([
                'file_name' => $fileName,
                'file_path' => $publicPath,
            ]);

            @unlink($tmpPath);
        }
    }

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

    public function show(OfiRecord $ofiRecord)
    {
        return response()->json([
            'id' => $ofiRecord->id,
            'status' => $ofiRecord->status,
            'data' => $ofiRecord->data,
        ]);
    }

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

        $this->syncPublishedUploads($ofiRecord->fresh());

        return response()->json(['ok' => true]);
    }

    public function download(OfiRecord $ofiRecord)
    {
        $tmpDir = $this->ensureTmpDir();

        $fileName = 'OFI_' . ($ofiRecord->ofi_no ?: now()->format('Ymd_His')) . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('ofi_download_', true) . '_' . $fileName;

        $this->generateDocxToPath($ofiRecord, $outputPath);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, OfiRecord $ofiRecord)
    {
        $data = $request->validate([
            'remarks' => ['nullable', 'string', 'max:1000'],
            'file_name' => ['nullable', 'string', 'max:200'],
        ]);

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

        $ofiRecord = $ofiRecord->fresh();

        $tmpDir = $this->ensureTmpDir();
        $disk = Storage::disk('public');

        $baseName = $this->sanitizeBaseFileName($data['file_name'] ?? null, $ofiRecord);
        $fileName = "{$baseName}.docx";

        $tmpPath = $tmpDir . '/' . uniqid('ofi_publish_', true) . '_' . $fileName;
        $this->generateDocxToPath($ofiRecord, $tmpPath);

        $publicPath = 'documents/ofi/' . $fileName;

        if ($disk->exists($publicPath)) {
            $fileName = "{$baseName}_" . now()->format('His') . ".docx";
            $publicPath = 'documents/ofi/' . $fileName;
        }

        $disk->put($publicPath, file_get_contents($tmpPath));

        $upload = DocumentUpload::create([
            'document_type_id' => $this->rQms018TypeId(),
            'uploaded_by' => auth()->id(),
            'ofi_record_id' => $ofiRecord->id,
            'revision' => null,
            'status' => null,
            'file_name' => $fileName,
            'file_path' => $publicPath,
            'remarks' => $data['remarks'] ?? null,
        ]);

        @unlink($tmpPath);

        return response()->json([
            'ok' => true,
            'upload_id' => $upload->id,
            'ofi_record_id' => $ofiRecord->id,
            'file_name' => $fileName,
        ]);
    }
}