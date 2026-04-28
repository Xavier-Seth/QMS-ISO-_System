<?php

namespace App\Services;

use App\Models\QmsDynamicField;
use App\Support\QmsTemplateModules;
use Illuminate\Validation\ValidationException;

class QmsDynamicFieldValidator
{
    private const RESERVED_FIELD_KEYS_BY_MODULE = [
        QmsTemplateModules::DCR => [
            'date',
            'dcrNo',
            'toFor',
            'from',
            'amend',
            'newDoc',
            'deleteDoc',
            'documentNumber',
            'documentTitle',
            'revisionStatus',
            'changesRequested',
            'reason',
            'requestedBy',
            'deptUnitHead',
            'requestDenied',
            'requestAccepted',
            'requestDecision',
            'imrSigDate',
            'approvingSigName',
            'approvingDate',
            'statusNo',
            'statusVersion',
            'statusRevision',
            'effectivityDate',
            'idsDateUpdated',
            'updatedBy',
        ],
        QmsTemplateModules::OFI => [
            'date',
            'refNo',
            'to',
            'ofiNo',
            'from',
            'isoClause',
            'sourceIqa',
            'sourceFeedback',
            'sourceSurvey',
            'sourceSystem',
            'sourceOthersCheck',
            'sourceOthersText',
            'suggestion',
            'deptRepSig1',
            'requestedBySig',
            'agreedDate',
            'beneficialImpact',
            'associatedRisks',
            'action',
            'deptRepDate2',
            'deptHeadDate2',
            'assessmentUpdate',
            'assessmentUpdateNo',
            'assessmentUpdateYes',
            's3aNo',
            's3aYes',
            'dateUpdated',
            'verifiedBy1',
            'imsUpdate',
            'qmsUpdateNo',
            'qmsUpdateYes',
            's4aNo',
            's4aYes',
            'dcrNo',
            'dcrUpdated',
            'verifiedBy2',
            'followSig',
            'followsig',
            'f1_date',
            'f1_status',
            'f1_effective',
            'f1_auditor',
            'f1_rep',
            'f2_date',
            'f2_status',
            'f2_effective',
            'f2_auditor',
            'f2_rep',
            'f3_date',
            'f3_status',
            'f3_effective',
            'f3_auditor',
            'f3_rep',
            'f4_date',
            'f4_status',
            'f4_effective',
            'f4_auditor',
            'f4_rep',
            'imrSig',
            'caseClosedDate',
            'notedBy',
        ],
        QmsTemplateModules::CAR => [
            'deptSection',
            'refNo',
            'auditor',
            'carNo',
            'dateRep',
            'isoClause',
            'audit',
            'complaint',
            'nonConForm',
            'descript',
            'objective',
            'conseq',
            'depRep',
            'sigAuditor',
            'agreedDate',
            'correction',
            'actualdate',
            'notedby',
            'rootCause',
            'rootCauseBy',
            'rootCauseDate',
            'rootCauseNotedBy',
            'rootCauseNotedDate',
            'correctiveAction',
            'correctiveActionDate',
            'correctiveActionNotedBy',
            'riskUpdate',
            'riskNo',
            'riskYes',
            'riskDateUpdated',
            'riskVerifiedBy',
            'imsUpdate',
            'imsNo',
            'imsYes',
            'imsDcrNo',
            'imsVerifiedBy',
            'caseClosed',
            'imrSig',
            'caseClosedDate',
            'deptHeadNotedBy',
            'causeMan',
            'causeMachine',
            'causeMaterial',
            'causeMethod',
            'causeOthers',
            'effectProblem',
            'f1_date',
            'f1_status',
            'f1_auditor',
            'f1_rep',
            'f1_effective',
            'f2_date',
            'f2_status',
            'f2_auditor',
            'f2_rep',
            'f2_effective',
            'f3_date',
            'f3_status',
            'f3_auditor',
            'f3_rep',
            'f3_effective',
            'f4_date',
            'f4_status',
            'f4_auditor',
            'f4_rep',
            'f4_effective',
            'f5_date',
            'f5_status',
            'f5_auditor',
            'f5_rep',
            'f5_effective',
            'f6_date',
            'f6_status',
            'f6_auditor',
            'f6_rep',
            'f6_effective',
            'f7_date',
            'f7_status',
            'f7_auditor',
            'f7_rep',
            'f7_effective',
            'f8_date',
            'f8_status',
            'f8_auditor',
            'f8_rep',
            'f8_effective',
            'f9_date',
            'f9_status',
            'f9_auditor',
            'f9_rep',
            'f9_effective',
            'f10_date',
            'f10_status',
            'f10_auditor',
            'f10_rep',
            'f10_effective',
        ],
    ];

    public function validateRequiredFields(string $module, array $payload): void
    {
        $module = QmsTemplateModules::ensureAllowed($module);
        $dynamicValues = $payload['dynamic'] ?? [];

        if (!is_array($dynamicValues)) {
            $dynamicValues = [];
        }

        $errors = [];

        QmsDynamicField::query()
            ->forModule($module)
            ->active()
            ->where('is_required', true)
            ->sorted()
            ->get()
            ->each(function (QmsDynamicField $field) use ($dynamicValues, &$errors) {
                $fieldKey = (string) $field->field_key;
                $value = $dynamicValues[$fieldKey] ?? null;

                if ($this->isBlankValue($value)) {
                    $errors["dynamic.{$fieldKey}"] = [
                        "{$field->label} is required.",
                    ];
                }
            });

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function ensureFieldKeyIsNotReserved(string $module, string $fieldKey): void
    {
        $module = QmsTemplateModules::ensureAllowed($module);

        if (!$this->isReservedFieldKey($module, $fieldKey)) {
            return;
        }

        throw ValidationException::withMessages([
            'field_key' => "The placeholder key {$fieldKey} is reserved by the base {$module} form.",
        ]);
    }

    public function isReservedFieldKey(string $module, string $fieldKey): bool
    {
        $module = QmsTemplateModules::ensureAllowed($module);
        $normalizedKey = strtolower(trim($fieldKey));

        return in_array(
            $normalizedKey,
            array_map('strtolower', self::RESERVED_FIELD_KEYS_BY_MODULE[$module] ?? []),
            true
        );
    }

    private function isBlankValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_bool($value)) {
            return $value === false;
        }

        if (is_array($value) || is_object($value)) {
            return true;
        }

        return false;
    }
}
