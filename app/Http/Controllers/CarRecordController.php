<?php

namespace App\Http\Controllers;

use App\Models\CarRecord;
use App\Models\DocumentType;
use App\Models\DocumentUpload;
use App\Services\CARFormGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CarRecordController extends Controller
{
    /**
     * Store (create draft)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'document_type_id' => 'required|exists:document_types,id',
            'data' => 'nullable|array',
        ]);

        $record = CarRecord::create([
            'document_type_id' => $data['document_type_id'],
            'data' => $data['data'] ?? [],
            'status' => 'draft',
            'workflow_status' => null,
            'resolution_status' => 'open',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json($record);
    }

    /**
     * Update draft
     */
    public function update(Request $request, CarRecord $carRecord)
    {
        $data = $request->validate([
            'data' => 'nullable|array',
        ]);

        $carRecord->update([
            'data' => $data['data'] ?? $carRecord->data,
            'updated_by' => Auth::id(),
        ]);

        return response()->json($carRecord);
    }

    /**
     * Submit to admin
     */
    public function submit(CarRecord $carRecord)
    {
        $carRecord->update([
            'status' => 'submitted',
            'workflow_status' => 'pending',
        ]);

        return response()->json(['message' => 'CAR submitted']);
    }

    /**
     * Approve (admin)
     */
    public function approve(CarRecord $carRecord)
    {
        DB::transaction(function () use ($carRecord) {
            $carRecord->update([
                'workflow_status' => 'approved',
                'resolution_status' => 'open',
            ]);

            $this->publish($carRecord);
        });

        return response()->json(['message' => 'CAR approved']);
    }

    /**
     * Reject (admin)
     */
    public function reject(Request $request, CarRecord $carRecord)
    {
        $data = $request->validate([
            'reason' => 'required|string',
        ]);

        $carRecord->update([
            'workflow_status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
        ]);

        return response()->json(['message' => 'CAR rejected']);
    }

    /**
     * Publish → generate DOCX + save to document_uploads
     * FINAL OUTPUT SHOULD GO TO R-QMS-017, NOT F-QMS-006
     */
    private function publish(CarRecord $carRecord): void
    {
        $templatePath = config('qms_templates.car.path');

        if (!is_string($templatePath) || $templatePath === '' || !file_exists($templatePath)) {
            throw new \RuntimeException('CAR template not found.');
        }

        $recordDocumentType = DocumentType::where('code', 'R-QMS-017')->first();

        if (!$recordDocumentType) {
            throw new \RuntimeException('R-QMS-017 document type not found.');
        }

        $generator = new CARFormGenerator($templatePath);

        $tempDir = storage_path('app/car_forms_tmp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $fileName = 'CAR_' . now()->timestamp . '.docx';
        $tempPath = $tempDir . '/' . $fileName;

        $generator->generate($carRecord->data ?? [], $tempPath);

        $storagePath = 'documents/car/' . $fileName;

        Storage::disk('public')->put($storagePath, file_get_contents($tempPath));

        DocumentUpload::create([
            'document_type_id' => $recordDocumentType->id, // ✅ R-QMS-017
            'uploaded_by' => Auth::id(),
            'car_record_id' => $carRecord->id,
            'file_name' => $fileName,
            'file_path' => $storagePath,
            'storage_disk' => 'public',
            'status' => 'Active',
        ]);

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}