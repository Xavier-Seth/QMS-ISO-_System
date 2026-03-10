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
        $check = fn($v) => $v ? '✔' : '';

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

        $this->template->setValue('amend', $check($data['amend'] ?? false));
        $this->template->setValue('newDoc', $check($data['newDoc'] ?? false));
        $this->template->setValue('deleteDoc', $check($data['deleteDoc'] ?? false));

        $decision = $data['requestDecision'] ?? null;

        $this->template->setValue(
            'requestDenied',
            $check(($data['requestDenied'] ?? false) || $decision === 'DENIED')
        );

        $this->template->setValue(
            'requestAccepted',
            $check(($data['requestAccepted'] ?? false) || $decision === 'ACCEPTED')
        );

        $this->template->saveAs($outputPath);
    }
}