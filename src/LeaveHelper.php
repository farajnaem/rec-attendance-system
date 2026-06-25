<?php

declare(strict_types=1);

class LeaveHelper
{
    public const TYPES = [
        'sick' => 'إجازة مرضية',
        'emergency' => 'إجازة طارئة',
        'regular' => 'إجازة عادية',
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public static function isValid(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
