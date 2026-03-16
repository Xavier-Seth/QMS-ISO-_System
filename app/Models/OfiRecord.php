<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfiRecord extends Model
{
    protected $table = 'ofi_records';

    protected $fillable = [
        'document_type_id',
        'ofi_no',
        'ref_no',
        'to',
        'status',
        'workflow_status',
        'resolution_status',
        'data',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isCreatedByAdmin(): bool
    {
        return $this->creator?->role === 'admin';
    }
}