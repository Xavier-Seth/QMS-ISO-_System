<?php

namespace App\Services;

use App\Models\QmsTemplate;
use App\Support\QmsTemplateModules;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class QmsTemplateResolver
{
    public function getActiveTemplate(string $module): QmsTemplate
    {
        $module = QmsTemplateModules::ensureAllowed($module);

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
        return $this->getActiveTemplate(QmsTemplateModules::DCR);
    }

    public function getActiveDcrTemplatePath(): string
    {
        return $this->getActiveTemplatePath(QmsTemplateModules::DCR);
    }

    public function getActiveOfiTemplate(): QmsTemplate
    {
        return $this->getActiveTemplate(QmsTemplateModules::OFI);
    }

    public function getActiveOfiTemplatePath(): string
    {
        return $this->getActiveTemplatePath(QmsTemplateModules::OFI);
    }

    public function getActiveCarTemplate(): QmsTemplate
    {
        return $this->getActiveTemplate(QmsTemplateModules::CAR);
    }

    public function getActiveCarTemplatePath(): string
    {
        return $this->getActiveTemplatePath(QmsTemplateModules::CAR);
    }
}
