<?php

namespace App\Support;

use InvalidArgumentException;

final class QmsTemplateModules
{
    public const DCR = 'DCR';
    public const OFI = 'OFI';
    public const CAR = 'CAR';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DCR,
            self::OFI,
            self::CAR,
        ];
    }

    public static function normalize(string $module): string
    {
        return strtoupper(trim($module));
    }

    public static function isAllowed(string $module): bool
    {
        return in_array(self::normalize($module), self::all(), true);
    }

    public static function ensureAllowed(string $module): string
    {
        $normalizedModule = self::normalize($module);

        if (!in_array($normalizedModule, self::all(), true)) {
            throw new InvalidArgumentException("Unsupported QMS template module: {$module}");
        }

        return $normalizedModule;
    }
}
