<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class DCRFormGenerator
{
    private TemplateProcessor $template;

    public function __construct(string $templatePath)
    {
        $this->template = new TemplateProcessor($templatePath);
    }

    public function generate(array $data, string $outputPath): void
    {
        // Helpers
        $check = fn($v) => $v ? '✔' : '';

        // Simple text fields
        $textKeys = [
            'date',
            'dcrNo',
            'toFor',
            'from',
            'documentNumber',
            'documentTitle',
            'revisionStatus',
            'changesRequested',
            'reason',
            'requestedBy',
            'deptUnitHead',
            'imrSigDate',
            'approvingSigName',
            'approvingDate',
            'statusNo',
            'statusVersion',
            'statusRevision',
            'effectivityDate',
            'idsDateUpdated',
            'updatedBy',
        ];

        foreach ($textKeys as $k) {
            $this->template->setValue($k, (string) ($data[$k] ?? ''));
        }

        // Checkbox placeholders (✔ or blank)
        $this->template->setValue('amend', $check($data['amend'] ?? false));
        $this->template->setValue('newDoc', $check($data['newDoc'] ?? false));
        $this->template->setValue('deleteDoc', $check($data['deleteDoc'] ?? false));

        $this->template->setValue('requestDenied', $check($data['requestDenied'] ?? false));
        $this->template->setValue('requestAccepted', $check($data['requestAccepted'] ?? false));

        // Save output
        $this->template->saveAs($outputPath);
    }
}