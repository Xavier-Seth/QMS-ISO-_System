<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\DocumentUpload;

class DocumentType extends Model
{
    protected $fillable = ['series_id', 'code', 'title', 'storage'];

    public function series()
    {
        return $this->belongsTo(DocumentSeries::class, 'series_id');
    }

    public function uploads()
    {
        return $this->hasMany(DocumentUpload::class, 'document_type_id');
    }
}