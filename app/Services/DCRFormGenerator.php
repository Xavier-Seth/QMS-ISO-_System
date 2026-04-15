<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class DCRFormGenerator
{
    private TemplateProcessor $template;

    public function __construct(string $templatePath)
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template not found: {$templatePath}");
        }

        $this->template = new TemplateProcessor($templatePath);
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

        $dynamicValues = $this->normalizeDynamicValues($d('dynamic', []));

        return array_merge($dynamicValues, $baseValues);
    }

    private function normalizeDynamicValues(mixed $dynamic): array
    {
        if (!is_array($dynamic)) {
            return [];
        }

        $normalized = [];

        foreach ($dynamic as $key => $value) {
            $placeholder = trim((string) $key);

            if ($placeholder === '') {
                continue;
            }

            // Keep placeholder names safe and predictable
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $placeholder)) {
                continue;
            }

            if (is_bool($value)) {
                $normalized[$placeholder] = $value ? '✔' : '';
                continue;
            }

            if (is_array($value) || is_object($value)) {
                $normalized[$placeholder] = '';
                continue;
            }

            $normalized[$placeholder] = (string) ($value ?? '');
        }

        return $normalized;
    }
}
