<?php

namespace App\Services;

class QmsDynamicPlaceholderNormalizer
{
    public function normalize(mixed $dynamic): array
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
