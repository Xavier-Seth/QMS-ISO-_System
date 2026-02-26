<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSeries extends Model
{
    protected $table = 'document_series';
    protected $fillable = ['code_prefix', 'name'];

    public function types()
    {
        return $this->hasMany(DocumentType::class, 'series_id');
    }
}