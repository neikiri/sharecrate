<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Very small user agent classifier - enough to show "Chrome on Windows"
 * in the download log without shipping a 1 MB regex database.
 */
final class UserAgent
{
    /** @return array{browser: ?string, platform: ?string, is_bot: bool} */
    public static function parse(?string $agent): array
    {
        if ($agent === null || trim($agent) === '') {
            return ['browser' => null, 'platform' => null, 'is_bot' => false];
        }

        return [
            'browser' => self::browser($agent),
            'platform' => self::platform($agent),
            'is_bot' => self::isBot($agent),
        ];
    }

    public static function browser(string $agent): ?string
    {
        $candidates = [
            'Edge' => '/edg(?:e|a|ios)?\//i',
            'Opera' => '/(?:opr|opera)\//i',
            'Vivaldi' => '/vivaldi/i',
            'Brave' => '/brave/i',
            'Samsung Internet' => '/samsungbrowser/i',
            'Yandex' => '/yabrowser/i',
            'Chrome' => '/(?:chrome|crios)\//i',
            'Firefox' => '/(?:firefox|fxios)\//i',
            'Safari' => '/safari\//i',
            'curl' => '/curl\//i',
            'wget' => '/wget/i',
            'aria2' => '/aria2/i',
            'Postman' => '/postman/i',
            'Python' => '/python-requests|urllib/i',
        ];

        foreach ($candidates as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                return $name;
            }
        }

        if (self::isBot($agent)) {
            return 'Bot';
        }

        return null;
    }

    public static function platform(string $agent): ?string
    {
        $candidates = [
            'Windows' => '/windows nt/i',
            'Android' => '/android/i',
            'iPadOS' => '/ipad/i',
            'iOS' => '/iphone|ipod/i',
            'macOS' => '/macintosh|mac os x/i',
            'Chrome OS' => '/cros/i',
            'Ubuntu' => '/ubuntu/i',
            'Linux' => '/linux/i',
            'FreeBSD' => '/freebsd/i',
        ];

        foreach ($candidates as $name => $pattern) {
            if (preg_match($pattern, $agent) === 1) {
                return $name;
            }
        }

        return null;
    }

    public static function isBot(string $agent): bool
    {
        return preg_match(
            '/bot|crawler|spider|crawling|facebookexternalhit|slurp|bingpreview|discordbot|telegrambot|whatsapp|preview|monitor|pingdom|uptime/i',
            $agent
        ) === 1;
    }

    /** Icon name for the platform column. */
    public static function platformIcon(?string $platform): string
    {
        return match ($platform) {
            'Android', 'iOS' => 'smartphone',
            'iPadOS' => 'monitor',
            null => 'globe',
            default => 'monitor',
        };
    }
}
