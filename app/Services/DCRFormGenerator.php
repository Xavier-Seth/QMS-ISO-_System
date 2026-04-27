<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class DCRFormGenerator
{
    private TemplateProcessor $template;
    private QmsDynamicPlaceholderNormalizer $dynamicPlaceholderNormalizer;

    public function __construct(
        string $templatePath,
        ?QmsDynamicPlaceholderNormalizer $dynamicPlaceholderNormalizer = null
    )
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template not found: {$templatePath}");
        }

        $this->template = new TemplateProcessor($templatePath);
        $this->dynamicPlaceholderNormalizer = $dynamicPlaceholderNormalizer
            ?? new QmsDynamicPlaceholderNormalizer();
    }

    public function generate(array $data, string $outputPath): void
    {
        $values = $this->buildValues($data);

        foreach ($values as $placeholder => $value) {
            $this->template->setValue(
                $placeholder,
                htmlspecialchars((string) ($value ?? ''), ENT_COMPAT | ENT_XML1)
            );
        }

        $this->template->saveAs($outputPath);
    }

    private function buildValues(array $data): array
    {
        $d = fn(string $key, mixed $default = '') => $data[$key] ?? $default;
        $check = fn(mixed $value): string => $value ? '✔' : '';

        $baseValues = [
            // Header
            'date' => $d('date'),
            'dcrNo' => $d('dcrNo'),
            'toFor' => $d('toFor'),
            'from' => $d('from'),

            // Document type checkboxes
            'amend' => $check($d('amend', false)),
            'newDoc' => $check($d('newDoc', false)),
            'deleteDoc' => $check($d('deleteDoc', false)),

            // Section 1
            'documentNumber' => $d('documentNumber'),
            'documentTitle' => $d('documentTitle'),
            'revisionStatus' => $d('revisionStatus'),

            // Section 2
            'changesRequested' => $d('changesRequested'),
            'reason' => $d('reason'),
            'requestedBy' => $d('requestedBy'),
            'deptUnitHead' => $d('deptUnitHead'),

            // Section 3
            'requestDenied' => $check(
                $d('requestDenied', false) || $d('requestDecision') === 'DENIED'
            ),
            'requestAccepted' => $check(
                $d('requestAccepted', false) || $d('requestDecision') === 'ACCEPTED'
            ),
            'imrSigDate' => $d('imrSigDate'),

            // Section 4
            'approvingSigName' => $d('approvingSigName'),
            'approvingDate' => $d('approvingDate'),

            // Section 5
            'statusNo' => $d('statusNo'),
            'statusVersion' => $d('statusVersion'),
            'statusRevision' => $d('statusRevision'),
            'effectivityDate' => $d('effectivityDate'),
            'idsDateUpdated' => $d('idsDateUpdated'),
            'updatedBy' => $d('updatedBy'),
        ];

        $dynamicValues = $this->dynamicPlaceholderNormalizer->normalize($d('dynamic', []));

        return array_merge($dynamicValues, $baseValues);
    }
}
