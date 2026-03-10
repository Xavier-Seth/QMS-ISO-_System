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
        'remarks',
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
}