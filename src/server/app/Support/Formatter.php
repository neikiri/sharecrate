<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\I18n;
use App\Models\Setting;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Locale aware formatting of sizes, numbers and dates.
 */
final class Formatter
{
    private static ?DateTimeZone $displayZone = null;

    public static function displayTimezone(): DateTimeZone
    {
        if (self::$displayZone instanceof DateTimeZone) {
            return self::$displayZone;
        }

        $name = 'Europe/Prague';

        try {
            $configured = Setting::get('timezone', $name);

            if (is_string($configured) && $configured !== '' && in_array($configured, timezone_identifiers_list(), true)) {
                $name = $configured;
            }
        } catch (\Throwable) {
            // Database not ready - stick with the default.
        }

        self::$displayZone = new DateTimeZone($name);

        return self::$displayZone;
    }

    public static function resetTimezone(): void
    {
        self::$displayZone = null;
    }

    /* ----------------------------------------------------------------
     * Numbers
     * ---------------------------------------------------------------- */

    public static function number(int|float|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '0';
        }

        [$decimalSeparator, $thousandsSeparator] = I18n::locale() === 'cs' ? [',', "\u{00A0}"] : ['.', ','];

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    public static function bytes(?int $bytes, int $precision = 1): string
    {
        if ($bytes === null) {
            return '–';
        }

        if ($bytes < 1024) {
            return self::number($bytes) . ' B';
        }

        $units = ['kB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float) $bytes;
        $unit = -1;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $decimals = $value < 10 ? $precision : 0;

        return self::number($value, $decimals) . ' ' . $units[$unit];
    }

    public static function percent(float $value, int $decimals = 0): string
    {
        return self::number($value, $decimals) . ' %';
    }

    /* ----------------------------------------------------------------
     * Dates - stored as UTC, displayed in the configured timezone
     * ---------------------------------------------------------------- */

    public static function toDisplay(?string $utc): ?DateTimeImmutable
    {
        if ($utc === null || $utc === '' || $utc === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))
                ->setTimezone(self::displayTimezone());
        } catch (\Throwable) {
            return null;
        }
    }

    public static function date(?string $utc, bool $withTime = true): string
    {
        $date = self::toDisplay($utc);

        if ($date === null) {
            return '–';
        }

        if (I18n::locale() === 'cs') {
            return $date->format($withTime ? 'j. n. Y, H:i' : 'j. n. Y');
        }

        return $date->format($withTime ? 'j M Y, H:i' : 'j M Y');
    }

    public static function dateTimeLocal(?string $utc): string
    {
        $date = self::toDisplay($utc);

        return $date === null ? '' : $date->format('Y-m-d\TH:i');
    }

    /**
     * Converts a datetime-local input value (display timezone) to UTC.
     */
    public static function fromDisplayInput(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, self::displayTimezone()))
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function ago(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '–';
        }

        $timestamp = strtotime($utc . ' UTC');

        if ($timestamp === false) {
            return '–';
        }

        $diff = time() - $timestamp;

        if ($diff < 0) {
            return self::until($utc);
        }

        if ($diff < 45) {
            return I18n::t('time.just_now');
        }

        if ($diff < 3600) {
            return I18n::choice('time.minutes_ago', (int) round($diff / 60));
        }

        if ($diff < 86400) {
            return I18n::choice('time.hours_ago', (int) round($diff / 3600));
        }

        if ($diff < 2592000) {
            return I18n::choice('time.days_ago', (int) round($diff / 86400));
        }

        if ($diff < 31536000) {
            return I18n::choice('time.months_ago', (int) round($diff / 2592000));
        }

        return I18n::choice('time.years_ago', (int) round($diff / 31536000));
    }

    public static function until(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '–';
        }

        $timestamp = strtotime($utc . ' UTC');

        if ($timestamp === false) {
            return '–';
        }

        $diff = $timestamp - time();

        if ($diff <= 0) {
            return I18n::t('time.expired');
        }

        if ($diff < 3600) {
            return I18n::choice('time.in_minutes', (int) round($diff / 60));
        }

        if ($diff < 86400) {
            return I18n::choice('time.in_hours', (int) round($diff / 3600));
        }

        return I18n::choice('time.in_days', (int) round($diff / 86400));
    }

    /* ----------------------------------------------------------------
     * Strings
     * ---------------------------------------------------------------- */

    /** Shortens a long file name but keeps the extension visible. */
    public static function shortName(string $name, int $max = 42): string
    {
        if (mb_strlen($name) <= $max) {
            return $name;
        }

        $extension = '';
        $dot = mb_strrpos($name, '.');

        if ($dot !== false && mb_strlen($name) - $dot <= 8) {
            $extension = mb_substr($name, $dot);
            $name = mb_substr($name, 0, $dot);
        }

        $keep = max(6, $max - mb_strlen($extension) - 1);

        return mb_substr($name, 0, $keep) . '…' . $extension;
    }

    public static function truncate(?string $text, int $max = 120): string
    {
        $text = trim((string) $text);

        if ($text === '') {
            return '';
        }

        return mb_strlen($text) <= $max ? $text : mb_substr($text, 0, $max - 1) . '…';
    }
}
