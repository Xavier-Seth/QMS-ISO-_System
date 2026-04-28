<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class QmsDynamicField extends Model
{
    protected $table = 'qms_dynamic_fields';

    protected $fillable = [
        'module',
        'label',
        'field_key',
        'field_type',
        'is_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', strtoupper(trim($module)));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('label')
            ->orderBy('id');
    }

    public static function activeFor(string $module)
    {
        return static::query()
            ->forModule($module)
            ->active()
            ->sorted()
            ->get();
    }

    public function isText(): bool
    {
        return $this->field_type === 'text';
    }

    public function isTextarea(): bool
    {
        return $this->field_type === 'textarea';
    }

    public function isDate(): bool
    {
        return $this->field_type === 'date';
    }
}