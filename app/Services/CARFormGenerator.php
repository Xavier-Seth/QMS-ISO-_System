<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class CARFormGenerator
{
    private string $templatePath;

    public function __construct(string $templatePath)
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template not found: {$templatePath}");
        }

        $this->templatePath = $templatePath;
    }

    /**
     * IMPORTANT:
     * This generator requires template placeholders like ${fieldName}.
     * Templates without placeholders will NOT work.
     */
    public function generate(array $data, string $outputPath): void
    {
        $processor = new TemplateProcessor($this->templatePath);
        $values = $this->buildValues($data);

        foreach ($values as $placeholder => $value) {
            $processor->setValue(
                $placeholder,
                htmlspecialchars((string) ($value ?? ''), ENT_COMPAT | ENT_XML1)
            );
        }

        $processor->saveAs($outputPath);
    }

    private function buildValues(array $data): array
    {
        $d = fn(string $key, mixed $default = '') => $data[$key] ?? $default;
        $check = fn(mixed $value): string => $value ? '✔' : '';

        // Follow-up rows (10 rows)
        $followUp = $d('followUp', []);
        $rows = [];

        for ($i = 1; $i <= 10; $i++) {
            $row = $followUp[$i - 1] ?? [];

            $rows["f{$i}_date"] = $row['date'] ?? '';
            $rows["f{$i}_status"] = $row['status'] ?? '';
            $rows["f{$i}_auditor"] = $row['auditor'] ?? '';
            $rows["f{$i}_rep"] = $row['rep'] ?? '';
            $rows["f{$i}_effective"] = $row['effective'] ?? '';
        }

        // Risk / Opportunity section
        $riskUpdate = $d('riskUpdate', null); // "NO" | "YES" | null
        $riskNo = $check($d('riskNo', false) || $riskUpdate === 'NO');
        $riskYes = $check($d('riskYes', false) || $riskUpdate === 'YES');

        // IMS section
        $imsUpdate = $d('imsUpdate', null); // "NO" | "YES" | null
        $imsNo = $check($d('imsNo', false) || $imsUpdate === 'NO');
        $imsYes = $check($d('imsYes', false) || $imsUpdate === 'YES');

        return array_merge([
            // Header
            'deptSection' => $d('deptSection'),
            'refNo' => $d('refNo'),
            'auditor' => $d('auditor'),
            'carNo' => $d('carNo'),
            'dateRep' => $d('dateRep'),
            'isoClause' => $d('isoClause'),

            // Source checkboxes
            'audit' => $check($d('audit', false)),
            'complaint' => $check($d('complaint', false)),
            'nonConForm' => $check($d('nonConForm', false)),

            // Section 1
            'descript' => $d('descript'),
            'objective' => $d('objective'),
            'conseq' => $d('conseq'),
            'depRep' => $d('depRep'),
            'sigAuditor' => $d('sigAuditor'),
            'agreedDate' => $d('agreedDate'),

            // Section 2
            'correction' => $d('correction'),
            'actualdate' => $d('actualdate'),
            'notedby' => $d('notedby'),

            // Section 3
            'rootCause' => $d('rootCause'),
            'rootCauseBy' => $d('rootCauseBy'),
            'rootCauseDate' => $d('rootCauseDate'),
            'rootCauseNotedBy' => $d('rootCauseNotedBy'),
            'rootCauseNotedDate' => $d('rootCauseNotedDate'),

            // Section 4
            'correctiveAction' => $d('correctiveAction'),
            'correctiveActionDate' => $d('correctiveActionDate'),
            'correctiveActionNotedBy' => $d('correctiveActionNotedBy'),

            // Section 5
            'riskNo' => $riskNo,
            'riskYes' => $riskYes,
            'riskDateUpdated' => $d('riskDateUpdated'),
            'riskVerifiedBy' => $d('riskVerifiedBy'),

            // Section 6
            'imsNo' => $imsNo,
            'imsYes' => $imsYes,
            'imsDcrNo' => $d('imsDcrNo'),
            'imsVerifiedBy' => $d('imsVerifiedBy'),

            // Section 8
            'caseClosed' => $d('caseClosed'),
            'imrSig' => $d('imrSig'),
            'caseClosedDate' => $d('caseClosedDate'),
            'deptHeadNotedBy' => $d('deptHeadNotedBy'),

            // Section 9
            'causeMan' => $d('causeMan'),
            'causeMachine' => $d('causeMachine'),
            'causeMaterial' => $d('causeMaterial'),
            'causeMethod' => $d('causeMethod'),
            'causeOthers' => $d('causeOthers'),
            'effectProblem' => $d('effectProblem'),
        ], $rows);
    }
}