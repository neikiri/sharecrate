<?php

declare(strict_types=1);

namespace App\Core;

use App\Support\Formatter;

/**
 * Translation + locale detection.
 *
 * Detection order:
 *   1. ?lang= query parameter (also stores a cookie)
 *   2. locale cookie
 *   3. locale saved on the signed in user
 *   4. GeoIP country - CZ/SK visitors get Czech
 *   5. Accept-Language header
 *   6. DEFAULT_LOCALE
 */
final class I18n
{
    public const AVAILABLE = ['en', 'cs'];

    public const COOKIE = 'dl_locale';

    private static string $locale = 'en';

    private static string $fallbackLocale = 'en';

    /** @var array<string, array<string, mixed>> */
    private static array $loaded = [];

    private static bool $detectedByGeo = false;

    public static function boot(Request $request): void
    {
        self::setLocale(self::detect($request));
    }

    /**
     * Locale detection without touching the database (used by the installer).
     */
    public static function bootLight(Request $request): void
    {
        $queryLocale = $request->queryParam('lang');
        if (is_string($queryLocale) && self::supported($queryLocale)) {
            self::remember($queryLocale);
            self::setLocale($queryLocale);

            return;
        }

        $cookie = $request->cookies[self::COOKIE] ?? null;
        if (is_string($cookie) && self::supported($cookie)) {
            self::setLocale($cookie);

            return;
        }

        $header = strtolower((string) $request->header('Accept-Language'));
        foreach (explode(',', $header) as $part) {
            $primary = strtolower(explode('-', trim(explode(';', $part)[0]))[0]);

            if ($primary === 'cs' || $primary === 'sk') {
                self::setLocale('cs');

                return;
            }

            if ($primary !== '' && self::supported($primary)) {
                self::setLocale($primary);

                return;
            }
        }

        self::setLocale(self::defaultLocale());
    }

    public static function detect(Request $request): string
    {
        // 1. Explicit switch
        $queryLocale = $request->queryParam('lang');
        if (is_string($queryLocale) && self::supported($queryLocale)) {
            self::remember($queryLocale);

            return $queryLocale;
        }

        // 2. Cookie set by an earlier switch
        $cookie = $request->cookies[self::COOKIE] ?? null;
        if (is_string($cookie) && self::supported($cookie)) {
            return $cookie;
        }

        // 3. Signed in user preference
        try {
            $user = Auth::user();
            if ($user !== null && is_string($user['locale'] ?? null) && self::supported((string) $user['locale'])) {
                return (string) $user['locale'];
            }
        } catch (\Throwable) {
            // Database might not be reachable yet - keep going.
        }

        // 4. GeoIP
        $country = Geo::countryForRequest($request);
        if ($country !== null) {
            self::$detectedByGeo = true;

            return in_array($country, self::czechCountries(), true)
                ? 'cs'
                : self::defaultLocale();
        }

        // 5. Accept-Language
        $header = strtolower((string) $request->header('Accept-Language'));
        if ($header !== '') {
            foreach (explode(',', $header) as $part) {
                $tag = trim(explode(';', $part)[0]);

                if ($tag === '') {
                    continue;
                }

                $primary = strtolower(explode('-', $tag)[0]);

                if ($primary === 'cs' || $primary === 'sk') {
                    return 'cs';
                }

                if (self::supported($primary)) {
                    return $primary;
                }
            }
        }

        return self::defaultLocale();
    }

    /** @return string[] */
    public static function czechCountries(): array
    {
        $list = Config::list('CZECH_COUNTRIES');

        return $list === [] ? ['CZ', 'SK'] : array_map('strtoupper', $list);
    }

    public static function defaultLocale(): string
    {
        $default = strtolower((string) Config::get('DEFAULT_LOCALE', 'en'));

        return self::supported($default) ? $default : 'en';
    }

    public static function supported(string $locale): bool
    {
        return in_array(strtolower($locale), self::AVAILABLE, true);
    }

    public static function detectedByGeo(): bool
    {
        return self::$detectedByGeo;
    }

