<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = ['series_id', 'code', 'title', 'storage'];

    public function series()
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    // For counting and fetching uploads
    public function uploads()
    {
        return $this->hasMany(DocumentUpload::class, 'document_type_id');
    }
}