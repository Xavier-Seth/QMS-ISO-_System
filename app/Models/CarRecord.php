<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarRecord extends Model
{
    protected $table = 'car_records';

    protected $fillable = [
        'document_type_id',
        'car_no',
        'ref_no',
        'dept_section',
        'auditor',
        'status',
        'workflow_status',
        'resolution_status',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'data',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'data' => 'array',
        'rejected_at' => 'datetime',
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

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}