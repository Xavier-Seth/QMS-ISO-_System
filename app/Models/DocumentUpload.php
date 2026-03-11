<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function scopeObsolete(Builder $query): Builder
    {
        return $query->where('status', 'Obsolete');
    }

    public function scopeForManuals(Builder $query): Builder
    {
        return $query->whereHas('documentType', function (Builder $q) {
            $q->manuals();
        });
    }

    public function scopeForManualCategory(Builder $query, string $category): Builder
    {
        return $query->whereHas('documentType', function (Builder $q) use ($category) {
            $q->manuals()->manualCategory($category);
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isObsolete(): bool
    {
        return $this->status === 'Obsolete';
    }
}