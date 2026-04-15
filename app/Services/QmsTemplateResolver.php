<?php

namespace App\Services;

use App\Models\QmsTemplate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QmsTemplateResolver
{
    public function getActiveTemplate(string $module): QmsTemplate
    {
        $template = QmsTemplate::activeFor($module);

        if (!$template) {
            throw new RuntimeException(
                strtoupper(trim($module)) . ' template is not configured. Please upload and activate a template in System Settings.'
            );
        }

        return $template;
    }

    public function getActiveTemplatePath(string $module): string
    {
        $template = $this->getActiveTemplate($module);

        $diskName = $template->getStorageDiskName();
        $disk = Storage::disk($diskName);
        $filePath = $template->file_path;

        if (!$filePath || !$disk->exists($filePath)) {
            throw new RuntimeException(
                strtoupper(trim($module)) . ' active template file is missing from storage. Please re-upload the template in System Settings.'
            );
        }

        return $disk->path($filePath);
    }

    public function getActiveDcrTemplate(): QmsTemplate
    {
        return $this->getActiveTemplate('DCR');
    }

    public function getActiveDcrTemplatePath(): string
    {
        return $this->getActiveTemplatePath('DCR');
    }
}