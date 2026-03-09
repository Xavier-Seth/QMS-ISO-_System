<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DcrRecord extends Model
{
    protected $table = 'dcr_records';

    protected $fillable = [
        'document_type_id',
        'dcr_no',
        'to_for',
        'from',
        'status',
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
}