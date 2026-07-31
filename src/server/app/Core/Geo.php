<?php

declare(strict_types=1);

namespace App\Core;

/**
 * IP -> country/city resolution.
 *
 * Providers, in order:
 *   - Cloudflare / reverse proxy headers (free, instant)
 *   - Apache mod_geoip or mod_maxminddb environment variables
 *   - the PHP geoip extension when installed
 *   - ip-api.com as a last resort (cached in the geo_cache table)
 *
 * Set GEOIP_PROVIDER=none in .env to switch geo detection off completely.
 */
final class Geo
{
    private const CACHE_DAYS = 30;

    /** @var array<string, array{country: ?string, city: ?string}> */
    private static array $memo = [];

    private static ?string $requestCountry = null;

    private static bool $requestResolved = false;

    public static function provider(): string
    {
        return strtolower((string) Config::get('GEOIP_PROVIDER', 'auto'));
    }

    public static function enabled(): bool
    {
        return self::provider() !== 'none';
    }

    public static function countryForRequest(Request $request): ?string
    {
        if (self::$requestResolved) {
            return self::$requestCountry;
        }

        self::$requestResolved = true;
        self::$requestCountry = self::lookup($request->ip(), $request)['country'];

        return self::$requestCountry;
    }

    /**
     * @return array{country: ?string, city: ?string}
     */
    public static function lookup(string $ip, ?Request $request = null): array
    {
        $empty = ['country' => null, 'city' => null];

        if (!self::enabled() || $ip === '' || self::isPrivate($ip)) {
            return $empty;
        }

        if (isset(self::$memo[$ip])) {
            return self::$memo[$ip];
        }

        $provider = self::provider();
        $result = $empty;

        // 1. Headers / server variables set by the infrastructure.
        if (in_array($provider, ['auto', 'cloudflare', 'server'], true)) {
            $fromServer = self::fromServer($request);

            if ($fromServer['country'] !== null) {
                $result = $fromServer;
            }
        }

        // 2. Cached API answer.
        if ($result['country'] === null && in_array($provider, ['auto', 'api'], true)) {
            $cached = self::fromCache($ip);

            if ($cached !== null) {
                $result = $cached;
            } else {
                $fetched = self::fromApi($ip);

                if ($fetched !== null) {
                    $result = $fetched;
                    self::store($ip, $fetched);
                } else {
                    // Remember the failure for a while so we do not hammer the API.
                    self::store($ip, $empty);
                }
            }
        }

        self::$memo[$ip] = $result;

        return $result;
    }

    public static function country(string $ip): ?string
    {
        return self::lookup($ip)['country'];
    }

    /**
     * @return array{country: ?string, city: ?string}
     */
    private static function fromServer(?Request $request): array
    {
        $server = $request?->server ?? $_SERVER;

        $candidates = [
            'HTTP_CF_IPCOUNTRY',
            'GEOIP_COUNTRY_CODE',
            'MM_COUNTRY_CODE',
            'HTTP_X_COUNTRY_CODE',
            'HTTP_X_GEO_COUNTRY',
        ];

        foreach ($candidates as $key) {
            $value = $server[$key] ?? null;

            if (is_string($value) && preg_match('/^[A-Za-z]{2}$/', $value) === 1) {
                $code = strtoupper($value);

                if ($code === 'XX' || $code === 'T1') {
                    continue;
                }

                $city = $server['GEOIP_CITY'] ?? $server['HTTP_CF_IPCITY'] ?? null;

                return [
                    'country' => $code,
                    'city' => is_string($city) && $city !== '' ? mb_substr($city, 0, 120) : null,
                ];
            }
        }

        if (function_exists('geoip_country_code_by_name') && $request !== null) {
            $code = @geoip_country_code_by_name($request->ip());

            if (is_string($code) && strlen($code) === 2) {
                return ['country' => strtoupper($code), 'city' => null];
            }
        }

        return ['country' => null, 'city' => null];
    }

    /**
     * @return array{country: ?string, city: ?string}|null
     */
    private static function fromCache(string $ip): ?array
    {
        try {
            $row = Database::one(
                'SELECT country, city, created_at FROM geo_cache WHERE ip_hash = :hash LIMIT 1',
                ['hash' => self::hash($ip)]
            );
        } catch (\Throwable) {
            return null;
        }

        if ($row === null) {
            return null;
        }

        if (strtotime((string) $row['created_at']) < time() - self::CACHE_DAYS * 86400) {
            return null;
        }

        return [
            'country' => is_string($row['country']) && $row['country'] !== '' ? $row['country'] : null,
            'city' => is_string($row['city']) && $row['city'] !== '' ? $row['city'] : null,
        ];
    }

    /** @param array{country: ?string, city: ?string} $data */
    private static function store(string $ip, array $data): void
    {
        try {
            Database::run(
                'INSERT INTO geo_cache (ip_hash, country, city, created_at)
                 VALUES (:hash, :country, :city, :now)
                 ON DUPLICATE KEY UPDATE country = :country2, city = :city2, created_at = :now2',
                [
                    'hash' => self::hash($ip),
                    'country' => $data['country'],
                    'country2' => $data['country'],
                    'city' => $data['city'],
                    'city2' => $data['city'],
                    'now' => gmdate('Y-m-d H:i:s'),
                    'now2' => gmdate('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Cache misses are harmless.
        }
    }

    /**
     * @return array{country: ?string, city: ?string}|null
     */
    private static function fromApi(string $ip): ?array
    {
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,city';
        $body = self::httpGet($url, 1500);

        if ($body === null) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return null;
        }

        $code = $decoded['countryCode'] ?? null;
        $city = $decoded['city'] ?? null;

        if (!is_string($code) || strlen($code) !== 2) {
            return null;
        }

        return [
            'country' => strtoupper($code),
            'city' => is_string($city) && $city !== '' ? mb_substr($city, 0, 120) : null,
        ];
    }

    private static function httpGet(string $url, int $timeoutMs): ?string
    {
        $seconds = max(1, (int) ceil($timeoutMs / 1000));

        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle === false) {
                return null;
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => $timeoutMs,
                CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT => 'ShareCrate/1.0',
            ]);

            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return is_string($body) && $status === 200 ? $body : null;
        }

