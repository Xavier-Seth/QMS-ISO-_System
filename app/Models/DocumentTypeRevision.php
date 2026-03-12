<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTypeRevision extends Model
{
    protected $fillable = [
        'document_type_id',
        'revision_no',
        'revision_date',
    ];

    protected $casts = [
        'revision_date' => 'date',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }
}