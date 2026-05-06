<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = [
        'system_name',
        'institution_name',
        'office_name',
        'maintenance_mode',
        'e_signature_path',
        'logo_path',
        'backup_frequency',
        'storage_location',
        'auto_backup',
    ];

    public function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'auto_backup' => 'boolean',
        ];
    }

    public static function instance(): self
    {
        return static::firstOrCreate([], [
            'system_name' => 'Quality Management System',
            'institution_name' => 'Leyte Normal University',
            'office_name' => 'QMS (ISO) Office',
            'maintenance_mode' => false,
            'backup_frequency' => 'weekly',
            'storage_location' => 'local',
            'auto_backup' => false,
        ]);
    }
}
