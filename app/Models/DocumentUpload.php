<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentUpload extends Model
{
    protected $fillable = [
        'document_type_id',
        'uploaded_by',
        'revision',
        'status',
        'file_name',
        'file_path',
        'remarks',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}