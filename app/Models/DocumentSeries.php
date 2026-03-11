<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = [
        'code_prefix',
        'name',
    ];

    public function types(): HasMany
    {
        return $this->hasMany(DocumentType::class, 'series_id');
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('code_prefix', 'MANUAL');
    }
}