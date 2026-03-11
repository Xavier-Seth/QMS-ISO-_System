<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\DocumentTypeRevision;

class DocumentType extends Model
{
    protected $fillable = [
        'series_id',
        'code',
        'title',
        'manual_category',
        'manual_access',
        'storage',
        'initial_issue_date',
        'status',
        'status_note',
        'requires_revision',
    ];

    protected $casts = [
        'initial_issue_date' => 'date',
        'requires_revision' => 'boolean',
    ];

    public function series(): BelongsTo
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(DocumentUpload::class, 'document_type_id')->latest('id');
    }

    public function activeUpload(): HasOne
    {
        return $this->hasOne(DocumentUpload::class, 'document_type_id')
            ->where('status', 'Active')
            ->latestOfMany('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DocumentTypeRevision::class, 'document_type_id')
            ->orderBy('revision_no');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeManuals(Builder $query): Builder
    {
        return $query->whereHas('series', function (Builder $q) {
            $q->where('code_prefix', 'MANUAL');
        });
    }

    public function scopeManualCategory(Builder $query, string $category): Builder
    {
        return $query->where('manual_category', strtoupper($category));
    }

    public function scopeManualAccess(Builder $query, string $access): Builder
    {
        return $query->where('manual_access', strtolower($access));
    }

    public function scopeControlled(Builder $query): Builder
    {
        return $query->where('manual_access', 'controlled');
    }

    public function scopeUncontrolled(Builder $query): Builder
    {
        return $query->where('manual_access', 'uncontrolled');
    }

    public function isManual(): bool
    {
        return $this->manual_category !== null && $this->manual_access !== null;
    }

    public function isControlledManual(): bool
    {
        return $this->isManual() && $this->manual_access === 'controlled';
    }

    public function isUncontrolledManual(): bool
    {
        return $this->isManual() && $this->manual_access === 'uncontrolled';
    }
}