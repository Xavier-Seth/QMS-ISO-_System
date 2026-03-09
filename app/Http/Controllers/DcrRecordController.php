<?php

namespace App\Http\Controllers;

use App\Models\DcrRecord;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\DCRFormGenerator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DcrRecordController extends Controller
{
    private function rQms013TypeId(): int
    {
        $id = DocumentType::where('code', 'R-QMS-013')->value('id');

        if (!$id) {
            abort(404, 'DocumentType R-QMS-013 not found.');
        }

        return (int) $id;
    }

    private function templatePath(): string
    {
        $path = base_path('templates/F-QMS-001 _template.docx');

        if (!file_exists($path)) {
            abort(500, 'DCR template file not found.');
        }

        return $path;
    }

    private function ensureTmpDir(): string
    {
        $tmpDir = storage_path('app/dcr_forms_tmp');

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        return $tmpDir;
    }

    private function sanitizeBaseFileName(?string $raw, DcrRecord $dcrRecord): string
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

        $fallbackBase = $dcrRecord->dcr_no
            ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $dcrRecord->dcr_no)
            : now()->format('Ymd_His');

        return "DCR_{$fallbackBase}";
    }

    private function generateDocxToPath(DcrRecord $dcrRecord, string $outputPath): void
    {
        $generator = new DCRFormGenerator($this->templatePath());
        $generator->generate($dcrRecord->data ?? [], $outputPath);
    }

    private function syncPublishedUploads(DcrRecord $dcrRecord): void
    {
        /** @var Collection<int, DocumentUpload> $uploads */
        $uploads = DocumentUpload::query()
            ->where('dcr_record_id', $dcrRecord->id)
            ->get();

        if ($uploads->isEmpty()) {
            return;
        }

        $disk = Storage::disk('public');
        $tmpDir = $this->ensureTmpDir();

        /** @var DocumentUpload $upload */
        foreach ($uploads as $upload) {
            $fileName = $upload->file_name ?: ('DCR_' . ($dcrRecord->dcr_no ?: $dcrRecord->id) . '.docx');
            $tmpPath = $tmpDir . '/' . uniqid('dcr_sync_', true) . '_' . $fileName;

            $this->generateDocxToPath($dcrRecord, $tmpPath);

            $publicPath = $upload->file_path ?: ('documents/dcr/' . $fileName);
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
        try {
            $payload = $request->all();

            $typeId = DocumentType::where('code', 'R-QMS-013')->value('id');

            if (!$typeId) {
                return response()->json([
                    'message' => 'DocumentType R-QMS-013 not found.',
                ], 422);
            }

            $record = DcrRecord::create([
                'document_type_id' => $typeId,
                'dcr_no' => $payload['dcrNo'] ?? null,
                'to_for' => $payload['toFor'] ?? null,
                'from' => $payload['from'] ?? null,
                'status' => $payload['status'] ?? 'draft',
                'data' => $payload,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            return response()->json(['id' => $record->id]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to save DCR draft.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function show(DcrRecord $dcrRecord)
    {
        return response()->json([
            'id' => $dcrRecord->id,
            'status' => $dcrRecord->status,
            'data' => $dcrRecord->data,
        ]);
    }

    public function update(Request $request, DcrRecord $dcrRecord)
    {
        try {
            $payload = $request->all();

            $dcrRecord->update([
                'dcr_no' => $payload['dcrNo'] ?? $dcrRecord->dcr_no,
                'to_for' => $payload['toFor'] ?? $dcrRecord->to_for,
                'from' => $payload['from'] ?? $dcrRecord->from,
                'status' => $payload['status'] ?? $dcrRecord->status,
                'data' => $payload,
                'updated_by' => auth()->id(),
            ]);

            $this->syncPublishedUploads($dcrRecord->fresh());

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update DCR draft.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }

    public function download(DcrRecord $dcrRecord)
    {
        $tmpDir = $this->ensureTmpDir();

        $fileName = 'DCR_' . ($dcrRecord->dcr_no ?: now()->format('Ymd_His')) . '.docx';
        $outputPath = $tmpDir . '/' . uniqid('dcr_download_', true) . '_' . $fileName;

        $this->generateDocxToPath($dcrRecord, $outputPath);

        return response()->download($outputPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function publish(Request $request, DcrRecord $dcrRecord)
    {
        try {
            $data = $request->validate([
                'remarks' => ['nullable', 'string', 'max:1000'],
                'file_name' => ['nullable', 'string', 'max:200'],
            ]);

            if ($request->has('data')) {
                $payload = (array) $request->input('data', []);
                $dcrRecord->update([
                    'dcr_no' => $payload['dcrNo'] ?? $dcrRecord->dcr_no,
                    'to_for' => $payload['toFor'] ?? $dcrRecord->to_for,
                    'from' => $payload['from'] ?? $dcrRecord->from,
                    'status' => $request->input('status', $dcrRecord->status),
                    'data' => $payload ?: $dcrRecord->data,
                    'updated_by' => auth()->id(),
                ]);
            }

            $dcrRecord = $dcrRecord->fresh();

            $tmpDir = $this->ensureTmpDir();
            $disk = Storage::disk('public');

            $baseName = $this->sanitizeBaseFileName($data['file_name'] ?? null, $dcrRecord);
            $fileName = "{$baseName}.docx";

            $tmpPath = $tmpDir . '/' . uniqid('dcr_publish_', true) . '_' . $fileName;
            $this->generateDocxToPath($dcrRecord, $tmpPath);

            $publicPath = 'documents/dcr/' . $fileName;

            if ($disk->exists($publicPath)) {
                $fileName = "{$baseName}_" . now()->format('His') . ".docx";
                $publicPath = 'documents/dcr/' . $fileName;
            }

            $disk->put($publicPath, file_get_contents($tmpPath));

            $upload = DocumentUpload::create([
                'document_type_id' => $this->rQms013TypeId(),
                'uploaded_by' => auth()->id(),
                'dcr_record_id' => $dcrRecord->id,
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
                'dcr_record_id' => $dcrRecord->id,
                'file_name' => $fileName,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to publish DCR.',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
    }
}