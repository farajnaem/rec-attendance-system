<?php

declare(strict_types=1);

class TimezoneHelper
{
    public static function utcNow(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public static function toLocal(string $utcDatetime, string $timezone): DateTimeImmutable
    {
        $dt = new DateTimeImmutable($utcDatetime, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone($timezone));
    }

    public static function localWorkDate(DateTimeImmutable $utcNow, string $timezone): string
    {
        return $utcNow->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
    }

    public static function formatArabic(string $utcDatetime, string $timezone): string
    {
        $local = self::toLocal($utcDatetime, $timezone);
        return $local->format('Y-m-d H:i');
    }

    public static function localToUtc(string $localDatetime, string $timezone): DateTimeImmutable
    {
        $dt = new DateTimeImmutable($localDatetime, new DateTimeZone($timezone));
        return $dt->setTimezone(new DateTimeZone('UTC'));
    }

    public static function commonTimezones(): array
    {
        return [
            'Asia/Riyadh' => 'الرياض',
            'Asia/Jerusalem' => 'القدس',
            'Asia/Gaza' => 'غزة',
            'Europe/Rome' => 'إيطاليا',
        ];
    }

    public static function isValid(string $timezone): bool
    {
        return isset(self::commonTimezones()[$timezone]);
    }

    public static function defaultTimezone(): string
    {
        if (!function_exists('app_config')) {
            require_once dirname(__DIR__) . '/config/config.php';
        }
        $tz = app_config()['app']['default_timezone'] ?? 'Asia/Riyadh';
        return is_string($tz) && $tz !== '' ? $tz : 'Asia/Riyadh';
    }
}
