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
     * ─── SECTION 1 — Header metadata ──────────────────────────────────────────
     *   date            string  e.g. "November 23, 2022"
     *   refNo           string  Reference number
     *   to              string  Area Concerned
     *   ofiNo           string  OFI number
     *   from            string  Sender/office
     *   isoClause       string  ISO clause (if applicable)
     *
     * ─── SOURCE checkboxes ────────────────────────────────────────────────────
     *   sourceIqa          bool  true → "✔", false → ""
     *   sourceFeedback     bool
     *   sourceSurvey       bool
     *   sourceSystem       bool
     *   sourceOthersCheck  bool
     *   sourceOthersText   string  Label shown after Others checkbox
     *
     * ─── SECTION 1 — Suggestion ───────────────────────────────────────────────
     *   suggestion      string
     *   deptRepSig1     string  Department Representative name/signature
     *   requestedBySig  string  Requested By name/signature
     *   agreedDate      string  Agreed date for 1st follow-up
     *
     * ─── SECTION 2 — Analysis ─────────────────────────────────────────────────
     *   beneficialImpact  string
     *   associatedRisks   string
     *   action            string
     *   deptRepDate2      string
     *   deptHeadDate2     string
     *
     * ─── SECTION 3 — Risk/Opportunity Assessment ──────────────────────────────
     *   assessmentUpdateNo   bool
     *   assessmentUpdateYes  bool
     *   dateUpdated          string
     *   verifiedBy1          string
     *
     * ─── SECTION 4 — QMS Update ───────────────────────────────────────────────
     *   qmsUpdateNo   bool
     *   qmsUpdateYes  bool
     *   dcrUpdated    string
     *   verifiedBy2   string
     *
     * ─── SECTION 5 — Follow-up rows (4 rows) ─────────────────────────────────
     *   followUp  array of up to 4 items, each with keys:
     *             date, status, effective, auditor, rep
     *
     * ─── SECTION 6 — Case Closed ──────────────────────────────────────────────
     *   imrSig          string  Quality Management Representative
     *   caseClosedDate  string
     *   notedBy         string  Department Head
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

        // Section 3 uses ${s3aNo} / ${s3aYes}
        // Section 4 uses ${s4aNo} / ${s4aYes} — fully independent
        $s3aNo = $check($d('assessmentUpdateNo', false));
        $s3aYes = $check($d('assessmentUpdateYes', false));
        $s4aNo = $check($d('qmsUpdateNo', false));
        $s4aYes = $check($d('qmsUpdateYes', false));

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
            'sourceOthersText' => $d('sourceOthersText') . str_repeat(" ", max(0, 30 - mb_strlen($d('sourceOthersText')))),

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
            'dcrUpdated' => $d('dcrUpdated'),
            'verifiedBy2' => $d('verifiedBy2'),

            // Section 6
            'imrSig' => $d('imrSig'),
            'caseClosedDate' => $d('caseClosedDate'),
            'notedBy' => $d('notedBy'),
        ], $rows);
    }
}