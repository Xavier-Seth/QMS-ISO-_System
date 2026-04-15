<?php

namespace App\Services;

use App\Models\QmsDynamicField;
use Illuminate\Validation\ValidationException;

class DcrDynamicFieldValidator
{
    private const MODULE = 'DCR';

    private const RESERVED_FIELD_KEYS = [
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
    ];

    public function validateRequiredFields(array $payload): void
    {
        $dynamicValues = $payload['dynamic'] ?? [];

        if (!is_array($dynamicValues)) {
            $dynamicValues = [];
        }

        $errors = [];

        QmsDynamicField::query()
            ->forModule(self::MODULE)
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

    public function ensureFieldKeyIsNotReserved(string $fieldKey): void
    {
        if (!$this->isReservedFieldKey($fieldKey)) {
            return;
        }

        throw ValidationException::withMessages([
            'field_key' => "The placeholder key {$fieldKey} is reserved by the base DCR form.",
        ]);
    }

    public function isReservedFieldKey(string $fieldKey): bool
    {
        $normalizedKey = strtolower(trim($fieldKey));

        return in_array(
            $normalizedKey,
            array_map('strtolower', self::RESERVED_FIELD_KEYS),
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

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
