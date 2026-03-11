<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentUpload extends Model
{
    protected $fillable = [
        'document_type_id',
        'uploaded_by',
        'revision',
        'ofi_record_id',
        'dcr_record_id',
        'status',
        'file_name',
        'file_path',
        'storage_disk',
        'preview_disk',
        'preview_path',
        'preview_mime',
        'preview_generated_at',
        'preview_last_accessed_at',
        'preview_source_hash',
        'preview_size',
        'remarks',
    ];

    protected $casts = [
        'preview_generated_at' => 'datetime',
        'preview_last_accessed_at' => 'datetime',
        'preview_size' => 'integer',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function ofiRecord(): BelongsTo
    {
        return $this->belongsTo(OfiRecord::class, 'ofi_record_id');
    }

    public function dcrRecord(): BelongsTo
    {
        return $this->belongsTo(DcrRecord::class, 'dcr_record_id');
    }

    public function getStorageDiskName(): string
    {
        return $this->storage_disk ?: 'public';
    }

    public function getPreviewDiskName(): ?string
    {
        return $this->preview_disk ?: null;
    }

    public function hasPreviewCache(): bool
    {
        return filled($this->preview_disk) && filled($this->preview_path);
    }

    public function isGeneratedRecordDocument(): bool
    {
        return !is_null($this->ofi_record_id) || !is_null($this->dcr_record_id);
    }

    public function markPreviewAccessed(): void
    {
        $this->forceFill([
            'preview_last_accessed_at' => now(),
        ])->save();
    }

    public function clearPreviewCacheMeta(): void
    {
        $this->forceFill([
            'preview_disk' => null,
            'preview_path' => null,
            'preview_mime' => null,
            'preview_generated_at' => null,
            'preview_last_accessed_at' => null,
            'preview_source_hash' => null,
            'preview_size' => null,
        ])->save();
    }
}