        if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $seconds,
                'method' => 'GET',
                'header' => "User-Agent: ShareCrate/1.0\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) ? $body : null;
    }

    public static function isPrivate(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    public static function hash(string $ip): string
    {
        return hash('sha256', $ip . '|' . Config::appKey());
    }

    /**
     * Masks the host part so logs can stay GDPR friendly.
     */
    public static function anonymise(string $ip): string
    {
        if (str_contains($ip, ':')) {
            $parts = explode(':', $ip);
            $keep = array_slice($parts, 0, 4);

            return implode(':', $keep) . '::';
        }

        $parts = explode('.', $ip);

        if (count($parts) === 4) {
            $parts[3] = '0';

            return implode('.', $parts);
        }

        return $ip;
    }

    /** Regional indicator emoji for a country code. */
    public static function flag(?string $code): string
    {
        if ($code === null || strlen($code) !== 2 || preg_match('/^[A-Za-z]{2}$/', $code) !== 1) {
            return '🌐';
        }

        $code = strtoupper($code);
        $flag = '';

        for ($i = 0; $i < 2; $i++) {
            $flag .= mb_chr(0x1F1E6 + (ord($code[$i]) - 65), 'UTF-8');
        }

        return $flag;
    }

    /** Human readable country name in the active locale. */
    public static function name(?string $code): string
    {
        if ($code === null || $code === '') {
            return I18n::t('common.unknown');
        }

        $code = strtoupper($code);

        if (class_exists(\Locale::class) && method_exists(\Locale::class, 'getDisplayRegion')) {
            $name = \Locale::getDisplayRegion('-' . $code, I18n::locale());

            if (is_string($name) && $name !== '' && strtoupper($name) !== $code) {
                return $name;
            }
        }

        $fallback = self::commonNames()[$code] ?? null;

        if ($fallback === null) {
            return $code;
        }

        return $fallback[I18n::locale()] ?? $fallback['en'] ?? $code;
    }

    /**
     * Small offline list so the most common countries read nicely even
     * without the intl extension.
     *
     * @return array<string, array<string, string>>
     */
    private static function commonNames(): array
    {
        return [
            'CZ' => ['cs' => 'Česko', 'en' => 'Czechia'],
            'SK' => ['cs' => 'Slovensko', 'en' => 'Slovakia'],
            'PL' => ['cs' => 'Polsko', 'en' => 'Poland'],
            'DE' => ['cs' => 'Německo', 'en' => 'Germany'],
            'AT' => ['cs' => 'Rakousko', 'en' => 'Austria'],
            'GB' => ['cs' => 'Spojené království', 'en' => 'United Kingdom'],
            'US' => ['cs' => 'Spojené státy', 'en' => 'United States'],
            'CA' => ['cs' => 'Kanada', 'en' => 'Canada'],
            'FR' => ['cs' => 'Francie', 'en' => 'France'],
            'ES' => ['cs' => 'Španělsko', 'en' => 'Spain'],
            'IT' => ['cs' => 'Itálie', 'en' => 'Italy'],
            'NL' => ['cs' => 'Nizozemsko', 'en' => 'Netherlands'],
            'UA' => ['cs' => 'Ukrajina', 'en' => 'Ukraine'],
            'RU' => ['cs' => 'Rusko', 'en' => 'Russia'],
            'HU' => ['cs' => 'Maďarsko', 'en' => 'Hungary'],
            'CH' => ['cs' => 'Švýcarsko', 'en' => 'Switzerland'],
            'SE' => ['cs' => 'Švédsko', 'en' => 'Sweden'],
            'NO' => ['cs' => 'Norsko', 'en' => 'Norway'],
            'FI' => ['cs' => 'Finsko', 'en' => 'Finland'],
            'DK' => ['cs' => 'Dánsko', 'en' => 'Denmark'],
            'IE' => ['cs' => 'Irsko', 'en' => 'Ireland'],
            'PT' => ['cs' => 'Portugalsko', 'en' => 'Portugal'],
            'BE' => ['cs' => 'Belgie', 'en' => 'Belgium'],
            'RO' => ['cs' => 'Rumunsko', 'en' => 'Romania'],
            'BG' => ['cs' => 'Bulharsko', 'en' => 'Bulgaria'],
            'HR' => ['cs' => 'Chorvatsko', 'en' => 'Croatia'],
            'SI' => ['cs' => 'Slovinsko', 'en' => 'Slovenia'],
            'GR' => ['cs' => 'Řecko', 'en' => 'Greece'],
            'TR' => ['cs' => 'Turecko', 'en' => 'Türkiye'],
            'JP' => ['cs' => 'Japonsko', 'en' => 'Japan'],
            'CN' => ['cs' => 'Čína', 'en' => 'China'],
            'IN' => ['cs' => 'Indie', 'en' => 'India'],
            'AU' => ['cs' => 'Austrálie', 'en' => 'Australia'],
            'BR' => ['cs' => 'Brazílie', 'en' => 'Brazil'],
        ];
    }
}
