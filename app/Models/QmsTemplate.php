<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QmsTemplate extends Model
{
    protected $table = 'qms_templates';

    protected $fillable = [
        'module',
        'name',
        'original_file_name',
        'file_name',
        'file_path',
        'storage_disk',
        'is_active',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', strtoupper(trim($module)));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public static function activeFor(string $module): ?self
    {
        return static::query()
            ->forModule($module)
            ->active()
            ->latestFirst()
            ->first();
    }

    public function getStorageDiskName(): string
    {
        return $this->storage_disk ?: 'public';
    }

    public function isForModule(string $module): bool
    {
        return strtoupper((string) $this->module) === strtoupper(trim($module));
    }
}