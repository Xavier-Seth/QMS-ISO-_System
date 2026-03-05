<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class OFIFormGenerator
{
    private string $templatePath;

    public function __construct(string $templatePath)
    {
        if (!file_exists($templatePath)) {
            throw new \InvalidArgumentException("Template not found: $templatePath");
        }
        $this->templatePath = $templatePath;
    }

    /**
     * Fill and save the OFI form.
     *
     * @param array $data  See buildValues() for all accepted keys.
     * @param string $outputPath  Where to save the filled DOCX.
     */
    public function generate(array $data, string $outputPath): void
    {
        $processor = new TemplateProcessor($this->templatePath);
        $values = $this->buildValues($data);

        foreach ($values as $placeholder => $value) {
            $processor->setValue($placeholder, htmlspecialchars($value ?? '', ENT_COMPAT | ENT_XML1));
        }

        $processor->saveAs($outputPath);
    }

    /**
     * Map incoming $data keys to every ${placeholder} in the template.
     * All keys are optional; missing ones default to an empty string.
     *
     * Supports BOTH:
     *  - "generate controller" style booleans:
     *      assessmentUpdateNo/Yes, qmsUpdateNo/Yes, dcrUpdated
     *  - "saved JSON" style raw fields from Vue:
     *      assessmentUpdate ("NO"/"YES"), imsUpdate ("NO"/"YES"), dcrNo
     */
    private function buildValues(array $data): array
    {
        $d = fn(string $key, mixed $default = '') => $data[$key] ?? $default;
        $check = fn(mixed $val): string => $val ? '✔' : '';

        // Follow-up rows (4 rows)
        $followUp = $d('followUp', []);
        $rows = [];
        for ($i = 1; $i <= 4; $i++) {
            $row = $followUp[$i - 1] ?? [];
            $rows["f{$i}_date"] = $row['date'] ?? '';
            $rows["f{$i}_status"] = $row['status'] ?? '';
            $rows["f{$i}_effective"] = $row['effective'] ?? '';
            $rows["f{$i}_auditor"] = $row['auditor'] ?? '';
            $rows["f{$i}_rep"] = $row['rep'] ?? '';
        }

        /**
         * Section 3 uses template placeholders: ${s3aNo} / ${s3aYes}
         * Section 4 uses template placeholders: ${s4aNo} / ${s4aYes}
         *
         * Support two input styles:
         *  A) boolean keys (from your current controller):
         *     assessmentUpdateNo/Yes, qmsUpdateNo/Yes
         *  B) radio value keys (from Vue / DB JSON):
         *     assessmentUpdate: "NO"|"YES"
         *     imsUpdate: "NO"|"YES"
         */
        $assessmentRadio = $d('assessmentUpdate', null); // "NO" | "YES" | null
        $imsRadio = $d('imsUpdate', null);               // "NO" | "YES" | null

        $s3aNo = $check($d('assessmentUpdateNo', false) || $assessmentRadio === 'NO');
        $s3aYes = $check($d('assessmentUpdateYes', false) || $assessmentRadio === 'YES');

        $s4aNo = $check($d('qmsUpdateNo', false) || $imsRadio === 'NO');
        $s4aYes = $check($d('qmsUpdateYes', false) || $imsRadio === 'YES');

        // DCR No placeholder in template is ${dcrUpdated}
        // Support either 'dcrUpdated' (controller) OR 'dcrNo' (Vue/DB JSON)
        $dcrUpdated = $d('dcrUpdated', $d('dcrNo'));

        return array_merge([
            // Header
            'date' => $d('date'),
            'refNo' => $d('refNo'),
            'to' => $d('to'),
            'ofiNo' => $d('ofiNo'),
            'from' => $d('from'),
            'isoClause' => $d('isoClause'),

            // Source checkboxes
            'sourceIqa' => $check($d('sourceIqa', false)),
            'sourceFeedback' => $check($d('sourceFeedback', false)),
            'sourceSurvey' => $check($d('sourceSurvey', false)),
            'sourceSystem' => $check($d('sourceSystem', false)),
            'sourceOthersCheck' => $check($d('sourceOthersCheck', false)),
            'sourceOthersText' => $d('sourceOthersText') . str_repeat(" ", max(0, 30 - mb_strlen($d('sourceOthersText')))),

            // Section 1
            'suggestion' => $d('suggestion'),
            'deptRepSig1' => $d('deptRepSig1'),
            'requestedBySig' => $d('requestedBySig'),
            'agreedDate' => $d('agreedDate'),

            // Section 2
            'beneficialImpact' => $d('beneficialImpact'),
            'associatedRisks' => $d('associatedRisks'),
            'action' => $d('action'),
            'deptRepDate2' => $d('deptRepDate2'),
            'deptHeadDate2' => $d('deptHeadDate2'),

            // Section 3 — independent placeholders
            's3aNo' => $s3aNo,
            's3aYes' => $s3aYes,
            'dateUpdated' => $d('dateUpdated'),
            'verifiedBy1' => $d('verifiedBy1'),

            // Section 4 — independent placeholders
            's4aNo' => $s4aNo,
            's4aYes' => $s4aYes,
            'dcrUpdated' => $dcrUpdated,
            'verifiedBy2' => $d('verifiedBy2'),

            // Section 5 — Signature (template placeholder: ${followsig})
            'followsig' => $d('followSig'),

            // Section 6
            'imrSig' => $d('imrSig'),
            'caseClosedDate' => $d('caseClosedDate'),
            'notedBy' => $d('notedBy'),
        ], $rows);
    }
}