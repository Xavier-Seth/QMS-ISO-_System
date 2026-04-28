<?php

namespace App\Services;

use App\Support\QmsTemplateModules;

class DcrDynamicFieldValidator
{
    public function __construct(
        private QmsDynamicFieldValidator $validator
    ) {
    }

    public function validateRequiredFields(array $payload): void
    {
        $this->validator->validateRequiredFields(QmsTemplateModules::DCR, $payload);
    }

    public function ensureFieldKeyIsNotReserved(string $fieldKey): void
    {
        $this->validator->ensureFieldKeyIsNotReserved(QmsTemplateModules::DCR, $fieldKey);
    }

    public function isReservedFieldKey(string $fieldKey): bool
    {
        return $this->validator->isReservedFieldKey(QmsTemplateModules::DCR, $fieldKey);
    }
}
