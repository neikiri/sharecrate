<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use App\Models\Setting;

/**
 * Link aliases.
 *
 * The alias is the whole public URL: example.com/<alias>. It keeps the file
 * extension so the recipient can tell what they are about to download, and it
 * can be edited freely from the dashboard.
 */
final class Alias
{
    /** Never hand these out - they collide with real files or routes. */
    public const RESERVED = [
        'admin', 'api', 'app', 'assets', 'install', 'storage', 'bin', 'database',
        'index', 'index.php', 'f', 'd', 'p', 't', 'lang', 'health', 'legal',
        'robots.txt', 'favicon.ico', 'favicon.svg', 'sitemap.xml', 'manifest.json',
        'login', 'logout', 'download', 'downloads', 'files', 'file', 'upload', 'uploads',
        'settings', 'users', 'profile', 'import', 'well-known',
    ];

    private const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,159}$/';

    /** @var array<string, string> */
    private const TRANSLITERATE = [
        'á' => 'a', 'ä' => 'a', 'â' => 'a', 'à' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a',
        'č' => 'c', 'ć' => 'c', 'ç' => 'c', 'ĉ' => 'c',
        'ď' => 'd', 'đ' => 'd',
        'é' => 'e', 'ě' => 'e', 'ë' => 'e', 'ê' => 'e', 'è' => 'e', 'ę' => 'e', 'ē' => 'e',
        'ğ' => 'g',
        'í' => 'i', 'ï' => 'i', 'î' => 'i', 'ì' => 'i', 'ī' => 'i',
        'ĺ' => 'l', 'ľ' => 'l', 'ł' => 'l',
        'ň' => 'n', 'ń' => 'n', 'ñ' => 'n',
        'ó' => 'o', 'ö' => 'o', 'ô' => 'o', 'ò' => 'o', 'õ' => 'o', 'ø' => 'o', 'ő' => 'o',
        'ř' => 'r', 'ŕ' => 'r',
        'š' => 's', 'ś' => 's', 'ş' => 's',
        'ť' => 't', 'ţ' => 't',
        'ú' => 'u', 'ů' => 'u', 'ü' => 'u', 'û' => 'u', 'ù' => 'u', 'ū' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'þ' => 'th', 'ð' => 'd',
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ж' => 'zh',
        'з' => 'z', 'и' => 'i', 'й' => 'j', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
        'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ы' => 'y', 'э' => 'e',
        'ю' => 'ju', 'я' => 'ja',
    ];

    /**
     * Turns any text into a URL safe slug (diacritics are transliterated).
     */
    public static function slug(string $value, bool $keepDots = false): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, self::TRANSLITERATE);

        // Anything left that is not ASCII gets dropped.
        $pattern = $keepDots ? '/[^a-z0-9._-]+/u' : '/[^a-z0-9_-]+/u';
        $value = preg_replace($pattern, '-', $value) ?? '';
        $value = preg_replace('/-{2,}/', '-', $value) ?? $value;
        $value = preg_replace('/\.{2,}/', '.', $value) ?? $value;
        $value = trim($value, '-._');

        return mb_substr($value, 0, 120);
    }

    /**
     * Default alias for a file name: "Výroční zpráva 2026.pdf" -> "vyrocni-zprava-2026.pdf"
     */
    public static function fromFilename(string $filename): string
    {
        $extension = FileTypes::extension($filename);
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $slug = self::slug($stem);

        if ($slug === '') {
            $slug = 'file-' . self::random(4);
        }

        $style = (string) (Setting::get('alias_style', 'slug') ?? 'slug');

        if ($style === 'random') {
            $slug = self::random(max(4, (int) (Setting::get('alias_random_len', '6') ?? '6')));
        } elseif ($style === 'slug_random') {
            $slug .= '-' . self::random(max(3, (int) (Setting::get('alias_random_len', '6') ?? '6')));
        }

        return $extension !== '' ? $slug . '.' . $extension : $slug;
    }

    public static function random(int $length = 6): string
    {
        // No look-alike characters, so aliases stay easy to read out loud.
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }

    public static function isValid(string $alias): bool
    {
        if (preg_match(self::PATTERN, $alias) !== 1) {
            return false;
        }

        if (str_contains($alias, '..')) {
            return false;
        }

        return true;
    }

    public static function isReserved(string $alias): bool
    {
        $lower = strtolower($alias);

        if (in_array($lower, self::RESERVED, true)) {
            return true;
        }

        // Reject anything that shadows a real file or directory in the web root.
        $firstSegment = explode('/', $lower)[0];

        return in_array($firstSegment, ['assets', 'admin', 'app', 'api', 'install', 'storage', 'bin', 'database'], true);
    }

    public static function isTaken(string $alias, ?int $ignoreFileId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM files WHERE alias = :alias';
        $params = ['alias' => $alias];

        if ($ignoreFileId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreFileId;
        }

        return (int) Database::value($sql, $params) > 0;
    }

    /**
     * Makes the alias unique by appending a short random suffix when needed.
     */
    public static function unique(string $base, ?int $ignoreFileId = null): string
    {
        $base = self::isValid($base) ? $base : self::fromFilename($base);
        $extension = FileTypes::extension($base);
        $stem = $extension !== '' ? substr($base, 0, strlen($base) - strlen($extension) - 1) : $base;
        $suffix = $extension !== '' ? '.' . $extension : '';

        $candidate = $stem . $suffix;
        $attempt = 0;

        while (self::isReserved($candidate) || self::isTaken($candidate, $ignoreFileId)) {
            $attempt++;
            $candidate = $stem . '-' . self::random($attempt > 6 ? 8 : 4) . $suffix;

            if ($attempt > 25) {
                $candidate = self::random(12) . $suffix;

                break;
            }
        }

        return $candidate;
    }
}