    public static function setLocale(string $locale): void
    {
        $locale = strtolower($locale);
        self::$locale = self::supported($locale) ? $locale : self::$fallbackLocale;
        self::load(self::$locale);
        self::load(self::$fallbackLocale);
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function isCzech(): bool
    {
        return self::$locale === 'cs';
    }

    /** Stores the choice for a year. */
    public static function remember(string $locale): void
    {
        if (!self::supported($locale) || headers_sent()) {
            return;
        }

        setcookie(self::COOKIE, $locale, [
            'expires' => time() + 31536000,
            'path' => Config::basePath() === '' ? '/' : Config::basePath() . '/',
            'secure' => Request::current()->isSecure(),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private static function load(string $locale): void
    {
        if (isset(self::$loaded[$locale])) {
            return;
        }

        $file = LOCALE_PATH . '/' . $locale . '.php';
        $messages = is_file($file) ? require $file : [];
        self::$loaded[$locale] = is_array($messages) ? $messages : [];
    }

    /**
     * Translates a dot separated key. Placeholders use {name} syntax.
     *
     * @param array<string, string|int> $replace
     */
    public static function t(string $key, array $replace = []): string
    {
        $value = self::find($key, self::$locale);

        if ($value === null) {
            $value = self::find($key, self::$fallbackLocale);
        }

        if ($value === null) {
            return $key;
        }

        return self::interpolate($value, $replace);
    }

    public static function has(string $key): bool
    {
        return self::find($key, self::$locale) !== null || self::find($key, self::$fallbackLocale) !== null;
    }

    /**
     * Plural forms. Czech uses three variants separated by "|":
     * one | few (2-4) | many (0, 5+).
     *
     * @param array<string, string|int> $replace
     */
    public static function choice(string $key, int $count, array $replace = []): string
    {
        $value = self::find($key, self::$locale) ?? self::find($key, self::$fallbackLocale);

        if ($value === null) {
            return $key;
        }

        $forms = explode('|', $value);
        $index = self::pluralIndex($count, self::$locale, count($forms));
        $chosen = $forms[$index] ?? $forms[count($forms) - 1];

        return self::interpolate(trim($chosen), $replace + ['count' => Formatter::number($count)]);
    }

    private static function pluralIndex(int $count, string $locale, int $formCount): int
    {
        if ($locale === 'cs') {
            $index = match (true) {
                $count === 1 => 0,
                $count >= 2 && $count <= 4 => 1,
                default => 2,
            };
        } else {
            $index = $count === 1 ? 0 : 1;
        }

        return min($index, max(0, $formCount - 1));
    }

    /** @param array<string, string|int> $replace */
    private static function interpolate(string $value, array $replace): string
    {
        if ($replace === []) {
            return $value;
        }

        $keys = array_map(static fn ($k) => '{' . $k . '}', array_keys($replace));

        return str_replace($keys, array_map(static fn ($v) => (string) $v, array_values($replace)), $value);
    }

    private static function find(string $key, string $locale): ?string
    {
        self::load($locale);
        $messages = self::$loaded[$locale] ?? [];

        // 1. Regular nested walk: "files.state_active"
        $value = $messages;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $value = null;

                break;
            }

            $value = $value[$segment];
        }

        if (is_string($value)) {
            return $value;
        }

        // 2. Group plus a literal key that itself contains dots,
        //    e.g. "activity.file.created" => ['activity' => ['file.created' => ...]]
        $parts = explode('.', $key, 2);

        if (count($parts) === 2 && isset($messages[$parts[0]]) && is_array($messages[$parts[0]])) {
            $candidate = $messages[$parts[0]][$parts[1]] ?? null;

            if (is_string($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function localeNames(): array
    {
        return [
            'en' => 'English',
            'cs' => 'Čeština',
        ];
    }

    /**
     * Subset of strings handed to the JavaScript layer.
     *
     * @return array<string, string>
     */
    public static function jsStrings(): array
    {
        return [
            'copied' => self::t('common.copied'),
            'confirm' => self::t('common.confirm'),
            'are_you_sure' => self::t('common.are_you_sure'),
            'upload_failed' => self::t('upload.failed'),
            'upload_done' => self::t('upload.finished'),
            'upload_cancelled' => self::t('upload.cancelled'),
            'file_too_large' => self::t('upload.too_large'),
            'network_error' => self::t('errors.network'),
        ];
    }
}
